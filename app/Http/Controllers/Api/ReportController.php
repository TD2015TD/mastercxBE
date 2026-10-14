<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Ticket;
use App\Models\Agent;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Display a listing of reports.
     */
    public function index(Request $request)
    {
        $query = Report::with('generatedBy');

        // Filter by report type
        if ($request->has('report_type')) {
            $query->where('report_type', $request->report_type);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('generated_by', $request->user_id);
        }

        $perPage = $request->get('per_page', 20);
        $reports = $query->orderBy('generated_at', 'desc')->paginate($perPage);

        return response()->json($reports);
    }

    /**
     * Get agent performance metrics (for Superset/BI integration).
     */
    public function getAgentMetrics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'agent_id' => 'required|exists:agents,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $agent = Agent::with('user')->findOrFail($request->agent_id);

        // Get tickets handled by agent in date range
        $tickets = Ticket::where('assigned_to', $request->agent_id)
            ->whereBetween('created_at', [$request->start_date, $request->end_date])
            ->with('customer')
            ->get();

        $kpi = $this->getKpiMetricsSummary([$agent->id], $request->start_date, $request->end_date);

        // Calculate metrics
        $metrics = [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->user->name,
                'agent_code' => $agent->agent_code,
                'department' => $agent->department,
                'email' => $agent->user->email,
            ],
            'period' => [
                'start' => $request->start_date,
                'end' => $request->end_date,
            ],
            'summary' => [
                'total_tickets' => $tickets->count(),
                'resolved_tickets' => $tickets->whereIn('status', ['Resolved', 'Closed'])->count(),
                'open_tickets' => $tickets->whereIn('status', ['Open', 'In Progress', 'Pending'])->count(),
                'sla_breached' => $tickets->where('sla_breached', true)->count(),
                'sla_compliance_rate' => $tickets->count() > 0
                    ? round((($tickets->count() - $tickets->where('sla_breached', true)->count()) / $tickets->count()) * 100, 2)
                    : 0,
                'avg_resolution_time_minutes' => round($tickets->whereNotNull('resolution_time_minutes')->avg('resolution_time_minutes') ?? 0, 2),
                'avg_first_response_time_minutes' => round($tickets->whereNotNull('first_response_time_minutes')->avg('first_response_time_minutes') ?? 0, 2),
                'customer_satisfaction' => round($tickets->whereNotNull('customer_rating')->avg('customer_rating') ?? 0, 2),
                'total_calls' => $kpi['total_calls'],
                'talk_time_minutes' => $kpi['talk_time_minutes'],
                'calls_data_available' => $kpi['has_call_data'],
                'avg_handle_time_min' => $kpi['avg_handle_time_min'],
                'avg_acw_sec' => $kpi['avg_acw_sec'],
                'avg_speed_of_answer_sec' => $kpi['avg_speed_of_answer_sec'],
                'occupancy_rate_pct' => $kpi['occupancy_rate_pct'],
                'abandon_rate_pct' => $kpi['abandon_rate_pct'],
                'abandon_data_available' => $kpi['has_abandon_data'],
                'fcr_rate_pct' => $kpi['fcr_rate_pct'],
            ],
            'breakdown' => [
                'by_priority' => [
                    'P1' => $tickets->where('priority', 'P1')->count(),
                    'P2' => $tickets->where('priority', 'P2')->count(),
                    'P3' => $tickets->where('priority', 'P3')->count(),
                    'P4' => $tickets->where('priority', 'P4')->count(),
                ],
                'by_status' => [
                    'Open' => $tickets->where('status', 'Open')->count(),
                    'In Progress' => $tickets->where('status', 'In Progress')->count(),
                    'Pending' => $tickets->where('status', 'Pending')->count(),
                    'On Hold' => $tickets->where('status', 'On Hold')->count(),
                    'Resolved' => $tickets->where('status', 'Resolved')->count(),
                    'Closed' => $tickets->where('status', 'Closed')->count(),
                ],
                'by_channel' => $tickets->groupBy('channel')->map->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Get supervisor team metrics (for Superset/BI integration).
     */
    public function getSupervisorMetrics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // No department = org-wide (admin/super_admin "All Departments" view).
        $tickets = Ticket::whereBetween('created_at', [$request->start_date, $request->end_date])
            ->when($request->filled('department'), fn ($q) => $q->whereIn('assigned_to', $this->agentIdsForDepartment($request->department)))
            ->with(['customer', 'assignedAgent.user'])
            ->get();

        $agents = Agent::when($request->filled('department'), fn ($q) => $q->where('department', $request->department))
            ->with('user')
            ->get();

        $agentIds = $agents->pluck('id')->all();
        $teamKpi = $this->getKpiMetricsSummary($agentIds, $request->start_date, $request->end_date);
        $kpiByAgent = $this->getKpiMetricsByAgentId($agentIds, $request->start_date, $request->end_date);

        // Calculate team metrics
        $metrics = [
            'department' => $request->get('department', 'All Departments'),
            'period' => [
                'start' => $request->start_date,
                'end' => $request->end_date,
            ],
            'summary' => [
                'total_tickets' => $tickets->count(),
                'resolved_tickets' => $tickets->whereIn('status', ['Resolved', 'Closed'])->count(),
                'open_tickets' => $tickets->whereIn('status', ['Open', 'In Progress', 'Pending'])->count(),
                'sla_breached' => $tickets->where('sla_breached', true)->count(),
                'sla_compliance_rate' => $tickets->count() > 0
                    ? round((($tickets->count() - $tickets->where('sla_breached', true)->count()) / $tickets->count()) * 100, 2)
                    : 0,
                'avg_resolution_time_minutes' => round($tickets->whereNotNull('resolution_time_minutes')->avg('resolution_time_minutes') ?? 0, 2),
                'customer_satisfaction' => round($tickets->whereNotNull('customer_rating')->avg('customer_rating') ?? 0, 2),
                'total_calls' => $teamKpi['total_calls'],
                'talk_time_minutes' => $teamKpi['talk_time_minutes'],
                'calls_data_available' => $teamKpi['has_call_data'],
                'avg_handle_time_min' => $teamKpi['avg_handle_time_min'],
                'avg_acw_sec' => $teamKpi['avg_acw_sec'],
                'avg_speed_of_answer_sec' => $teamKpi['avg_speed_of_answer_sec'],
                'occupancy_rate_pct' => $teamKpi['occupancy_rate_pct'],
                'abandon_rate_pct' => $teamKpi['abandon_rate_pct'],
                'abandon_data_available' => $teamKpi['has_abandon_data'],
                'fcr_rate_pct' => $teamKpi['fcr_rate_pct'],
            ],
            'breakdown' => [
                'by_priority' => [
                    'P1' => $tickets->where('priority', 'P1')->count(),
                    'P2' => $tickets->where('priority', 'P2')->count(),
                    'P3' => $tickets->where('priority', 'P3')->count(),
                    'P4' => $tickets->where('priority', 'P4')->count(),
                ],
                'by_status' => [
                    'Open' => $tickets->where('status', 'Open')->count(),
                    'In Progress' => $tickets->where('status', 'In Progress')->count(),
                    'Pending' => $tickets->where('status', 'Pending')->count(),
                    'On Hold' => $tickets->where('status', 'On Hold')->count(),
                    'Resolved' => $tickets->where('status', 'Resolved')->count(),
                    'Closed' => $tickets->where('status', 'Closed')->count(),
                ],
                'by_channel' => $tickets->groupBy('channel')->map->count(),
            ],
            'agent_performance' => $agents->map(function ($agent) use ($tickets, $kpiByAgent) {
                $agentTickets = $tickets->where('assigned_to', $agent->id);
                $agentKpi = $kpiByAgent[$agent->id] ?? $this->emptyKpiMetrics();
                return [
                    'agent_id' => $agent->id,
                    'agent_code' => $agent->agent_code,
                    'name' => $agent->user->name,
                    'email' => $agent->user->email,
                    'total_tickets' => $agentTickets->count(),
                    'resolved' => $agentTickets->whereIn('status', ['Resolved', 'Closed'])->count(),
                    'sla_compliance_rate' => $agentTickets->count() > 0
                        ? round((($agentTickets->count() - $agentTickets->where('sla_breached', true)->count()) / $agentTickets->count()) * 100, 2)
                        : 0,
                    'avg_resolution_time_minutes' => round($agentTickets->whereNotNull('resolution_time_minutes')->avg('resolution_time_minutes') ?? 0, 2),
                    'csat' => round($agentTickets->whereNotNull('customer_rating')->avg('customer_rating') ?? 0, 2),
                    'total_calls' => $agentKpi['total_calls'],
                    'talk_time_minutes' => $agentKpi['talk_time_minutes'],
                    'avg_handle_time_min' => $agentKpi['avg_handle_time_min'],
                    'occupancy_rate_pct' => $agentKpi['occupancy_rate_pct'],
                    'abandon_rate_pct' => $agentKpi['abandon_rate_pct'],
                    'fcr_rate_pct' => $agentKpi['fcr_rate_pct'],
                ];
            })->values(),
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Get ticket volume metrics by date range.
     */
    public function getTicketVolumeMetrics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'department' => 'nullable|string',
            'agent_id' => 'nullable|exists:agents,id',
            'group_by' => 'nullable|in:day,week,month',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $groupBy = $request->get('group_by', 'day');
        $dateFormat = match($groupBy) {
            'week' => 'IYYY-IW',
            'month' => 'YYYY-MM',
            default => 'YYYY-MM-DD',
        };

        $volumeByDate = Ticket::selectRaw(
                "TO_CHAR(created_at, '{$dateFormat}') as date, " .
                "COUNT(*) as total, " .
                "SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as resolved, " .
                "SUM(CASE WHEN channel = 'phone' THEN 1 ELSE 0 END) as call_count, " .
                "SUM(CASE WHEN channel = 'email' THEN 1 ELSE 0 END) as email_count, " .
                "SUM(CASE WHEN channel = 'chat' THEN 1 ELSE 0 END) as chat_count"
            )
            ->whereBetween('created_at', [$request->start_date, $request->end_date])
            ->when($request->filled('department'), function ($q) use ($request) {
                $q->whereIn('assigned_to', $this->agentIdsForDepartment($request->department));
            })
            ->when($request->filled('agent_id'), function ($q) use ($request) {
                $q->where('assigned_to', $request->agent_id);
            })
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($row) {
                return [
                    'date' => $row->date,
                    'total' => (int) $row->total,
                    'resolved' => (int) $row->resolved,
                    'pending' => (int) $row->total - (int) $row->resolved,
                    'call' => (int) $row->call_count,
                    'email' => (int) $row->email_count,
                    'chat' => (int) $row->chat_count,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $request->start_date,
                    'end' => $request->end_date,
                ],
                'group_by' => $groupBy,
                'volume' => $volumeByDate,
            ]
        ]);
    }

    /**
     * Get SLA compliance metrics.
     */
    public function getSlaComplianceMetrics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'department' => 'nullable|string',
            'agent_id' => 'nullable|exists:agents,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Ticket::whereBetween('created_at', [$request->start_date, $request->end_date]);

        if ($request->filled('department')) {
            $query->whereIn('assigned_to', $this->agentIdsForDepartment($request->department));
        }

        if ($request->filled('agent_id')) {
            $query->where('assigned_to', $request->agent_id);
        }

        $tickets = $query->get();

        $metrics = [
            'period' => [
                'start' => $request->start_date,
                'end' => $request->end_date,
            ],
            'department' => $request->get('department', 'All'),
            'summary' => [
                'total_tickets' => $tickets->count(),
                'sla_breached' => $tickets->where('sla_breached', true)->count(),
                'sla_met' => $tickets->where('sla_breached', false)->count(),
                'compliance_rate' => $tickets->count() > 0
                    ? round((($tickets->count() - $tickets->where('sla_breached', true)->count()) / $tickets->count()) * 100, 2)
                    : 0,
            ],
            'by_priority' => [
                'P1' => [
                    'total' => $tickets->where('priority', 'P1')->count(),
                    'breached' => $tickets->where('priority', 'P1')->where('sla_breached', true)->count(),
                ],
                'P2' => [
                    'total' => $tickets->where('priority', 'P2')->count(),
                    'breached' => $tickets->where('priority', 'P2')->where('sla_breached', true)->count(),
                ],
                'P3' => [
                    'total' => $tickets->where('priority', 'P3')->count(),
                    'breached' => $tickets->where('priority', 'P3')->where('sla_breached', true)->count(),
                ],
                'P4' => [
                    'total' => $tickets->where('priority', 'P4')->count(),
                    'breached' => $tickets->where('priority', 'P4')->where('sla_breached', true)->count(),
                ],
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * Get customer satisfaction metrics.
     */
    public function getCustomerSatisfactionMetrics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'department' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Ticket::whereBetween('created_at', [$request->start_date, $request->end_date])
            ->whereNotNull('customer_rating');

        if ($request->filled('department')) {
            $query->whereIn('assigned_to', $this->agentIdsForDepartment($request->department));
        }

        $tickets = $query->get();

        $ratingDistribution = [
            '1' => $tickets->where('customer_rating', 1)->count(),
            '2' => $tickets->where('customer_rating', 2)->count(),
            '3' => $tickets->where('customer_rating', 3)->count(),
            '4' => $tickets->where('customer_rating', 4)->count(),
            '5' => $tickets->where('customer_rating', 5)->count(),
        ];

        $metrics = [
            'period' => [
                'start' => $request->start_date,
                'end' => $request->end_date,
            ],
            'department' => $request->get('department', 'All'),
            'summary' => [
                'total_ratings' => $tickets->count(),
                'average_rating' => round($tickets->avg('customer_rating') ?? 0, 2),
                'rating_distribution' => $ratingDistribution,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }

    /**
     * All Ticket Report: itemized ticket listing (PDF requirement Report 1).
     */
    public function getAllTicketsReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'department' => 'nullable|string',
            'agent_id' => 'nullable|exists:agents,id',
            'channel' => 'nullable|string',
            'per_page' => 'nullable|integer|min:1|max:5000', // higher ceiling supports full-dataset CSV export
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $tickets = Ticket::with('assignedAgent.user')
            ->whereBetween('created_at', [$request->start_date, $request->end_date])
            ->when($request->filled('department'), fn ($q) => $q->whereIn('assigned_to', $this->agentIdsForDepartment($request->department)))
            ->when($request->filled('agent_id'), fn ($q) => $q->where('assigned_to', $request->agent_id))
            ->when($request->filled('channel'), fn ($q) => $q->where('channel', $request->channel))
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 25));

        $tickets->getCollection()->transform(function (Ticket $ticket) {
            return [
                'ticket_id' => $ticket->ticket_number,
                'channel' => $ticket->channel,
                'created_at' => $ticket->created_at,
                'closed_at' => $ticket->closed_at,
                'status' => $ticket->status,
                'agent_name' => optional($ticket->assignedAgent?->user)->name,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'urgency' => $ticket->urgency,
                'impact' => $ticket->impact,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    /**
     * Ticket per Agent Report, split by channel, with Mitel ACD data merged in for the phone channel
     * (PDF requirement Report 2).
     */
    public function getTicketsPerAgentReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'department' => 'nullable|string',
            'agent_id' => 'nullable|exists:agents,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $rows = Ticket::whereBetween('created_at', [$request->start_date, $request->end_date])
            ->when($request->filled('department'), fn ($q) => $q->whereIn('assigned_to', $this->agentIdsForDepartment($request->department)))
            ->when($request->filled('agent_id'), fn ($q) => $q->where('assigned_to', $request->agent_id))
            ->selectRaw("assigned_to, channel, COUNT(*) as total, SUM(CASE WHEN status IN ('Resolved', 'Closed') THEN 1 ELSE 0 END) as resolved")
            ->groupBy('assigned_to', 'channel')
            ->get();

        $agents = Agent::whereIn('id', $rows->pluck('assigned_to')->filter()->unique())
            ->with('user')
            ->get()
            ->keyBy('id');

        $kpiByAgent = $this->getKpiMetricsByAgentId($agents->keys()->all(), $request->start_date, $request->end_date);

        $result = $rows->map(function ($row) use ($agents, $kpiByAgent) {
            $agent = $agents->get($row->assigned_to);
            $agentKpi = $kpiByAgent[$row->assigned_to] ?? null;

            return [
                'agent_id' => $row->assigned_to,
                'agent_name' => optional($agent?->user)->name ?? 'Unassigned',
                'channel' => $row->channel,
                'total_tickets' => (int) $row->total,
                'resolved' => (int) $row->resolved,
                'pending' => (int) $row->total - (int) $row->resolved,
                'acd_count' => $row->channel === 'phone' ? ($agentKpi['total_calls'] ?? 0) : null,
                'talk_time_minutes' => $row->channel === 'phone' ? ($agentKpi['talk_time_minutes'] ?? 0) : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => ['start' => $request->start_date, 'end' => $request->end_date],
                'rows' => $result,
            ],
        ]);
    }

    /**
     * Interactions vs Ticket Report (PDF requirement Report 4).
     *
     * Call interactions = "Ringing" spectrum count from the voice queue (mitel_data_queue_spectrum) —
     * the only voice-side metric with real data at the Mitel source; Answer/Abandon spectrum types
     * have zero rows for the voice queue specifically (confirmed against the live Mitel server).
     * Email/Chat interactions come from the CRM (email_messages received, chatbot_sessions created),
     * per the source-system mapping in the data requirement doc.
     */
    public function getInteractionsVsTicketReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $start = $request->start_date;
        $end = $request->end_date;
        $voiceQueueIds = $this->getVoiceQueueIds();

        $callInteractionsAvailable = DB::table('mitel_data_queue_spectrum')
            ->whereIn('queue_id', $voiceQueueIds)
            ->whereBetween('period_date', [$start, $end])
            ->exists();
        $callInteractions = DB::table('mitel_data_queue_spectrum')
            ->whereIn('queue_id', $voiceQueueIds)
            ->where('spectrum_type_id', 5) // Ringing — see tblLookup_SpectrumType
            ->whereBetween('period_date', [$start, $end])
            ->sum('total_calls');

        $emailInteractions = DB::table('email_messages')
            ->where('direction', 'inbound')
            ->whereBetween('received_at', [$start, $end])
            ->count();

        $chatInteractions = DB::table('chatbot_sessions')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $rows = [
            $this->buildInteractionRow('Call', (int) $callInteractions, $this->ticketCount('phone', $start, $end), $callInteractionsAvailable),
            $this->buildInteractionRow('Email', $emailInteractions, $this->ticketCount('email', $start, $end), true),
            $this->buildInteractionRow('Chat', $chatInteractions, $this->ticketCount('chat', $start, $end), true),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'period' => ['start' => $start, 'end' => $end],
                'channels' => $rows,
            ],
        ]);
    }

    /**
     * Summary by Channel Report (PDF requirement Report 5).
     *
     * Call row: Offered available (voice queue Ringing count); Handled/Abandoned are NOT available —
     * confirmed zero rows anywhere on the live Mitel server for voice Answer/Talk/Abandon tracking.
     * Email/Chat rows: Offered/Handled/Abandoned from mitel_data_mcc_queue_performance (real, synced
     * Mitel data), Tickets/SLA/Response Time from the CRM.
     */
    public function getChannelSummaryReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $start = $request->start_date;
        $end = $request->end_date;

        $voiceQueueIds = $this->getVoiceQueueIds();
        $digitalQueueIds = DB::table('mitel_data_mcc_queue_performance')->distinct()->pluck('queue_id');
        $queueNames = DB::table('mitel_config_queues')->whereIn('queue_id', $digitalQueueIds)->pluck('name', 'queue_id');
        $chatQueueIds = $digitalQueueIds->filter(fn ($id) => stripos($queueNames[$id] ?? '', 'chat') !== false)->values();
        $emailQueueIds = $digitalQueueIds->filter(fn ($id) => stripos($queueNames[$id] ?? '', 'email') !== false)->values();

        $callRow = [
            'channel' => 'Call',
            'offered' => (int) DB::table('mitel_data_queue_spectrum')
                ->whereIn('queue_id', $voiceQueueIds)->where('spectrum_type_id', 5)
                ->whereBetween('period_date', [$start, $end])->sum('total_calls'),
            'offered_available' => DB::table('mitel_data_queue_spectrum')
                ->whereIn('queue_id', $voiceQueueIds)->whereBetween('period_date', [$start, $end])->exists(),
            'handled' => null,
            'abandoned' => null,
            'handled_available' => false,
        ] + $this->channelTicketStats('phone', $start, $end);

        $emailRow = ['channel' => 'Email'] + $this->digitalChannelStats($emailQueueIds, $start, $end) + $this->channelTicketStats('email', $start, $end);
        $chatRow = ['channel' => 'Chat'] + $this->digitalChannelStats($chatQueueIds, $start, $end) + $this->channelTicketStats('chat', $start, $end);

        return response()->json([
            'success' => true,
            'data' => [
                'period' => ['start' => $start, 'end' => $end],
                'channels' => [$callRow, $emailRow, $chatRow],
            ],
        ]);
    }

    private function getVoiceQueueIds()
    {
        $digitalQueueIds = DB::table('mitel_data_mcc_queue_performance')->distinct()->pluck('queue_id');

        // Any queue with real spectrum data that ISN'T one of the digital (chat/email) queues is
        // treated as voice — sidesteps hardcoding a specific queue name.
        return DB::table('mitel_data_queue_spectrum')
            ->whereNotIn('queue_id', $digitalQueueIds)
            ->distinct()
            ->pluck('queue_id');
    }

    private function digitalChannelStats($queueIds, string $start, string $end): array
    {
        $agg = DB::table('mitel_data_mcc_queue_performance')
            ->whereIn('queue_id', $queueIds)
            ->whereBetween('period_date', [$start, $end])
            ->selectRaw('COUNT(*) as row_count, SUM(offered) as offered, SUM(completed_count) as handled, SUM(short_abandon + long_abandon) as abandoned')
            ->first();

        $available = (int) ($agg->row_count ?? 0) > 0;

        return [
            'offered' => $available ? (int) $agg->offered : null,
            'offered_available' => $available,
            'handled' => $available ? (int) $agg->handled : null,
            'abandoned' => $available ? (int) $agg->abandoned : null,
            'handled_available' => $available,
        ];
    }

    private function channelTicketStats(string $channel, string $start, string $end): array
    {
        $tickets = Ticket::where('channel', $channel)->whereBetween('created_at', [$start, $end])->get();
        $total = $tickets->count();
        $breached = $tickets->where('sla_breached', true)->count();

        return [
            'tickets' => $total,
            'sla_pct' => $total > 0 ? round((($total - $breached) / $total) * 100, 2) : null,
            'avg_response_time_min' => round($tickets->whereNotNull('first_response_time_minutes')->avg('first_response_time_minutes') ?? 0, 2),
        ];
    }

    private function ticketCount(string $channel, string $start, string $end): int
    {
        return Ticket::where('channel', $channel)->whereBetween('created_at', [$start, $end])->count();
    }

    private function buildInteractionRow(string $label, int $interactions, int $tickets, bool $available): array
    {
        return [
            'channel' => $label,
            'interactions' => $available ? $interactions : null,
            'interactions_available' => $available,
            'tickets' => $tickets,
            'conversion_rate_pct' => ($available && $interactions > 0) ? round($tickets * 100 / $interactions, 2) : null,
        ];
    }

    /**
     * Resolves a department name (as stored on agents.department) to the CRM agent IDs in it.
     *
     * IMPORTANT: tickets.department is a completely different taxonomy from agents.department —
     * tickets.department holds the customs/tax case category (e.g. "GST", "Excise", "Sales Tax"),
     * while agents.department holds the agent's org team (e.g. "Customer Service"). They share no
     * overlapping values. Never filter a ticket query with `where('department', $dept)` when $dept
     * came from the agent/team department list — always go through this method and filter by
     * `assigned_to` instead, or every "by department" report silently returns zero rows.
     */
    private function agentIdsForDepartment(string $department): array
    {
        return Agent::where('department', $department)->pluck('id')->all();
    }

    /**
     * Team-wide KPI summary (AHT, ACW, ASA, Occupancy, Abandon Rate, FCR) across a set of CRM
     * agent IDs, sourced from v_agent_kpi_summary (per-week grain — aggregated here from its raw
     * sums rather than averaging its per-week percentages, since that would be too imprecise).
     */
    private function getKpiMetricsSummary(array $agentIds, string $startDate, string $endDate): array
    {
        $agentIds = array_values(array_unique(array_filter($agentIds)));

        if (empty($agentIds)) {
            return $this->emptyKpiMetrics();
        }

        $rows = DB::table('v_agent_kpi_summary')
            ->whereIn('agent_id', $agentIds)
            ->whereBetween('week_start', [$startDate, $endDate])
            ->get();

        return $this->aggregateKpiWeeks($rows);
    }

    /**
     * Same as getKpiMetricsSummary() but grouped per CRM agent_id for per-agent breakdowns.
     */
    private function getKpiMetricsByAgentId(array $agentIds, string $startDate, string $endDate): array
    {
        $agentIds = array_values(array_unique(array_filter($agentIds)));

        if (empty($agentIds)) {
            return [];
        }

        $rows = DB::table('v_agent_kpi_summary')
            ->whereIn('agent_id', $agentIds)
            ->whereBetween('week_start', [$startDate, $endDate])
            ->get()
            ->groupBy('agent_id');

        $result = [];
        foreach ($rows as $agentId => $weeks) {
            $result[$agentId] = $this->aggregateKpiWeeks($weeks);
        }

        return $result;
    }

    /**
     * Collapses a collection of v_agent_kpi_summary week-rows into one summary, re-deriving rates
     * from raw sums rather than averaging the view's per-week percentages.
     */
    private function aggregateKpiWeeks($rows): array
    {
        if ($rows->isEmpty()) {
            return $this->emptyKpiMetrics();
        }

        $totalCalls = (int) $rows->sum('total_calls');
        $callSeconds = (float) $rows->sum('acd_talk_hours') * 3600;
        $acwSeconds = (float) $rows->sum('acw_hours') * 3600;
        $shiftSeconds = (float) $rows->sum('shift_hours') * 3600;
        $abandonedCalls = (int) $rows->sum('abandoned_calls');
        $resolvedTickets = (int) $rows->sum('resolved_tickets');
        $fcrTickets = (int) $rows->sum('fcr_tickets');

        $hasCallData = $rows->contains(fn ($r) => (bool) $r->has_call_data);
        $hasAbandonData = $rows->contains(fn ($r) => (bool) $r->has_abandon_data);

        // Simple average of per-week ASA values (not call-volume-weighted): ASA comes from a
        // different source table (call-detail records) than total_calls (agent-event records),
        // so the two don't reliably correlate week-to-week — weighting by total_calls would
        // silently discard real ASA data on weeks where that other table happens to show 0 calls.
        $asaRows = $rows->pluck('avg_speed_of_answer_sec')->filter(fn ($v) => $v !== null);

        return [
            'has_call_data' => $hasCallData,
            'total_calls' => $totalCalls,
            'talk_time_minutes' => round($callSeconds / 60, 2),
            'avg_handle_time_min' => $totalCalls > 0 ? round(($callSeconds + $acwSeconds) / $totalCalls / 60, 2) : null,
            'avg_acw_sec' => $totalCalls > 0 ? round($acwSeconds / $totalCalls, 2) : null,
            'avg_speed_of_answer_sec' => $asaRows->isNotEmpty() ? round($asaRows->avg(), 2) : null,
            'occupancy_rate_pct' => $shiftSeconds > 0 ? round(($callSeconds + $acwSeconds) * 100 / $shiftSeconds, 2) : null,
            'has_abandon_data' => $hasAbandonData,
            'abandoned_calls' => $abandonedCalls,
            'abandon_rate_pct' => ($hasAbandonData && ($totalCalls + $abandonedCalls) > 0)
                ? round($abandonedCalls * 100 / ($totalCalls + $abandonedCalls), 2)
                : null,
            'fcr_resolved_tickets' => $resolvedTickets,
            'fcr_tickets' => $fcrTickets,
            'fcr_rate_pct' => $resolvedTickets > 0 ? round($fcrTickets * 100 / $resolvedTickets, 2) : null,
        ];
    }

    private function emptyKpiMetrics(): array
    {
        return [
            'has_call_data' => false,
            'total_calls' => 0,
            'talk_time_minutes' => 0,
            'avg_handle_time_min' => null,
            'avg_acw_sec' => null,
            'avg_speed_of_answer_sec' => null,
            'occupancy_rate_pct' => null,
            'has_abandon_data' => false,
            'abandoned_calls' => 0,
            'abandon_rate_pct' => null,
            'fcr_resolved_tickets' => 0,
            'fcr_tickets' => 0,
            'fcr_rate_pct' => null,
        ];
    }
}
