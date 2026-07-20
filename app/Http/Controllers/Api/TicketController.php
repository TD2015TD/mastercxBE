<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\McxOpenMediaService;
use App\Models\Agent;
use App\Models\TicketHistory;
use App\Models\TicketNumberSequence;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Models\EmailMessage;
use App\Services\AuditLogger;
use App\Notifications\TicketCreatedNotification;
use App\Events\TicketNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets.
     */
    public function index(Request $request)
    {
        $query = Ticket::with(['customer.contacts', 'assignedAgent.user', 'creator', 'histories' => function($q) {
            $q->with('user')->latest()->take(5);
        }]);

        // Filter by priority
        if ($request->filled('priority')) {
            $query->byPriority($request->priority);
        }

        // Filter by status (supports comma-separated values)
        if ($request->filled('status')) {
            $statuses = is_array($request->status)
                ? $request->status
                : explode(',', $request->status);
            $query->whereIn('status', $statuses);
        }

        // Filter by department
        if ($request->filled('department')) {
            $query->byDepartment($request->department);
        }

        // Filter by assigned agent
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        // Filter for unassigned tickets
        if ($request->filled('unassigned') && $request->unassigned) {
            $query->whereNull('assigned_to');
        }

        // Filter by customer
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by SLA status
        if ($request->filled('sla_breached')) {
            if ($request->sla_breached === 'true' || $request->sla_breached === '1') {
                $query->slaBreached();
            }
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter by channel
        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        // Filter by conversation_id
        if ($request->filled('conversation_id')) {
            $query->where('conversation_id', $request->conversation_id);
        }

        // Filter by date range (created_at) — supports both date and datetime-local values
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        // Search by ticket number, call_id, case_id, subject, or customer name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('call_id', 'like', "%{$search}%")
                  ->orWhere('case_id', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
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
            'conversation_id' => 'nullable|string|max:100',
            'call_id' => 'nullable|string|max:100',
            'case_id' => 'nullable|string|max:100',
            'assigned_to' => 'nullable|exists:agents,id',
            'priority' => 'nullable|in:P1,P2,P3,P4',
            'department' => 'required|string|max:100',
            'category' => 'required|string|max:100',
            'sub_category' => 'required|string|max:100',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'urgency' => 'nullable|in:Low,Medium,High,Critical',
            'impact' => 'nullable|in:Low,Medium,High,Critical',
            'channel' => 'required|in:phone,email,chat,web,walk-in',
            'source' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'custom_fields' => 'nullable|array',
            'ticket_number' => 'nullable|string|max:50|unique:tickets,ticket_number',
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

            // Default priority if not provided
            $data['priority'] = $data['priority'] ?? 'P3';

            // Auto-close ticket immediately upon creation
            $data['status'] = 'Closed';
            $data['closed_at'] = now()->toDateTimeString();

            // Use provided ticket number if available (from reservation), otherwise generate new one
            if (!isset($data['ticket_number']) || empty($data['ticket_number'])) {
                // Generate unique ticket number based on channel
                // Map channel to sequence name ('walk-in' -> 'walkIn')
                $sequenceName = $data['channel'] === 'walk-in' ? 'walkIn' : $data['channel'];
                $data['ticket_number'] = $this->generateTicketNumber($sequenceName);
            }
            
            // Set created_by to authenticated user's agent ID
            if (auth()->check() && auth()->user()->agent) {
                $data['created_by'] = auth()->user()->agent->id;
            }

            // Auto-assign if not provided
            if (!isset($data['assigned_to']) || !$data['assigned_to']) {
                // Prefer the authenticated user's own agent (agents creating their own tickets)
                if (auth()->check() && auth()->user()->agent && auth()->user()->agent->status === 'active') {
                    $data['assigned_to'] = auth()->user()->agent->id;
                } else {
                    $data['assigned_to'] = $this->autoAssignAgent($data['department']);
                }
            }

            // Always ensure email tickets have a case_id at creation time
            if (($data['channel'] ?? '') === 'email' && empty($data['case_id'])) {
                $data['case_id'] = (string) \Illuminate\Support\Str::uuid();
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

            // Log auto-close on creation
            $ticket->logHistory(
                'status_changed',
                'status',
                'Open',
                'Closed',
                'Ticket automatically closed upon creation'
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

            // Update customer metrics (ticket counts)
            if ($ticket->customer) {
                $ticket->customer->updateMetrics();
            }

            AuditLogger::info(
                'Ticket Created',
                'Ticket Management',
                'Ticket Management',
                "Created ticket {$ticket->ticket_number} — Status: {$ticket->status}",
                ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'status' => $ticket->status, 'priority' => $ticket->priority]
            );

            DB::commit();

            $ticket->load(['customer', 'assignedAgent.user', 'creator']);

            // Dispatch notifications asynchronously (after commit to ensure ticket exists)
            $this->dispatchTicketNotifications($ticket, 'created');

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
        $ticket = Ticket::with(['customer', 'assignedAgent.user', 'creator', 'histories' => function($q) {
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

            if (!empty($changes)) {
                $fieldsChanged = implode(', ', array_keys($changes));
                $details = isset($changes['status'])
                    ? "Ticket {$ticket->ticket_number} status changed from '{$changes['status']['old']}' to '{$changes['status']['new']}'"
                    : "Ticket {$ticket->ticket_number} updated — fields changed: {$fieldsChanged}";

                AuditLogger::info(
                    'Ticket Updated',
                    'Ticket Management',
                    'Ticket Management',
                    $details,
                    ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'status' => $ticket->status, 'changes' => $changes]
                );
            }

            $ticket->load(['customer', 'assignedAgent.user', 'creator']);

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

            $ticket->load(['assignedAgent.user']);

            // Dispatch assignment notifications asynchronously
            $this->dispatchTicketNotifications($ticket, 'assigned');

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

            // Update customer metrics when status changes to/from Resolved or Closed
            if ($ticket->customer &&
                (in_array($request->status, ['Resolved', 'Closed']) || in_array($oldStatus, ['Resolved', 'Closed']))) {
                $ticket->customer->updateMetrics();
            }

            AuditLogger::info(
                'Ticket Status Changed',
                'Ticket Management',
                'Ticket Management',
                "Ticket {$ticket->ticket_number} status changed from '{$oldStatus}' to '{$request->status}'",
                ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'old_status' => $oldStatus, 'new_status' => $request->status]
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

            AuditLogger::info(
                'Ticket Resolved',
                'Ticket Management',
                'Ticket Management',
                "Ticket {$ticket->ticket_number} marked as Resolved",
                ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'status' => $ticket->status]
            );

            $ticket->load(['customer', 'assignedAgent.user']);

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
    /**
     * Close via sendBeacon — accepts JWT in request body (_jwt field) because
     * sendBeacon cannot set custom headers. Falls back to the normal auth guard
     * if the request arrives with an Authorization header instead.
     */
    public function closeBeacon(Request $request, string $id): JsonResponse
    {
        // Authenticate from body._jwt (sendBeacon) or Authorization header (normal)
        $jwt = $request->input('_jwt');
        if ($jwt) {
            try {
                $user = \Tymon\JWTAuth\Facades\JWTAuth::setToken($jwt)->toUser();
                if (!$user) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
                }
            } catch (\Throwable) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }
        } else {
            try {
                $user = \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->toUser();
                if (!$user) {
                    return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
                }
            } catch (\Throwable) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }
        }

        $ticket = Ticket::find($id);
        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }

        if (in_array($ticket->status, ['Closed', 'Resolved', 'Cancelled'])) {
            return response()->json(['success' => true, 'message' => 'Already closed']);
        }

        try {
            $ticket->close();
            Log::info('[closeBeacon] Ticket closed via page unload beacon', [
                'ticket_number' => $ticket->ticket_number,
            ]);
            AuditLogger::info(
                'Ticket Closed',
                'Ticket Management',
                'Ticket Management',
                "Ticket {$ticket->ticket_number} closed (page unload)",
                ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'status' => $ticket->status]
            );
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

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

            AuditLogger::info(
                'Ticket Closed',
                'Ticket Management',
                'Ticket Management',
                "Ticket {$ticket->ticket_number} closed",
                ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'status' => $ticket->status]
            );

            $ticket->load(['customer', 'assignedAgent.user']);

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
     * Close a ticket by its Mitel interaction ID (called by SignalR hangup event).
     */
    public function closeByMitelId(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'mitel_interaction_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $ticket = Ticket::where('mitel_interaction_id', $request->mitel_interaction_id)
            ->whereNotIn('status', ['Closed', 'Resolved', 'Cancelled'])
            ->first();

        if (!$ticket) {
            return response()->json(['success' => true, 'message' => 'No open ticket found for this interaction']);
        }

        try {
            $ticket->close();
            AuditLogger::info(
                'Ticket Closed',
                'Ticket Management',
                'Ticket Management',
                "Ticket {$ticket->ticket_number} closed (Mitel hangup)",
                ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'status' => $ticket->status]
            );
            return response()->json(['success' => true, 'message' => 'Ticket closed', 'data' => ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to close ticket', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Called by the email iframe every ~5 s to detect agent hangup without SignalR.
     * Checks the Mitel MCX interaction state; if it's gone/Handled the ticket is closed
     * immediately and the response tells the iframe to refresh.
     */
    public function mitelPing(Request $request, string $id): JsonResponse
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        }

        if (in_array($ticket->status, ['Closed', 'Resolved', 'Cancelled'])) {
            return response()->json(['success' => true, 'closed' => true, 'status' => $ticket->status]);
        }

        if (!$ticket->mitel_interaction_id) {
            return response()->json(['success' => true, 'closed' => false, 'status' => $ticket->status]);
        }

        try {
            $mcx   = app(McxOpenMediaService::class);
            $state = $mcx->getInteractionState($ticket->mitel_interaction_id);

            if ($state === null || $state === 'Handled') {
                $ticket->close();
                Log::info('[mitelPing] Ticket auto-closed via iframe poll', [
                    'ticket_number' => $ticket->ticket_number,
                    'mitel_id'      => $ticket->mitel_interaction_id,
                    'state'         => $state ?? '404',
                ]);
                AuditLogger::info(
                    'Ticket Closed',
                    'Ticket Management',
                    'Ticket Management',
                    "Ticket {$ticket->ticket_number} closed (Mitel call ended)",
                    ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'status' => $ticket->status]
                );
                return response()->json(['success' => true, 'closed' => true, 'status' => 'Closed']);
            }

            return response()->json(['success' => true, 'closed' => false, 'status' => $ticket->status]);
        } catch (\Throwable $e) {
            Log::warning('[mitelPing] MCX check failed', ['ticket' => $ticket->ticket_number, 'error' => $e->getMessage()]);
            return response()->json(['success' => true, 'closed' => false, 'status' => $ticket->status]);
        }
    }

    /**
     * End the agent-side Mitel conversation (employee conversations endpoint).
     * Called from the email-interaction iframe when the agent closes/replies.
     * The frontend passes its own mitel_token (agent JWT) because this endpoint
     * requires the agent's identity — the backend's client-credentials token won't work.
     */
    public function mitelEndConversation(Request $request, string $id): JsonResponse
    {
        $ticket = Ticket::find($id);
        if (!$ticket || !$ticket->mitel_interaction_id) {
            return response()->json(['success' => false, 'message' => 'No interaction to end'], 404);
        }

        $agentToken = $request->input('mitel_token');
        if (!$agentToken) {
            return response()->json(['success' => false, 'message' => 'mitel_token required'], 422);
        }

        try {
            $baseUrl       = \App\Models\MitelConfiguration::getValue('MITEL_BASE_URL', '');
            $conversationId = $ticket->mitel_interaction_id;
            $url = rtrim($baseUrl, '/') . "/MiccSdk/api/v1/employees/me/conversations/{$conversationId}";

            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withToken($agentToken)
                ->withHeaders(['Accept' => 'application/json'])
                ->put($url, [
                    'conversationAction' => 'End',
                    'conversationId'     => $conversationId,
                ]);

            Log::info('[mitelEndConversation] Employee conversation end', [
                'ticket'        => $ticket->ticket_number,
                'mitel_id'      => $conversationId,
                'status'        => $response->status(),
                'body'          => $response->body(),
            ]);

            return response()->json(['success' => true, 'status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::warning('[mitelEndConversation] Failed', ['ticket' => $ticket->ticket_number, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
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

            AuditLogger::info(
                'Ticket Reopened',
                'Ticket Management',
                'Ticket Management',
                "Ticket {$ticket->ticket_number} reopened",
                ['ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'status' => $ticket->status]
            );

            $ticket->load(['customer', 'assignedAgent.user']);

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
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [$request->start_date, $request->end_date]);
        }

        // Filter by department
        if ($request->filled('department')) {
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
     * Get ticket history.
     */
    public function history(string $id)
    {
        $ticket = Ticket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $histories = TicketHistory::where('ticket_id', $id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $histories,
        ]);
    }

    /**
     * Generate unique ticket number using configurable sequence.
     * 
     * @param string $sequenceName The sequence to use (default, urgent, vip, etc.)
     * @return string
     */
    /**
     * Reserve a ticket number based on case_id and phone_number to guarantee uniqueness.
     * This endpoint is called when the form is loaded with case_id and phone_number.
     * If a ticket with the same case_id already exists, return that ticket's info for redirect.
     * Otherwise, generate a NEW unique ticket number.
     */
    public function reserveTicketNumber(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'case_id' => 'nullable|string|max:100',
            'conversation_id' => 'nullable|string|max:100',
            'phone_number' => 'required|string|max:50',
            'channel' => 'nullable|in:phone,email,chat,web,walk-in',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // At least one identifier (case_id or conversation_id) must be provided
        if (!$request->case_id && !$request->conversation_id) {
            return response()->json([
                'success' => false,
                'message' => 'Either case_id or conversation_id must be provided',
            ], 422);
        }

        try {
            $caseId = $request->case_id;
            $conversationId = $request->conversation_id;
            $channel = $request->channel ?? 'phone';

            // Use database transaction with row locking to prevent race conditions
            return DB::transaction(function () use ($caseId, $conversationId, $channel) {
                // Check if a ticket with this case_id OR conversation_id already exists
                // Using OR logic - finds ticket if EITHER identifier matches
                // Use lockForUpdate to prevent race conditions during simultaneous calls
                $existingTicket = Ticket::where(function($query) use ($caseId, $conversationId) {
                    if ($caseId) {
                        $query->where('case_id', $caseId);
                    }
                    if ($conversationId) {
                        if ($caseId) {
                            $query->orWhere('conversation_id', $conversationId);
                        } else {
                            $query->where('conversation_id', $conversationId);
                        }
                    }
                })
                ->lockForUpdate()
                ->first();

                if ($existingTicket) {
                    // Return existing ticket info so frontend can redirect
                    return response()->json([
                        'success' => true,
                        'data' => [
                            'ticket_number' => $existingTicket->ticket_number,
                            'ticket_id' => $existingTicket->id,
                            'exists' => true,
                        ],
                    ]);
                }

                // No existing ticket - generate a new ticket number
                // The uniqueness is guaranteed by the sequence generation mechanism
                $sequenceName = $channel === 'walk-in' ? 'walkIn' : $channel;
                $ticketNumber = $this->generateTicketNumber($sequenceName);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'ticket_number' => $ticketNumber,
                        'exists' => false,
                    ],
                ]);
            });
        } catch (\Exception $e) {
            \Log::error('Error reserving ticket number: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to reserve ticket number',
            ], 500);
        }
    }

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
        // Try to find agent in the same department first
        $agent = Agent::where('department', $department)
            ->where('availability', 'available')
            ->where('status', 'active')
            ->first();

        // If no agent found in department, fallback to any available agent
        if (!$agent) {
            $agent = Agent::where('availability', 'available')
                ->where('status', 'active')
                ->first();
        }

        return $agent?->id;
    }

    /**
     * Upload attachment to a ticket.
     */
    public function uploadAttachment(Request $request, string $ticketId)
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // Max 10MB
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('ticket_attachments/' . $ticketId, $fileName);

            $attachment = TicketAttachment::create([
                'ticket_id' => $ticketId,
                'uploaded_by' => auth()->id(),
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => TicketAttachment::getFileTypeFromMime($file->getMimeType()),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'description' => $request->description,
            ]);

            // Log attachment upload
            $ticket->logHistory(
                'attachment_added',
                null,
                null,
                $attachment->file_name,
                "File '{$attachment->file_name}' uploaded",
                ['attachment_id' => $attachment->id]
            );

            $attachment->load('uploader');

            return response()->json([
                'success' => true,
                'message' => 'Attachment uploaded successfully',
                'data' => $attachment,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload attachment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all attachments for a ticket.
     */
    public function getAttachments(string $ticketId)
    {
        $ticket = Ticket::find($ticketId);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        }

        $attachments = TicketAttachment::where('ticket_id', $ticketId)
            ->with('uploader')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attachments,
        ]);
    }

    /**
     * Download an attachment.
     */
    public function downloadAttachment(string $ticketId, string $attachmentId)
    {
        $attachment = TicketAttachment::where('ticket_id', $ticketId)
            ->where('id', $attachmentId)
            ->first();

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found',
            ], 404);
        }

        $disk = Storage::disk(config('filesystems.default'));

        if (!$disk->exists($attachment->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found in storage',
            ], 404);
        }

        return response()->download(
            $disk->path($attachment->file_path),
            $attachment->file_name,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    /**
     * Delete an attachment.
     */
    public function deleteAttachment(string $ticketId, string $attachmentId)
    {
        $attachment = TicketAttachment::where('ticket_id', $ticketId)
            ->where('id', $attachmentId)
            ->first();

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found',
            ], 404);
        }

        try {
            $fileName = $attachment->file_name;
            $ticket = $attachment->ticket;
            
            $attachment->delete();

            // Log attachment deletion
            $ticket->logHistory(
                'attachment_deleted',
                null,
                $fileName,
                null,
                "File '{$fileName}' deleted",
                ['attachment_id' => $attachmentId]
            );

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create ticket from email message.
     * This is an internal method used by the email processing service.
     */
    public function createFromEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email_message_id' => 'required|exists:email_messages,id',
            'customer_id' => 'required|exists:customers,id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'nullable|in:P1,P2,P3,P4',
            'department' => 'nullable|string|max:100',
            'assigned_to' => 'nullable|exists:agents,id',
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

            // Set defaults for email-originated tickets
            $data['priority'] = $data['priority'] ?? 'P3';
            $data['department'] = $data['department'] ?? 'Support';
            $data['category'] = 'Email Request';
            $data['channel'] = 'email';
            $data['source'] = 'IMAP';
            $data['urgency'] = $this->mapPriorityToUrgency($data['priority']);
            $data['impact'] = 'Medium';
            // Auto-close on creation
            $data['status'] = 'Closed';
            $data['closed_at'] = now()->toDateTimeString();

            // Generate ticket number
            $data['ticket_number'] = $this->generateTicketNumber('email');

            // Auto-assign if not provided
            if (!isset($data['assigned_to']) || !$data['assigned_to']) {
                $data['assigned_to'] = $this->autoAssignAgent($data['department']);
            }

            // Set case_id from the email message's message_id
            $emailMessageForCase = \App\Models\EmailMessage::find($request->email_message_id);
            if ($emailMessageForCase) {
                $data['case_id'] = $emailMessageForCase->message_id;
            }

            $ticket = Ticket::create($data);

            // Update email message with ticket ID
            $emailMessage = $emailMessageForCase;
            if ($emailMessage) {
                $emailMessage->ticket_id = $ticket->id;
                $emailMessage->markAsProcessed();
            }

            // Log ticket creation
            $ticket->logHistory(
                'created',
                null,
                null,
                null,
                "Ticket created from email message",
                [
                    'email_message_id' => $request->email_message_id,
                    'from_email' => $emailMessage->from_email ?? 'Unknown',
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

            $ticket->load(['customer', 'assignedAgent.user', 'creator']);

            return response()->json([
                'success' => true,
                'message' => 'Ticket created from email successfully',
                'data' => $ticket,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket from email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Map priority to urgency level.
     */
    private function mapPriorityToUrgency(string $priority): string
    {
        return match($priority) {
            'P1' => 'Critical',
            'P2' => 'High',
            'P3' => 'Medium',
            'P4' => 'Low',
            default => 'Medium',
        };
    }

    /**
     * Dispatch ticket notifications asynchronously.
     * Notifications are queued to prevent blocking ticket creation.
     *
     * @param Ticket $ticket
     * @param string $eventType (created, assigned, escalated, etc.)
     * @return void
     */
    private function dispatchTicketNotifications(Ticket $ticket, string $eventType = 'created'): void
    {
        try {
            // 1. Broadcast real-time event to WebSocket channels
            event(new TicketNotification([
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'event_type' => $eventType,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'assigned_to' => $ticket->assigned_to,
                'subject' => $ticket->subject,
                'timestamp' => now()->toISOString(),
                'message' => "Ticket #{$ticket->ticket_number} {$eventType}",
            ]));

            // 2. Send database notification to assigned agent (queued)
            if ($ticket->assigned_to && $ticket->assignedAgent && $ticket->assignedAgent->user) {
                $ticket->assignedAgent->user->notify(
                    new TicketCreatedNotification($ticket, $eventType)
                );
            }

            // 3. Notify supervisors/admins for high-priority tickets (queued)
            if (in_array($ticket->priority, ['P1', 'P2'])) {
                $supervisors = User::whereHas('role', function($query) {
                    $query->whereIn('slug', ['super_admin', 'admin', 'supervisor']);
                })->get();

                Notification::send($supervisors, new TicketCreatedNotification($ticket, $eventType));
            }

            \Log::info("Notifications dispatched for ticket #{$ticket->ticket_number}", [
                'ticket_id' => $ticket->id,
                'event_type' => $eventType,
                'assigned_to' => $ticket->assigned_to,
                'priority' => $ticket->priority,
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail ticket creation
            \Log::error('Failed to dispatch ticket notifications: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get agent statistics for dashboard
     */
    public function getAgentStatistics(Request $request, $agentId)
    {
        try {
            $today = now()->startOfDay();

            // Get active tickets assigned to agent
            $activeTickets = Ticket::where('assigned_to', $agentId)
                ->whereNotIn('status', ['Closed', 'Cancelled', 'Resolved'])
                ->count();

            // Get tickets resolved today
            $resolvedToday = Ticket::where('assigned_to', $agentId)
                ->where('status', 'Resolved')
                ->whereDate('updated_at', $today)
                ->count();

            // Get new tickets assigned today
            $newToday = Ticket::where('assigned_to', $agentId)
                ->whereDate('created_at', $today)
                ->count();

            // Calculate average response time (in minutes)
            $avgResponseTime = DB::table('ticket_histories')
                ->join('tickets', 'ticket_histories.ticket_id', '=', 'tickets.id')
                ->where('tickets.assigned_to', $agentId)
                ->where('ticket_histories.action', 'comment')
                ->whereDate('ticket_histories.created_at', '>=', now()->subDays(7))
                ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (ticket_histories.created_at - tickets.created_at))/60) as avg_response'))
                ->value('avg_response');

            // Format average response time
            $formattedAvgResponse = $avgResponseTime
                ? ($avgResponseTime < 60 ? round($avgResponseTime) . ' min' : round($avgResponseTime / 60, 1) . ' hrs')
                : '0 min';

            // Calculate SLA compliance (percentage of tickets meeting SLA)
            $totalTickets = Ticket::where('assigned_to', $agentId)
                ->whereDate('created_at', '>=', now()->subDays(30))
                ->count();

            $slaCompliantTickets = Ticket::where('assigned_to', $agentId)
                ->whereDate('created_at', '>=', now()->subDays(30))
                ->where('sla_breached', false)
                ->count();

            $slaCompliance = $totalTickets > 0 ? round(($slaCompliantTickets / $totalTickets) * 100) : 100;

            return response()->json([
                'success' => true,
                'data' => [
                    'activeTickets' => $activeTickets,
                    'resolvedToday' => $resolvedToday,
                    'newToday' => $newToday,
                    'avgResponseTime' => $formattedAvgResponse,
                    'avgResponseMinutes' => round($avgResponseTime ?? 0),
                    'slaCompliance' => $slaCompliance,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch agent statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/v1/tickets/{ticketNumber}/email-thread
     *
     * Returns a ticket and its full email thread for the agent email view.
     * Accepts ticket_number (e.g. TKT-20260001) or numeric ID.
     */
    public function emailThread(Request $request, string $ticketNumber): JsonResponse
    {
        try {
            $ticket = Ticket::with(['customer', 'assignedAgent.user'])
                ->where(function ($q) use ($ticketNumber) {
                    $q->where('ticket_number', $ticketNumber);
                    if (is_numeric($ticketNumber)) {
                        $q->orWhere('id', (int) $ticketNumber);
                    }
                })
                ->firstOrFail();

            $mapMessages = fn ($query) => $query->orderBy('received_at', 'asc')->get()
                ->map(fn ($m) => [
                    'id'          => $m->id,
                    'from_email'  => $m->from_email,
                    'from_name'   => $m->from_name,
                    'to_email'    => $m->to_email,
                    'subject'     => $m->subject,
                    'body_text'   => $m->body_text,
                    'body_html'   => $m->body_html,
                    'attachments' => collect($m->attachments ?? [])->map(function ($att) {
                        if (!empty($att['path'])) {
                            $att['url'] = rtrim(config('app.frontend_url', 'https://cxsuite.telediant.com.my'), '/')
                                . '/storage/' . $att['path'];
                        }
                        return $att;
                    })->values()->all(),
                    'received_at' => $m->received_at?->toISOString(),
                    'direction'   => $m->direction ?? 'inbound',
                ]);

            $messages = $mapMessages(EmailMessage::where('ticket_id', $ticket->id));

            // If a previous (closed) ticket reference is provided, load its messages
            // so the agent can see the full context in the same iframe view.
            $previousTicketData = null;
            $previousMessages   = [];
            if ($refNumber = $request->query('ref')) {
                $refTicket = Ticket::where('ticket_number', $refNumber)->first();
                if ($refTicket) {
                    $previousTicketData = [
                        'ticket_number' => $refTicket->ticket_number,
                        'subject'       => $refTicket->subject,
                        'status'        => $refTicket->status,
                        'created_at'    => $refTicket->created_at?->toISOString(),
                        'closed_at'     => $refTicket->closed_at?->toISOString(),
                    ];
                    $previousMessages = $mapMessages(EmailMessage::where('ticket_id', $refTicket->id));
                }
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'ticket' => [
                        'id'                   => $ticket->id,
                        'ticket_number'        => $ticket->ticket_number,
                        'subject'              => $ticket->subject,
                        'status'               => $ticket->status,
                        'priority'             => $ticket->priority,
                        'channel'              => $ticket->channel,
                        'mitel_interaction_id' => $ticket->mitel_interaction_id,
                        'customer' => $ticket->customer ? [
                            'name'  => $ticket->customer->name,
                            'email' => $ticket->customer->email,
                            'phone' => $ticket->customer->phone,
                        ] : null,
                        'assigned_to'             => $ticket->assigned_to,
                        'assigned_agent'          => $ticket->assignedAgent?->user?->name,
                        'created_at'              => $ticket->created_at?->toISOString(),
                        'resolved_at'             => $ticket->resolved_at?->toISOString(),
                        'closed_at'               => $ticket->closed_at?->toISOString(),
                        'resolution_time_minutes' => $ticket->resolution_time_minutes,
                        'sla_due_at'              => $ticket->sla_due_at?->toISOString(),
                        'sla_breached'            => (bool) $ticket->sla_breached,
                    ],
                    'messages'        => $messages,
                    'previous_ticket' => $previousTicketData,
                    'previous_messages' => $previousMessages,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Ticket not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/tickets/{ticketNumber}/reply-email
     *
     * Accepts multipart/form-data with:
     *   body      (string, required) – HTML body
     *   to        (email, optional)  – override recipient
     *   attachments[] (file, optional) – one or more files
     */
    public function replyEmail(Request $request, string $ticketNumber): JsonResponse
    {
        $ticket = Ticket::where(function ($q) use ($ticketNumber) {
            $q->where('ticket_number', $ticketNumber);
            if (is_numeric($ticketNumber)) {
                $q->orWhere('id', (int) $ticketNumber);
            }
        })->firstOrFail();

        $request->validate([
            'body'           => 'required|string',
            'to'             => 'nullable|email',
            'attachments'    => 'nullable|array',
            'attachments.*'  => 'file|max:20480', // 20 MB each
        ]);

        $toEmail = $request->input('to')
            ?? $ticket->customer?->email
            ?? EmailMessage::where('ticket_id', $ticket->id)->value('from_email');

        if (!$toEmail) {
            return response()->json(['success' => false, 'message' => 'No recipient email address found'], 422);
        }

        try {
            $subject   = 'Re: ' . $ticket->subject . ' [' . $ticket->ticket_number . ']';
            $bodyHtml  = $request->input('body');
            $bodyText  = strip_tags($bodyHtml);
            $fromEmail = config('mail.from.address');
            $fromName  = config('mail.from.name', config('app.name'));

            // Fetch previous messages so we can thread the reply properly
            $previousMessages = EmailMessage::where('ticket_id', $ticket->id)
                ->orderBy('received_at', 'asc')
                ->get();

            // The most-recent inbound message ID becomes In-Reply-To
            $lastInbound = $previousMessages->where('direction', 'inbound')->last()
                ?? $previousMessages->first();
            $inReplyToId = $lastInbound?->message_id;

            // Build References header from all known message IDs (oldest first)
            $referencesHeader = $previousMessages
                ->pluck('message_id')
                ->filter()
                ->implode(' ');

            // Existing thread ID to keep the outbound message in the same thread
            $threadId = $previousMessages->whereNotNull('conversation_thread_id')
                ->first()?->conversation_thread_id;

            $agentName = auth()->user()?->name ?? auth()->user()?->username ?? '';

            // The quoted original goes only in the sent email so the customer sees standard
            // reply context. We do NOT include it in $htmlForDb because the CRM already
            // shows all messages in the thread view — no need to duplicate content there.
            $quotedHtml = $this->buildQuotedHtml($lastInbound);

            // DB version of footer — uses external URL for logo (works in browser iframe)
            $footerForDb = $this->buildEmailFooter($ticket, $agentName, useUrl: true);
            $htmlForDb   = $bodyHtml . $footerForDb;

            // Store uploaded attachments
            $storedAttachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('email_attachments/' . date('Y/m/d'), 'public');
                    $storedAttachments[] = [
                        'filename'  => $file->getClientOriginalName(),
                        'path'      => $path,
                        'url'       => rtrim(config('app.frontend_url', config('app.url')), '/') . '/storage/' . $path,
                        'size'      => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                    ];
                }
            }

            // Generate Message-ID before sending so we can set it as a header on the
            // outbound email. Gmail/Outlook will echo this in their In-Reply-To when the
            // customer replies, which lets ProcessIncomingEmail match the existing ticket.
            $msgDomain = parse_url(config('app.frontend_url', config('app.url')), PHP_URL_HOST) ?: 'mail.local';
            $messageId = '<' . \Illuminate\Support\Str::uuid() . '@' . $msgDomain . '>';

            $ticket_ref  = $ticket;
            $attachments = $storedAttachments;
            Mail::send([], [], function ($msg) use (
                $toEmail, $subject, $fromEmail, $fromName, $bodyHtml, $bodyText,
                $attachments, $inReplyToId, $referencesHeader,
                $ticket_ref, $agentName, $quotedHtml, $messageId
            ) {
                // Embed the logo inline (CID) so it renders immediately without the
                // recipient needing to click "Trust sender" / "Show blocked content" —
                // mail clients block remotely-fetched <img> sources by default for
                // senders outside the Safe Senders list. Falls back to the external
                // URL if the local logo file isn't present on this server.
                $logoPath = public_path('logo-kastam.png');
                $logoCid  = file_exists($logoPath) ? $msg->embed($logoPath) : null;
                $footerForEmail = $logoCid
                    ? $this->buildEmailFooter($ticket_ref, $agentName, useUrl: false, cidSrc: $logoCid)
                    : $this->buildEmailFooter($ticket_ref, $agentName, useUrl: true);
                // Include quoted original so the customer sees context (standard reply format)
                $fullHtml = $bodyHtml . $footerForEmail . $quotedHtml;

                $msg->to($toEmail)
                    ->subject($subject)
                    ->from($fromEmail, $fromName)
                    ->html($fullHtml)
                    ->text($bodyText);

                // Set threading headers via the underlying Symfony Email object so
                // Gmail/Outlook group this reply in the same conversation thread.
                $symfonyMsg = $msg->getSymfonyMessage();
                $headers    = $symfonyMsg->getHeaders();
                // Set our own Message-ID so the mail server doesn't overwrite it.
                // When the customer replies, their In-Reply-To will match this value
                // and ProcessIncomingEmail can link the reply to the same ticket.
                // Message-ID requires IdentificationHeader — remove the auto-generated
                // one first, then add ours (addIdHeader expects no angle brackets).
                if ($headers->has('Message-ID')) {
                    $headers->remove('Message-ID');
                }
                $headers->addIdHeader('Message-ID', trim($messageId, '<>'));
                if ($inReplyToId) {
                    $headers->addTextHeader('In-Reply-To', $inReplyToId);
                }
                if ($referencesHeader) {
                    $headers->addTextHeader('References', $referencesHeader);
                }
                Log::info('[replyEmail] Threading headers', [
                    'ticket'       => $ticket_ref->ticket_number,
                    'message_id'   => $messageId,
                    'in_reply_to'  => $inReplyToId,
                    'references'   => $referencesHeader,
                    'to'           => $toEmail,
                ]);

                foreach ($attachments as $att) {
                    $fullPath = Storage::disk('public')->path($att['path']);
                    $msg->attach($fullPath, [
                        'as'   => $att['filename'],
                        'mime' => $att['mime_type'],
                    ]);
                }
            });
            if (!$threadId) {
                $threadId = md5($messageId);
            }

            EmailMessage::create([
                'message_id'            => $messageId,
                'ticket_id'             => $ticket->id,
                'from_email'            => $fromEmail,
                'from_name'             => $fromName,
                'to_email'              => $toEmail,
                'subject'               => $subject,
                'body_html'             => $htmlForDb,
                'body_text'             => $bodyText,
                'attachments'           => $storedAttachments ?: null,
                'direction'             => 'outbound',
                'received_at'           => now(),
                'is_processed'          => true,
                'conversation_thread_id' => $threadId,
                'in_reply_to'           => $inReplyToId,
                'references'            => $referencesHeader ?: null,
            ]);

            $ticket->logHistory('reply_sent', null, null, null, "Email reply sent to {$toEmail}");

            return response()->json(['success' => true, 'message' => 'Reply sent successfully']);
        } catch (\Exception $e) {
            Log::error('replyEmail failed: ' . $e->getMessage(), ['ticket' => $ticketNumber]);
            return response()->json(['success' => false, 'message' => 'Failed to send reply: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Upload a single inline image from the compose editor.
     * Returns a public URL that can be embedded in the email HTML.
     */
    public function uploadInlineImage(Request $request): JsonResponse
    {
        $request->validate(['image' => 'required|file|image|max:10240']);
        $file = $request->file('image');
        $path = $file->store('email_attachments/inline/' . date('Y/m/d'), 'public');
        $url  = rtrim(config('app.frontend_url', config('app.url')), '/') . '/storage/' . $path;
        return response()->json(['success' => true, 'url' => $url]);
    }

    private function buildQuotedHtml(?EmailMessage $original): string
    {
        if (!$original) return '';

        $date    = $original->received_at?->format('D, d M Y \a\t H:i T') ?? '';
        $from    = $original->from_name
            ? htmlspecialchars($original->from_name) . ' &lt;' . htmlspecialchars($original->from_email) . '&gt;'
            : htmlspecialchars($original->from_email);
        $content = $original->body_html ?: nl2br(htmlspecialchars($original->body_text ?? ''));

        // Use Gmail's native quote class so Gmail auto-collapses the quoted section
        return <<<HTML

<br>
<div class="gmail_quote">
  <div dir="ltr" style="font-size:13px;color:#6b7280;margin-bottom:4px;">On {$date}, {$from} wrote:</div>
  <blockquote class="gmail_quote" style="margin:0 0 0 0.8ex;border-left:1px solid #ccc;padding-left:1ex;">
    {$content}
  </blockquote>
</div>
HTML;
    }

    /**
     * @param bool        $useUrl   true  → public URL (for DB / browser display)
     *                              false → use $cidSrc (for email send via CID)
     * @param string|null $cidSrc   CID returned by $msg->embed(), only used when !$useUrl
     */
    private function buildEmailFooter(Ticket $ticket, string $agentName = '', bool $useUrl = true, ?string $cidSrc = null): string
    {
        $fromEmail = config('mail.from.address', 'appsdev@tde.com.my');
        $ticketNo  = $ticket->ticket_number;
        $nameHtml  = $agentName
            ? "<strong style=\"font-size:14px;color:#111827;\">{$agentName}</strong><br>"
            : '';

        if ($useUrl) {
            // Browser-facing version: external URL so it loads in the iframe/ticket-detail
            $logoUrl = rtrim(config('app.frontend_url', 'https://cxsuite.telediant.com.my'), '/') . '/logo-kastam.png';
            $logoImg = "<img src=\"{$logoUrl}\" alt=\"Kastam\" style=\"height:52px;width:auto;display:block;\">";
        } else {
            // Email-send version: CID src so Gmail/Outlook render it without blocking
            $logoImg = $cidSrc
                ? "<img src=\"{$cidSrc}\" alt=\"Kastam\" style=\"height:52px;width:auto;display:block;\">"
                : '';
        }

        return <<<HTML

<br><hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
<table style="font-family:Arial,sans-serif;font-size:12px;color:#6b7280;line-height:1.7;" cellpadding="0" cellspacing="0">
  <tr>
    <td style="padding-right:16px;vertical-align:middle;">
      {$logoImg}
    </td>
    <td style="vertical-align:middle;border-left:3px solid #e5e7eb;padding-left:16px;">
      {$nameHtml}<span style="color:#374151;font-weight:600;">Customer Support Team</span><br>
      <span style="color:#6b7280;">CX Ignite &bull; {$fromEmail}</span><br>
      <span style="color:#9ca3af;font-size:11px;">Ticket Reference: <strong style="color:#374151;">{$ticketNo}</strong></span>
    </td>
  </tr>
</table>
HTML;
    }
}
