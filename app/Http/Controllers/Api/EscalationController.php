<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Escalation;
use App\Models\Ticket;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class EscalationController extends Controller
{
    /**
     * Display a listing of escalations.
     */
    public function index(Request $request)
    {
        $query = Escalation::with(['ticket', 'fromAgent', 'toAgent', 'escalatedBy']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by escalation level
        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        // Filter by ticket
        if ($request->has('ticket_id')) {
            $query->where('ticket_id', $request->ticket_id);
        }

        // Filter by agent (from or to)
        if ($request->has('agent_id')) {
            $agentId = $request->agent_id;
            $query->where(function ($q) use ($agentId) {
                $q->where('from_agent_id', $agentId)
                    ->orWhere('to_agent_id', $agentId);
            });
        }

        // Filter by escalation type
        if ($request->has('escalation_type')) {
            $query->where('escalation_type', $request->escalation_type);
        }

        $perPage = $request->get('per_page', 20);
        $escalations = $query->orderBy('escalated_at', 'desc')->paginate($perPage);

        return response()->json($escalations);
    }

    /**
     * Store a newly created escalation.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ticket_id' => 'required|exists:tickets,id',
            'to_agent_id' => 'required|exists:agents,id',
            'level' => 'required|in:L1,L2,Supervisor,Manager',
            'reason' => 'required|string',
            'notes' => 'nullable|string',
            'escalation_type' => 'required|in:manual,auto,sla_risk',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $ticket = Ticket::findOrFail($request->ticket_id);

            $escalation = Escalation::create([
                'ticket_id' => $request->ticket_id,
                'from_agent_id' => $ticket->assigned_to,
                'to_agent_id' => $request->to_agent_id,
                'escalated_by' => auth()->id(),
                'level' => $request->level,
                'reason' => $request->reason,
                'notes' => $request->notes,
                'escalation_type' => $request->escalation_type,
                'status' => 'pending',
                'escalated_at' => now(),
            ]);

            // Update ticket assignment
            $ticket->assigned_to = $request->to_agent_id;
            $ticket->save();

            // Log history
            $ticket->logHistory(
                'escalated',
                'assigned_to',
                $escalation->from_agent_id,
                $escalation->to_agent_id,
                "Ticket escalated to {$request->level}: {$request->reason}"
            );

            DB::commit();

            $escalation->load(['ticket', 'fromAgent', 'toAgent']);

            return response()->json([
                'success' => true,
                'message' => 'Ticket escalated successfully',
                'data' => $escalation
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to escalate ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified escalation.
     */
    public function show($id)
    {
        $escalation = Escalation::with(['ticket', 'fromAgent', 'toAgent', 'escalatedBy'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $escalation
        ]);
    }

    /**
     * Acknowledge an escalation.
     */
    public function acknowledge($id)
    {
        $escalation = Escalation::findOrFail($id);

        if ($escalation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Escalation has already been acknowledged'
            ], 422);
        }

        $escalation->acknowledge();

        // Log history
        $escalation->ticket->logHistory(
            'escalation_acknowledged',
            null,
            null,
            null,
            "Escalation acknowledged by agent"
        );

        return response()->json([
            'success' => true,
            'message' => 'Escalation acknowledged successfully',
            'data' => $escalation
        ]);
    }

    /**
     * Mark escalation as resolved.
     */
    public function resolve($id)
    {
        $escalation = Escalation::findOrFail($id);

        if ($escalation->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Escalation already resolved'
            ], 422);
        }

        $escalation->markResolved();

        // Log history
        $escalation->ticket->logHistory(
            'escalation_resolved',
            null,
            null,
            null,
            "Escalation resolved"
        );

        return response()->json([
            'success' => true,
            'message' => 'Escalation resolved successfully',
            'data' => $escalation
        ]);
    }

    /**
     * Get escalation statistics.
     */
    public function statistics(Request $request)
    {
        $query = Escalation::query();

        // Date range filter
        if ($request->has('start_date')) {
            $query->where('escalated_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('escalated_at', '<=', $request->end_date);
        }

        $total = $query->count();
        $pending = (clone $query)->pending()->count();
        $acknowledged = (clone $query)->acknowledged()->count();
        $completed = (clone $query)->completed()->count();

        // Count by level
        $byLevel = (clone $query)
            ->select('level', DB::raw('count(*) as count'))
            ->groupBy('level')
            ->get();

        // Count by type
        $byType = (clone $query)
            ->select('escalation_type', DB::raw('count(*) as count'))
            ->groupBy('escalation_type')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'pending' => $pending,
                'acknowledged' => $acknowledged,
                'completed' => $completed,
                'by_level' => $byLevel,
                'by_type' => $byType,
            ]
        ]);
    }

    /**
     * Check if ticket needs auto-escalation.
     */
    public function checkAutoEscalation($ticketId)
    {
        $ticket = Ticket::with(['assignedAgent'])->findOrFail($ticketId);

        // Check if SLA is at risk (e.g., 80% consumed)
        $slaRiskThreshold = 80;
        $slaConsumed = 0;

        if ($ticket->sla_duration_minutes > 0) {
            $elapsed = now()->diffInMinutes($ticket->created_at);
            $slaConsumed = ($elapsed / $ticket->sla_duration_minutes) * 100;
        }

        $needsEscalation = $slaConsumed >= $slaRiskThreshold && !$ticket->sla_breached;

        return response()->json([
            'success' => true,
            'data' => [
                'needs_escalation' => $needsEscalation,
                'sla_consumed_percent' => round($slaConsumed, 2),
                'reason' => $needsEscalation ? 'SLA at risk - ' . round($slaConsumed, 2) . '% consumed' : null
            ]
        ]);
    }
}
