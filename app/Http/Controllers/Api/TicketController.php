<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Agent;
use App\Models\TicketHistory;
use App\Models\TicketNumberSequence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets.
     */
    public function index(Request $request)
    {
        $query = Ticket::with(['customer', 'assignedAgent', 'creator', 'histories' => function($q) {
            $q->with('user')->latest()->take(5);
        }]);

        // Filter by priority
        if ($request->has('priority')) {
            $query->byPriority($request->priority);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by department
        if ($request->has('department')) {
            $query->byDepartment($request->department);
        }

        // Filter by assigned agent
        if ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by SLA status
        if ($request->has('sla_breached')) {
            if ($request->sla_breached === 'true' || $request->sla_breached === '1') {
                $query->slaBreached();
            }
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by channel
        if ($request->has('channel')) {
            $query->where('channel', $request->channel);
        }

        // Search by ticket number or subject
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $tickets = $query->paginate($perPage);

        // Add computed attributes
        $tickets->getCollection()->transform(function ($ticket) {
            $data = $ticket->toArray();
            $data['formatted_sla_remaining'] = $ticket->formatted_sla_remaining;
            $data['is_overdue'] = $ticket->is_overdue;
            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => $tickets,
        ]);
    }

    /**
     * Store a newly created ticket.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'assigned_to' => 'nullable|exists:agents,id',
            'priority' => 'required|in:P1,P2,P3,P4',
            'department' => 'required|string|max:100',
            'category' => 'required|string|max:100',
            'sub_category' => 'nullable|string|max:100',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'urgency' => 'nullable|in:Low,Medium,High,Critical',
            'impact' => 'nullable|in:Low,Medium,High,Critical',
            'channel' => 'required|in:phone,email,chat,web,walk-in',
            'source' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'custom_fields' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();
            
            // Generate unique ticket number
            $data['ticket_number'] = $this->generateTicketNumber();
            
            // Set created_by to authenticated user's agent ID
            if (auth()->check() && auth()->user()->agent) {
                $data['created_by'] = auth()->user()->agent->id;
            }

            // Auto-assign if not provided
            if (!isset($data['assigned_to']) || !$data['assigned_to']) {
                $data['assigned_to'] = $this->autoAssignAgent($data['department']);
            }

            $ticket = Ticket::create($data);
            
            // Log ticket creation
            $ticket->logHistory(
                'created',
                null,
                null,
                null,
                "Ticket created by " . (auth()->user()->name ?? 'System'),
                [
                    'priority' => $ticket->priority,
                    'department' => $ticket->department,
                    'category' => $ticket->category,
                    'channel' => $ticket->channel,
                ]
            );
            
            // Log assignment if assigned
            if ($ticket->assigned_to) {
                $agent = Agent::with('user')->find($ticket->assigned_to);
                $ticket->logHistory(
                    'assigned',
                    'assigned_to',
                    null,
                    $agent->user->name ?? 'Unknown',
                    "Ticket assigned to " . ($agent->user->name ?? 'agent'),
                    ['agent_id' => $ticket->assigned_to]
                );
            }
            
            // Calculate SLA
            $ticket->calculateSla();
            $ticket->save();

            DB::commit();

            $ticket->load(['customer', 'assignedAgent', 'creator']);

            return response()->json([
                'success' => true,
                'message' => 'Ticket created successfully',
                'data' => $ticket,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified ticket.
     */
    public function show(string $id)
    {
        $ticket = Ticket::with(['customer', 'assignedAgent', 'creator', 'histories' => function($q) {
            $q->with('user')->latest();
        }])->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        // Update SLA remaining time
        $ticket->updateSlaRemaining();
        $ticket->save();

        // Add computed attributes
        $ticketData = $ticket->toArray();
        $ticketData['formatted_sla_remaining'] = $ticket->formatted_sla_remaining;
        $ticketData['is_overdue'] = $ticket->is_overdue;

        return response()->json([
            'success' => true,
            'data' => $ticketData,
        ]);
    }

    /**
     * Update the specified ticket.
     */
    public function update(Request $request, string $id)
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'assigned_to' => 'nullable|exists:agents,id',
            'priority' => 'sometimes|in:P1,P2,P3,P4',
            'department' => 'sometimes|string|max:100',
            'category' => 'sometimes|string|max:100',
            'sub_category' => 'nullable|string|max:100',
            'subject' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'urgency' => 'nullable|in:Low,Medium,High,Critical',
            'impact' => 'nullable|in:Low,Medium,High,Critical',
            'status' => 'sometimes|in:Open,In Progress,Pending,On Hold,Resolved,Closed,Cancelled',
            'tags' => 'nullable|array',
            'custom_fields' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $validator->validated();

            // Track changes for history
            $changes = [];
            foreach ($data as $field => $newValue) {
                if ($ticket->{$field} != $newValue) {
                    $changes[$field] = [
                        'old' => $ticket->{$field},
                        'new' => $newValue
                    ];
                }
            }

            // Recalculate SLA if priority changed
            if (isset($data['priority']) && $data['priority'] !== $ticket->priority) {
                $ticket->priority = $data['priority'];
                $ticket->calculateSla();
                unset($data['priority']); // Remove from update array as it's already set
            }

            $ticket->update($data);
            
            // Log all changes
            foreach ($changes as $field => $change) {
                $ticket->logHistory(
                    'updated',
                    $field,
                    $change['old'],
                    $change['new'],
                    "Field '{$field}' updated from '{$change['old']}' to '{$change['new']}'"
                );
            }
            
            $ticket->updateSlaRemaining();
            $ticket->save();

            $ticket->load(['customer', 'assignedAgent', 'creator']);

            return response()->json([
                'success' => true,
                'message' => 'Ticket updated successfully',
                'data' => $ticket,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified ticket.
     */
    public function destroy(string $id)
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        try {
            $ticket->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ticket deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign ticket to an agent.
     */
    public function assign(Request $request, string $id)
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'agent_id' => 'required|exists:agents,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $oldAssignee = $ticket->assigned_to ? Agent::with('user')->find($ticket->assigned_to) : null;
            $newAssignee = Agent::with('user')->find($request->agent_id);
            
            $ticket->assigned_to = $request->agent_id;
            
            // If ticket is Open, change to In Progress
            if ($ticket->status === 'Open') {
                $ticket->status = 'In Progress';
            }
            
            $ticket->save();
            
            // Log assignment change
            $ticket->logHistory(
                'assigned',
                'assigned_to',
                $oldAssignee ? $oldAssignee->user->name : 'Unassigned',
                $newAssignee->user->name ?? 'Unknown',
                "Ticket reassigned from " . ($oldAssignee ? $oldAssignee->user->name : 'Unassigned') . " to " . ($newAssignee->user->name ?? 'agent'),
                [
                    'old_agent_id' => $oldAssignee?->id,
                    'new_agent_id' => $newAssignee->id
                ]
            );
            
            $ticket->load(['assignedAgent']);

            return response()->json([
                'success' => true,
                'message' => 'Ticket assigned successfully',
                'data' => $ticket,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update ticket status.
     */
    public function updateStatus(Request $request, string $id)
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Open,In Progress,Pending,On Hold,Resolved,Closed,Cancelled',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $oldStatus = $ticket->status;
            $ticket->status = $request->status;
            
            if ($request->has('notes')) {
                $ticket->resolution_notes = $request->notes;
            }
            
            $ticket->save();
            
            // Log status change
            $ticket->logHistory(
                'status_changed',
                'status',
                $oldStatus,
                $request->status,
                "Status changed from '{$oldStatus}' to '{$request->status}'",
                $request->has('notes') ? ['notes' => $request->notes] : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Ticket status updated successfully',
                'data' => $ticket,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve a ticket.
     */
    public function resolve(Request $request, string $id)
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'resolution_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $ticket->resolve($request->resolution_notes);
            $ticket->load(['customer', 'assignedAgent']);

            return response()->json([
                'success' => true,
                'message' => 'Ticket resolved successfully',
                'data' => $ticket,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Close a ticket.
     */
    public function close(string $id)
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        try {
            $ticket->close();
            $ticket->load(['customer', 'assignedAgent']);

            return response()->json([
                'success' => true,
                'message' => 'Ticket closed successfully',
                'data' => $ticket,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to close ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reopen a ticket.
     */
    public function reopen(string $id)
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        try {
            $ticket->reopen();
            $ticket->load(['customer', 'assignedAgent']);

            return response()->json([
                'success' => true,
                'message' => 'Ticket reopened successfully',
                'data' => $ticket,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reopen ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Record first response to a ticket.
     */
    public function recordResponse(string $id)
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        try {
            $ticket->recordFirstResponse();
            $ticket->save();

            return response()->json([
                'success' => true,
                'message' => 'First response recorded successfully',
                'data' => $ticket,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record response',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get ticket statistics.
     */
    public function statistics(Request $request)
    {
        $query = Ticket::query();

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        // Filter by department
        if ($request->has('department')) {
            $query->where('department', $request->department);
        }

        $total = $query->count();
        $open = (clone $query)->open()->count();
        $resolved = (clone $query)->resolved()->count();
        $closed = (clone $query)->where('status', 'Closed')->count();
        $slaBreached = (clone $query)->slaBreached()->count();

        // Priority breakdown
        $byPriority = [
            'P1' => (clone $query)->byPriority('P1')->count(),
            'P2' => (clone $query)->byPriority('P2')->count(),
            'P3' => (clone $query)->byPriority('P3')->count(),
            'P4' => (clone $query)->byPriority('P4')->count(),
        ];

        // Average resolution time
        $avgResolutionTime = (clone $query)
            ->whereNotNull('resolution_time_minutes')
            ->avg('resolution_time_minutes');

        // SLA compliance rate
        $slaCompliance = $total > 0 ? (($total - $slaBreached) / $total) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_tickets' => $total,
                'open_tickets' => $open,
                'resolved_tickets' => $resolved,
                'closed_tickets' => $closed,
                'sla_breached' => $slaBreached,
                'sla_compliance_rate' => round($slaCompliance, 2),
                'by_priority' => $byPriority,
                'avg_resolution_time_minutes' => round($avgResolutionTime ?? 0, 2),
            ],
        ]);
    }

    /**
     * Generate unique ticket number using configurable sequence.
     * 
     * @param string $sequenceName The sequence to use (default, urgent, vip, etc.)
     * @return string
     */
    private function generateTicketNumber(string $sequenceName = 'default'): string
    {
        try {
            return TicketNumberSequence::generateNextNumber($sequenceName);
        } catch (\Exception $e) {
            // Fallback to old method if sequence generation fails
            \Log::error('Ticket number generation failed: ' . $e->getMessage());
            
            do {
                $number = 'TKT' . date('Ymd') . strtoupper(Str::random(6));
            } while (Ticket::where('ticket_number', $number)->exists());

            return $number;
        }
    }

    /**
     * Auto-assign agent based on department and availability.
     */
    private function autoAssignAgent(string $department): ?int
    {
        $agent = Agent::where('department', $department)
            ->where('availability', 'available')
            ->where('status', 'active')
            ->orderBy('tickets_resolved_today', 'asc')
            ->first();

        return $agent?->id;
    }
}
