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
                'avg_resolution_time_minutes' => round($tickets->whereNotNull('resolution_time_minutes')->avg('resolution_time_minutes'), 2),
                'avg_first_response_time_minutes' => round($tickets->whereNotNull('first_response_time_minutes')->avg('first_response_time_minutes'), 2),
                'customer_satisfaction' => round($tickets->whereNotNull('customer_rating')->avg('customer_rating'), 2),
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
            'department' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Get all tickets for department in date range
        $tickets = Ticket::where('department', $request->department)
            ->whereBetween('created_at', [$request->start_date, $request->end_date])
            ->with(['customer', 'assignedAgent.user'])
            ->get();

        // Get agents in department
        $agents = Agent::where('department', $request->department)->with('user')->get();

        // Calculate team metrics
        $metrics = [
            'department' => $request->department,
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
                'avg_resolution_time_minutes' => round($tickets->whereNotNull('resolution_time_minutes')->avg('resolution_time_minutes'), 2),
                'customer_satisfaction' => round($tickets->whereNotNull('customer_rating')->avg('customer_rating'), 2),
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
            'agent_performance' => $agents->map(function ($agent) use ($tickets) {
                $agentTickets = $tickets->where('assigned_to', $agent->id);
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
                    'avg_resolution_time_minutes' => round($agentTickets->whereNotNull('resolution_time_minutes')->avg('resolution_time_minutes'), 2),
                    'csat' => round($agentTickets->whereNotNull('customer_rating')->avg('customer_rating'), 2),
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
            'group_by' => 'nullable|in:day,week,month',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Ticket::whereBetween('created_at', [$request->start_date, $request->end_date]);

        if ($request->has('department')) {
            $query->where('department', $request->department);
        }

        $groupBy = $request->get('group_by', 'day');
        $dateFormat = match($groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $volumeByDate = Ticket::selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as date, COUNT(*) as count")
            ->whereBetween('created_at', [$request->start_date, $request->end_date])
            ->when($request->has('department'), function ($q) use ($request) {
                $q->where('department', $request->department);
            })
            ->groupBy('date')
            ->orderBy('date')
            ->get();

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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Ticket::whereBetween('created_at', [$request->start_date, $request->end_date]);

        if ($request->has('department')) {
            $query->where('department', $request->department);
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

        if ($request->has('department')) {
            $query->where('department', $request->department);
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
                'average_rating' => round($tickets->avg('customer_rating'), 2),
                'rating_distribution' => $ratingDistribution,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics
        ]);
    }
}
