<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
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

        // Team statistics
        $teamStats = [
            'total_agents' => $agents->count(),
            'agents_online' => $agents->whereIn('availability', ['available', 'busy'])->count(),
            'agents_available' => $agents->where('availability', 'available')->count(),
            'agents_busy' => $agents->where('availability', 'busy')->count(),
            'agents_away' => $agents->where('availability', 'away')->count(),
        ];

        // SLA statistics
        $slaStats = [
            'total_tickets' => $allTickets->count(),
            'sla_breaches_today' => $todayTickets->where('sla_breached', true)->count(),
            'compliance_rate' => $allTickets->count() > 0 
                ? round((($allTickets->count() - $allTickets->where('sla_breached', true)->count()) / $allTickets->count()) * 100, 2)
                : 0,
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
            ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
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
}
