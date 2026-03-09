<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Ticket;
use App\Services\MitelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends Controller
{
    protected MitelService $mitelService;

    public function __construct(MitelService $mitelService)
    {
        $this->mitelService = $mitelService;
    }

    /**
     * Get agent dashboard metrics.
     */
    public function agentDashboard(Request $request)
    {
        $agentId = $request->get('agent_id') ?? auth()->user()->agent->id;
        $agent = Agent::with('user')->findOrFail($agentId);

        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();

        // Today's metrics
        $todayTickets = Ticket::where('assigned_to', $agentId)
            ->whereDate('created_at', $today)
            ->get();

        // This week's metrics
        $weekTickets = Ticket::where('assigned_to', $agentId)
            ->whereBetween('created_at', [$thisWeek, Carbon::now()])
            ->get();

        // This month's metrics
        $monthTickets = Ticket::where('assigned_to', $agentId)
            ->whereBetween('created_at', [$thisMonth, Carbon::now()])
            ->get();

        // Current queue
        $currentQueue = Ticket::where('assigned_to', $agentId)
            ->whereIn('status', ['Open', 'In Progress', 'Pending'])
            ->get();

        $metrics = [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->user->name,
                'agent_code' => $agent->agent_code,
                'department' => $agent->department,
                'availability' => $agent->availability,
            ],
            'today' => [
                'tickets' => $todayTickets->count(),
                'target' => $agent->daily_target,
                'resolved' => $todayTickets->whereIn('status', ['Resolved', 'Closed'])->count(),
                'sla_breaches' => $todayTickets->where('sla_breached', true)->count(),
                'progress_percentage' => $agent->daily_target > 0 
                    ? round(($todayTickets->whereIn('status', ['Resolved', 'Closed'])->count() / $agent->daily_target) * 100, 2)
                    : 0,
            ],
            'week' => [
                'tickets' => $weekTickets->count(),
                'resolved' => $weekTickets->whereIn('status', ['Resolved', 'Closed'])->count(),
                'avg_resolution_time' => round($weekTickets->whereNotNull('resolution_time_minutes')->avg('resolution_time_minutes'), 2),
                'csat' => round($weekTickets->whereNotNull('customer_rating')->avg('customer_rating'), 2),
            ],
            'month' => [
                'tickets' => $monthTickets->count(),
                'resolved' => $monthTickets->whereIn('status', ['Resolved', 'Closed'])->count(),
                'sla_compliance' => $monthTickets->count() > 0 
                    ? round((($monthTickets->count() - $monthTickets->where('sla_breached', true)->count()) / $monthTickets->count()) * 100, 2)
                    : 0,
            ],
            'current_queue' => [
                'total' => $currentQueue->count(),
                'open' => $currentQueue->where('status', 'Open')->count(),
                'in_progress' => $currentQueue->where('status', 'In Progress')->count(),
                'pending' => $currentQueue->where('status', 'Pending')->count(),
                'priority_breakdown' => [
                    'P1' => $currentQueue->where('priority', 'P1')->count(),
                    'P2' => $currentQueue->where('priority', 'P2')->count(),
                    'P3' => $currentQueue->where('priority', 'P3')->count(),
                    'P4' => $currentQueue->where('priority', 'P4')->count(),
                ],
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Get supervisor dashboard metrics.
     */
    public function supervisorDashboard(Request $request)
    {
        $department = $request->get('department');
        
        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department is required'
            ], 422);
        }

        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();

        // Get all agents in department
        $agents = Agent::where('department', $department)->with('user')->get();

        // Get department tickets
        $allTickets = Ticket::where('department', $department)->get();
        $todayTickets = Ticket::where('department', $department)
            ->whereDate('created_at', $today)
            ->get();

        // Queue statistics
        $queueStats = [
            'waiting' => Ticket::where('department', $department)
                ->where('status', 'Open')
                ->count(),
            'in_progress' => Ticket::where('department', $department)
                ->where('status', 'In Progress')
                ->count(),
            'on_hold' => Ticket::where('department', $department)
                ->where('status', 'On Hold')
                ->count(),
        ];

        // Team statistics - Get real-time status from Mitel API
        $agentsOnline = 0;
        $agentsAvailable = 0;
        $agentsBusy = 0;
        $agentsAway = 0;

        try {
            // Get real-time agent states from Mitel API
            $mitelAgentStates = $this->mitelService->getAgentStates();
            
            // Create a map of mitel_agent_id => state for quick lookup
            $mitelStateMap = [];
            if (isset($mitelAgentStates['items']) && is_array($mitelAgentStates['items'])) {
                foreach ($mitelAgentStates['items'] as $mitelAgent) {
                    $agentId = $mitelAgent['id'] ?? $mitelAgent['agentId'] ?? null;
                    if ($agentId) {
                        $mitelStateMap[$agentId] = [
                            'logged_in' => $mitelAgent['loggedIn'] ?? false,
                            'state' => $mitelAgent['state'] ?? 'Unknown',
                            'agent_name' => $mitelAgent['name'] ?? 'Unknown',
                        ];
                    }
                }
            }

            // Match local agents with Mitel data
            foreach ($agents as $agent) {
                if ($agent->mitel_agent_id && isset($mitelStateMap[$agent->mitel_agent_id])) {
                    $mitelData = $mitelStateMap[$agent->mitel_agent_id];
                    
                    if ($mitelData['logged_in']) {
                        // Agent is logged into Mitel
                        $agentsOnline++;
                        
                        // Count by Mitel state
                        switch ($mitelData['state']) {
                            case 'Available':
                                $agentsAvailable++;
                                break;
                            case 'Busy':
                            case 'InUse':
                                $agentsBusy++;
                                break;
                            case 'Away':
                            case 'DoNotDisturb':
                            case 'DND':
                                $agentsAway++;
                                break;
                        }
                        
                        // Update local database with latest Mitel state (async, don't block)
                        $agent->mitel_logged_in = true;
                        $agent->mitel_state = $mitelData['state'];
                        $agent->mitel_last_state_change = Carbon::now();
                        $agent->save();
                    }
                } elseif ($agent->mitel_agent_id) {
                    // Agent has Mitel ID but not found in real-time data (logged out)
                    if ($agent->mitel_logged_in) {
                        // Update local state to logged out
                        $agent->mitel_logged_in = false;
                        $agent->mitel_state = 'Logout';
                        $agent->mitel_last_state_change = Carbon::now();
                        $agent->save();
                    }
                }
            }
        } catch (\Exception $e) {
            // If Mitel API fails, fall back to database values
            Log::warning('Failed to fetch real-time agent states from Mitel', [
                'error' => $e->getMessage(),
                'department' => $department
            ]);
            
            foreach ($agents as $agent) {
                if ($agent->mitel_agent_id && $agent->mitel_logged_in) {
                    $agentsOnline++;
                    
                    switch ($agent->mitel_state) {
                        case 'Available':
                            $agentsAvailable++;
                            break;
                        case 'Busy':
                        case 'InUse':
                            $agentsBusy++;
                            break;
                        case 'Away':
                        case 'DoNotDisturb':
                        case 'DND':
                            $agentsAway++;
                            break;
                    }
                }
            }
        }

        $teamStats = [
            'total_agents' => $agents->count(),
            'agents_online' => $agentsOnline,
            'agents_available' => $agentsAvailable,
            'agents_busy' => $agentsBusy,
            'agents_away' => $agentsAway,
            'data_source' => isset($mitelStateMap) && count($mitelStateMap) > 0 ? 'mitel_api' : 'database',
        ];

        // SLA statistics
        $slaStats = [
            'total_tickets' => $allTickets->count(),
            'sla_breaches_today' => $todayTickets->where('sla_breached', true)->count(),
            'compliance_rate' => $allTickets->count() > 0 
                ? round((($allTickets->count() - $allTickets->where('sla_breached', true)->count()) / $allTickets->count()) * 100, 2)
                : 0,
            'by_priority' => [
                'P1' => $this->calculatePriorityCompliance($allTickets, 'P1', $department),
                'P2' => $this->calculatePriorityCompliance($allTickets, 'P2', $department),
                'P3' => $this->calculatePriorityCompliance($allTickets, 'P3', $department),
                'P4' => $this->calculatePriorityCompliance($allTickets, 'P4', $department),
            ],
            'warnings_count' => $todayTickets->where('sla_warning', true)->whereNull('sla_breached_at')->count(),
            'on_track' => $todayTickets->where('sla_warning', false)->whereNull('sla_breached_at')->count(),
        ];

        // Agent performance comparison
        $agentPerformance = $agents->map(function ($agent) use ($todayTickets) {
            $agentTickets = $todayTickets->where('assigned_to', $agent->id);
            return [
                'agent_id' => $agent->id,
                'name' => $agent->user->name,
                'tickets_today' => $agentTickets->count(),
                'resolved_today' => $agentTickets->whereIn('status', ['Resolved', 'Closed'])->count(),
                'avg_resolution_time' => round($agentTickets->whereNotNull('resolution_time_minutes')->avg('resolution_time_minutes'), 2),
                'csat' => round($agentTickets->whereNotNull('customer_rating')->avg('customer_rating'), 2),
                'availability' => $agent->availability,
            ];
        })->sortByDesc('tickets_today')->values();

        // Trends
        $ticketsByHour = Ticket::where('department', $department)
            ->whereDate('created_at', $today)
            ->select(DB::raw('EXTRACT(HOUR FROM created_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('EXTRACT(HOUR FROM created_at)'))
            ->orderBy('hour')
            ->get();

        $metrics = [
            'department' => $department,
            'queue' => $queueStats,
            'team' => $teamStats,
            'sla' => $slaStats,
            'agents' => $agentPerformance,
            'trends' => [
                'tickets_by_hour' => $ticketsByHour,
                'tickets_by_priority' => [
                    'P1' => $allTickets->where('priority', 'P1')->count(),
                    'P2' => $allTickets->where('priority', 'P2')->count(),
                    'P3' => $allTickets->where('priority', 'P3')->count(),
                    'P4' => $allTickets->where('priority', 'P4')->count(),
                ],
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Get queue statistics widget.
     */
    public function getQueueStats(Request $request)
    {
        try {
            $department = $request->get('department');
            
            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department is required'
                ], 422);
            }

            $queueStats = [
                'waiting' => Ticket::where('department', $department)
                    ->where('status', 'Open')
                    ->count(),
                'in_progress' => Ticket::where('department', $department)
                    ->where('status', 'In Progress')
                    ->count(),
                'on_hold' => Ticket::where('department', $department)
                    ->where('status', 'On Hold')
                    ->count(),
                'total' => Ticket::where('department', $department)
                    ->whereIn('status', ['Open', 'In Progress', 'On Hold'])
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $queueStats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch queue stats', [
                'error' => $e->getMessage(),
                'department' => $request->get('department')
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load queue statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get team statistics widget (with Mitel integration).
     */
    public function getTeamStats(Request $request)
    {
        try {
            $department = $request->get('department');
            
            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department is required'
                ], 422);
            }

            $agents = Agent::where('department', $department)->with('user')->get();
            
            $agentsOnline = 0;
            $agentsAvailable = 0;
            $agentsBusy = 0;
            $agentsAway = 0;
            $mitelEnabled = config('services.mitel.enabled', false);
            $dataSource = 'database';

            if ($mitelEnabled) {
                try {
                    $mitelAgentStates = $this->mitelService->getAgentStates();
                    
                    $mitelStateMap = [];
                    if (isset($mitelAgentStates['items']) && is_array($mitelAgentStates['items'])) {
                        foreach ($mitelAgentStates['items'] as $mitelAgent) {
                            $agentId = $mitelAgent['id'] ?? $mitelAgent['agentId'] ?? null;
                            if ($agentId) {
                                $mitelStateMap[$agentId] = [
                                    'logged_in' => $mitelAgent['loggedIn'] ?? false,
                                    'state' => $mitelAgent['state'] ?? 'Unknown',
                                ];
                            }
                        }
                    }

                    foreach ($agents as $agent) {
                        if ($agent->mitel_agent_id && isset($mitelStateMap[$agent->mitel_agent_id])) {
                            $mitelData = $mitelStateMap[$agent->mitel_agent_id];
                            
                            if ($mitelData['logged_in']) {
                                $agentsOnline++;
                                
                                switch ($mitelData['state']) {
                                    case 'Available':
                                        $agentsAvailable++;
                                        break;
                                    case 'Busy':
                                    case 'InUse':
                                        $agentsBusy++;
                                        break;
                                    case 'Away':
                                    case 'DoNotDisturb':
                                    case 'DND':
                                        $agentsAway++;
                                        break;
                                }
                            }
                        }
                    }
                    
                    if (count($mitelStateMap) > 0) {
                        $dataSource = 'mitel_api';
                    }
                } catch (\Exception $e) {
                    Log::warning('Mitel API unavailable, using database fallback', [
                        'error' => $e->getMessage()
                    ]);
                    $mitelEnabled = false;
                }
            }

            if (!$mitelEnabled) {
                foreach ($agents as $agent) {
                    if ($agent->mitel_agent_id && $agent->mitel_logged_in) {
                        $agentsOnline++;
                        
                        switch ($agent->mitel_state) {
                            case 'Available':
                                $agentsAvailable++;
                                break;
                            case 'Busy':
                            case 'InUse':
                                $agentsBusy++;
                                break;
                            case 'Away':
                            case 'DoNotDisturb':
                            case 'DND':
                                $agentsAway++;
                                break;
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_agents' => $agents->count(),
                    'agents_online' => $agentsOnline,
                    'agents_available' => $agentsAvailable,
                    'agents_busy' => $agentsBusy,
                    'agents_away' => $agentsAway,
                    'data_source' => $dataSource,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch team stats', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load team statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SLA statistics widget.
     */
    public function getSlaStats(Request $request)
    {
        try {
            $department = $request->get('department');
            
            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department is required'
                ], 422);
            }

            $today = Carbon::today();
            $allTickets = Ticket::where('department', $department)->get();
            $todayTickets = Ticket::where('department', $department)
                ->whereDate('created_at', $today)
                ->get();

            $slaStats = [
                'total_tickets' => $allTickets->count(),
                'sla_breaches_today' => $todayTickets->where('sla_breached', true)->count(),
                'compliance_rate' => $allTickets->count() > 0 
                    ? round((($allTickets->count() - $allTickets->where('sla_breached', true)->count()) / $allTickets->count()) * 100, 2)
                    : 0,
                'by_priority' => [
                    'P1' => $this->calculatePriorityCompliance($allTickets, 'P1', $department),
                    'P2' => $this->calculatePriorityCompliance($allTickets, 'P2', $department),
                    'P3' => $this->calculatePriorityCompliance($allTickets, 'P3', $department),
                    'P4' => $this->calculatePriorityCompliance($allTickets, 'P4', $department),
                ],
                'warnings_count' => $todayTickets->where('sla_warning', true)->whereNull('sla_breached_at')->count(),
                'on_track' => $todayTickets->where('sla_warning', false)->whereNull('sla_breached_at')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $slaStats
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch SLA stats', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load SLA statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get agent performance widget.
     */
    public function getAgentPerformance(Request $request)
    {
        try {
            $department = $request->get('department');
            
            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department is required'
                ], 422);
            }

            $today = Carbon::today();
            $agents = Agent::where('department', $department)->with('user')->get();
            $todayTickets = Ticket::where('department', $department)
                ->whereDate('created_at', $today)
                ->get();

            $agentPerformance = $agents->map(function ($agent) use ($todayTickets) {
                $agentTickets = $todayTickets->where('assigned_to', $agent->id);
                return [
                    'agent_id' => $agent->id,
                    'name' => $agent->user->name,
                    'tickets_today' => $agentTickets->count(),
                    'resolved_today' => $agentTickets->whereIn('status', ['Resolved', 'Closed'])->count(),
                    'avg_resolution_time' => round($agentTickets->whereNotNull('resolution_time_minutes')->avg('resolution_time_minutes'), 2),
                    'csat' => round($agentTickets->whereNotNull('customer_rating')->avg('customer_rating'), 2),
                    'availability' => $agent->availability,
                ];
            })->sortByDesc('tickets_today')->values();

            return response()->json([
                'success' => true,
                'data' => $agentPerformance
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch agent performance', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load agent performance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get trends widget.
     */
    public function getTrends(Request $request)
    {
        try {
            $department = $request->get('department');
            
            if (!$department) {
                return response()->json([
                    'success' => false,
                    'message' => 'Department is required'
                ], 422);
            }

            $today = Carbon::today();
            $allTickets = Ticket::where('department', $department)->get();

            $ticketsByHour = Ticket::where('department', $department)
                ->whereDate('created_at', $today)
                ->select(DB::raw('EXTRACT(HOUR FROM created_at) as hour'), DB::raw('COUNT(*) as count'))
                ->groupBy(DB::raw('EXTRACT(HOUR FROM created_at)'))
                ->orderBy('hour')
                ->get();

            $trends = [
                'tickets_by_hour' => $ticketsByHour,
                'tickets_by_priority' => [
                    'P1' => $allTickets->where('priority', 'P1')->count(),
                    'P2' => $allTickets->where('priority', 'P2')->count(),
                    'P3' => $allTickets->where('priority', 'P3')->count(),
                    'P4' => $allTickets->where('priority', 'P4')->count(),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $trends
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch trends', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate SLA compliance for a specific priority.
     * Target is read from sla_configurations — null when not configured.
     */
    private function calculatePriorityCompliance($tickets, $priority, $department)
    {
        $config = \App\Models\SlaConfiguration::getSlaForTicket($department, $priority);
        $target = $config?->compliance_target ?? null;

        $priorityTickets = $tickets->where('priority', $priority);
        $count = $priorityTickets->count();

        if ($count === 0) {
            return [
                'compliance' => null,
                'target' => $target,
                'count' => 0,
                'breaches' => 0,
            ];
        }

        $breaches = $priorityTickets->where('sla_breached', true)->count();
        $compliance = round((($count - $breaches) / $count) * 100, 1);

        return [
            'compliance' => $compliance,
            'target' => $target,
            'count' => $count,
            'breaches' => $breaches,
        ];
    }

    /**
     * Get SLA breaches and warnings details.
     */
    public function getSlaDetails(Request $request)
    {
        $department = $request->get('department');
        
        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department is required'
            ], 422);
        }

        $today = Carbon::today();

        // Get recent breaches
        $recentBreaches = Ticket::where('department', $department)
            ->where('sla_breached', true)
            ->whereDate('sla_breached_at', '>=', $today->copy()->subDays(7))
            ->with(['customer', 'assignedAgent.user'])
            ->orderBy('sla_breached_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'customer' => $ticket->customer->name ?? 'N/A',
                    'assigned_agent' => $ticket->assignedAgent->user->name ?? 'Unassigned',
                    'department' => $ticket->department,
                    'breach_time' => $ticket->sla_breached_at ? Carbon::parse($ticket->sla_breached_at)->diffForHumans() : 'N/A',
                    'breach_duration' => $ticket->sla_breach_duration_minutes ?? 0,
                ];
            });

        // Get upcoming warnings (tickets approaching SLA)
        $upcomingWarnings = Ticket::where('department', $department)
            ->where('sla_warning', true)
            ->whereNull('sla_breached_at')
            ->whereIn('status', ['Open', 'In Progress'])
            ->with(['customer', 'assignedAgent.user'])
            ->orderBy('sla_deadline_at')
            ->limit(20)
            ->get()
            ->map(function ($ticket) {
                $deadline = Carbon::parse($ticket->sla_deadline_at);
                $now = Carbon::now();
                
                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'priority' => $ticket->priority,
                    'status' => $ticket->status,
                    'customer' => $ticket->customer->name ?? 'N/A',
                    'assigned_agent' => $ticket->assignedAgent->user->name ?? 'Unassigned',
                    'department' => $ticket->department,
                    'time_remaining' => $deadline->diffForHumans($now, ['parts' => 2]),
                    'time_remaining_minutes' => $deadline->diffInMinutes($now),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'recent_breaches' => $recentBreaches,
                'upcoming_warnings' => $upcomingWarnings,
            ]
        ]);
    }

    /**
     * Get workload management data for supervisor.
     */
    public function getWorkloadManagement(Request $request)
    {
        $department = $request->get('department');
        
        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department is required'
            ], 422);
        }

        // Get all agents in department with their current workload
        $agents = Agent::where('department', $department)
            ->where('status', 'active')
            ->with('user')
            ->get()
            ->map(function ($agent) {
                // Get current active tickets
                $currentTickets = Ticket::where('assigned_to', $agent->id)
                    ->whereIn('status', ['Open', 'In Progress', 'Pending'])
                    ->get();

                // Get today's tickets for performance metrics
                $todayTickets = Ticket::where('assigned_to', $agent->id)
                    ->whereDate('created_at', Carbon::today())
                    ->get();

                // Calculate average handle time (resolution time)
                $avgHandleTime = $todayTickets->whereNotNull('resolution_time_minutes')
                    ->avg('resolution_time_minutes');
                $avgHandleTimeFormatted = $avgHandleTime 
                    ? round($avgHandleTime) . ' min' 
                    : 'N/A';

                // Calculate efficiency (resolved vs assigned ratio)
                $resolved = $todayTickets->whereIn('status', ['Resolved', 'Closed'])->count();
                $total = $todayTickets->count();
                $efficiency = $total > 0 ? round(($resolved / $total) * 100) : 0;

                return [
                    'id' => $agent->id,
                    'name' => $agent->user->name,
                    'agent_code' => $agent->agent_code,
                    'department' => $agent->department,
                    'status' => $agent->availability,
                    'current_load' => $currentTickets->count(),
                    'max_capacity' => $agent->max_concurrent_tickets ?? 12,
                    'avg_handle_time' => $avgHandleTimeFormatted,
                    'efficiency' => $efficiency,
                ];
            })
            ->sortByDesc('current_load')
            ->values();

        // Get pending unassigned tickets
        $pendingTickets = Ticket::where('department', $department)
            ->where('status', 'Open')
            ->whereNull('assigned_to')
            ->with('customer')
            ->orderBy('priority')
            ->orderBy('created_at')
            ->limit(50)
            ->get()
            ->map(function ($ticket) {
                // Estimate handle time based on priority
                $estimatedTime = match($ticket->priority) {
                    'P1' => '30-45 min',
                    'P2' => '20-30 min',
                    'P3' => '15-20 min',
                    'P4' => '10-15 min',
                    default => '15 min'
                };

                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'priority' => $ticket->priority,
                    'category' => $ticket->category,
                    'customer' => $ticket->customer ? $ticket->customer->name : 'N/A',
                    'estimated_time' => $estimatedTime,
                    'created_at' => $ticket->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'agents' => $agents,
                'pending_tickets' => $pendingTickets,
            ]
        ]);
    }
}
