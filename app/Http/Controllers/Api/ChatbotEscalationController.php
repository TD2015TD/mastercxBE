<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatbotSession;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketNumberSequence;
use App\Services\MitelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatbotEscalationController extends Controller
{
    public function __construct(private MitelService $mitel) {}

    /**
     * POST /api/v1/public/chatbot/escalate
     *
     * Called by the citizen portal when the customer presses "Chat dengan Pegawai".
     * Creates an OpenMedia interaction on Mitel and returns the interaction ID + queue position.
     *
     * Request body:
     *   name     string   Customer name
     *   ic       string   IC / passport number
     *   topic    string   FAQ topic selected
     *   faq_path string   Full FAQ breadcrumb trail
     *
     * Response:
     *   { interaction_id, position_in_queue }
     */
    public function escalate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'nullable|string|max:255',
            'ic'       => 'nullable|string|max:50',
            'phone'    => 'nullable|string|max:20',
            'email'    => 'nullable|email|max:255',
            'issue'    => 'nullable|string|max:2000',
            'topic'    => 'nullable|string|max:255',
            'faq_path' => 'nullable|string',
        ]);

        try {
            $queue = config('services.mitel.escalation_queue', 'ChatbotEscalation');

            // Pre-generate ID so we can embed it in targetUri before calling Mitel
            $interactionId = (string) Str::uuid();

            // The agent chat page — Mitel Ignite will embed this as an iframe
            $agentBaseUrl = rtrim(config('services.chat.agent_url', 'https://chat.telediant.com.my'), '/');
            $targetUri    = "{$agentBaseUrl}/agent.html?sessionId={$interactionId}";

            $vars = [
                'Name'        => $validated['name']     ?? '',
                'IC'          => $validated['ic']        ?? '',
                'MobilePhone' => $validated['phone']    ?? '',
                'Email'       => $validated['email']    ?? '',
                'Topic'       => $validated['topic']    ?? '',
                'FaqPath'     => $validated['faq_path'] ?? '',
                'Channel'     => 'ChatbotPortal',
            ];

            $result = $this->mitel->escalateToAgent(
                queue:             $queue,
                vars:              $vars,
                id:                $interactionId,
                targetUri:         $targetUri,
                targetUriEmbedded: true,
                subject:           'Live Chat — ' . ($validated['topic'] ?? 'Pertanyaan'),
                from:              $validated['name'] ?? 'Pelanggan',
            );

            // Use pre-generated ID; fall back to whatever Mitel returned
            $interactionId = $result['_interaction_id'] ?? $interactionId;

            if ($interactionId) {
                ChatbotSession::create([
                    'interaction_id' => $interactionId,
                    'customer_name'  => $validated['name']     ?? null,
                    'customer_ic'    => $validated['ic']        ?? null,
                    'customer_phone' => $validated['phone']    ?? null,
                    'customer_email' => $validated['email']    ?? null,
                    'issue'          => $validated['issue']     ?? null,
                    'topic'          => $validated['topic']     ?? null,
                    'faq_path'       => $validated['faq_path']  ?? null,
                    'status'         => 'queuing',
                    'queue_name'     => $queue,
                ]);
            }

            return response()->json([
                'success'           => true,
                'interaction_id'    => $interactionId,
                'position_in_queue' => $result['_position_in_queue'] ?? 1,
            ]);
        } catch (\Exception $e) {
            Log::error('Chatbot escalation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to connect to the agent queue. Please try again.',
            ], 503);
        }
    }

    /**
     * GET /api/v1/public/chatbot/sessions
     *
     * Lists active/queuing chat sessions so agents can see pending chats and join them.
     * Used by the sessions monitor page at chat.telediant.com.my/sessions.html
     */
    public function listSessions(Request $request): JsonResponse
    {
        $status = $request->query('status'); // optional filter: queuing, active
        $agentId = $request->query('agent_id'); // optional filter by Mitel agent ID

        $query = ChatbotSession::query()
            ->where(function ($q) {
                // Always show queuing/active; also show closed sessions from the last 2 hours
                $q->whereIn('status', ['queuing', 'active'])
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'closed')
                         ->where('updated_at', '>=', now()->subHours(2));
                  });
            })
            ->orderBy('created_at', 'desc')
            ->limit(50);

        if ($status) {
            $query->where('status', $status);
        }
        if ($agentId) {
            $query->where('agent_id', $agentId);
        }

        $sessions = $query->get()->map(fn($s) => [
            'interaction_id' => $s->interaction_id,
            'customer_name'  => $s->customer_name,
            'topic'          => $s->topic,
            'status'         => $s->status,
            'agent_id'       => $s->agent_id,
            'agent_name'     => $s->agent_name,
            'queue_name'     => $s->queue_name,
            'created_at'     => $s->created_at,
            'agent_url'      => "https://chat.telediant.com.my/agent.html?sessionId={$s->interaction_id}"
                . ($s->agent_id ? "&userId={$s->agent_id}" : ''),
        ]);

        return response()->json(['success' => true, 'sessions' => $sessions]);
    }

    /**
     * GET /api/v1/public/chatbot/session/{interactionId}
     *
     * Returns the customer context for a chat session.
     * Used by the agent page (embedded in Ignite iframe) to display customer info.
     */
    public function getSessionInfo(string $interactionId): JsonResponse
    {
        $session = ChatbotSession::find($interactionId);

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        return response()->json([
            'success'        => true,
            'interaction_id' => $session->interaction_id,
            'customer_name'  => $session->customer_name,
            'customer_ic'    => $session->customer_ic,
            'customer_phone' => $session->customer_phone,
            'customer_email' => $session->customer_email,
            'topic'          => $session->topic,
            'faq_path'       => $session->faq_path,
            'status'         => $session->status,
            'agent_id'       => $session->agent_id,
            'agent_name'     => $session->agent_name,
            'queue_name'     => $session->queue_name,
        ]);
    }

    /**
     * POST /api/v1/public/chatbot/end/{interactionId}
     *
     * Called by the customer when they press "End Chat".
     * Marks the Mitel interaction as Handled so it disappears from the agent's Ignite inbox.
     */
    public function endSession(string $interactionId, Request $request): JsonResponse
    {
        try {
            $this->mitel->updateOpenMediaInteraction($interactionId, [
                'interactionState' => 'Handled',
            ]);
        } catch (\Exception $e) {
            Log::warning('Could not mark Mitel interaction as Handled', [
                'interaction_id' => $interactionId,
                'error'          => $e->getMessage(),
            ]);
        }

        $session = ChatbotSession::where('interaction_id', $interactionId)->first();

        if ($session) {
            $session->update(['status' => 'closed']);
            $this->autoCreateTicket($session);
        }

        return response()->json(['success' => true]);
    }

    private function autoCreateTicket(ChatbotSession $session): void
    {
        // Skip if a ticket already exists for this interaction (idempotency guard).
        if (Ticket::where('case_id', $session->interaction_id)->exists()) {
            return;
        }

        try {
            DB::beginTransaction();

            // Find or create customer from session data
            $customer = null;

            if ($session->customer_ic) {
                $customer = Customer::where('id_no', $session->customer_ic)->first();
            }
            if (!$customer && $session->customer_email) {
                $customer = Customer::where('email', $session->customer_email)->first();
            }
            if (!$customer) {
                $customer = Customer::create([
                    'name'          => $session->customer_name  ?? 'Pelanggan',
                    'email'         => $session->customer_email ?? null,
                    'id_type'       => 'MyKad',
                    'id_no'         => $session->customer_ic   ?? null,
                    'customer_type' => 'individual',
                    'status'        => 'active',
                    'country'       => 'Malaysia',
                ]);
            }

            // Generate ticket number
            try {
                $ticketNumber = TicketNumberSequence::generateNextNumber('chat');
            } catch (\Exception $e) {
                do {
                    $ticketNumber = 'TKT' . date('Ymd') . strtoupper(Str::random(6));
                } while (Ticket::where('ticket_number', $ticketNumber)->exists());
            }

            $topic   = $session->topic    ?? 'Live Chat';
            $faqPath = $session->faq_path ?? null;

            // Build description: customer info header + issue submitted before chat
            $description  = "Pelanggan: " . ($session->customer_name  ?? '-') . "\n";
            $description .= "IC/Pasport: " . ($session->customer_ic   ?? '-') . "\n";
            $description .= "Telefon:    " . ($session->customer_phone ?? '-') . "\n";
            $description .= "Email:      " . ($session->customer_email ?? '-') . "\n";
            if ($faqPath) {
                $description .= "Laluan FAQ: " . $faqPath . "\n";
            }
            $description .= "\n--- Perihal Isu ---\n";
            $description .= ($session->issue ?? '(Tiada perihal isu)') . "\n";

            Ticket::create([
                'ticket_number'   => $ticketNumber,
                'customer_id'     => $customer->id,
                'case_id'         => $session->interaction_id,
                'subject'         => 'Live Chat: ' . $topic,
                'description'     => $description,
                'channel'         => 'chat',
                'department'      => config('services.chat.default_department', 'Perkhidmatan Pelanggan'),
                'category'        => $topic,
                'sub_category'    => 'Live Chat',
                'priority'        => 'P3',
                'urgency'         => 'Medium',
                'impact'          => 'Low',
                'status'          => 'Closed',
                'closed_at'       => now(),
                'source'          => 'chatbot',
                'assigned_to'     => $session->crm_agent_id ?: null,
            ]);

            DB::commit();

            Log::info('Auto-ticket created for live chat session', [
                'interaction_id' => $session->interaction_id,
                'ticket_number'  => $ticketNumber,
                'customer_id'    => $customer->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to auto-create ticket for live chat', [
                'interaction_id' => $session->interaction_id,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /api/v1/public/chatbot/interaction/{interactionId}/status
     *
     * Check if an agent has been assigned to the interaction and return agent info.
     *
     * Response:
     *   { assigned, agent_name, agent_id }
     */
    public function getInteractionStatus(string $interactionId): JsonResponse
    {
        $session = ChatbotSession::where('interaction_id', $interactionId)->first();

        // If DB explicitly marks session closed, report unassigned immediately
        if ($session && $session->status === 'closed') {
            return response()->json([
                'success'        => true,
                'interaction_id' => $interactionId,
                'assigned'       => false,
            ]);
        }

        // Only states where an agent has actually accepted the interaction
        // Do NOT include queue/routing states (routing, queued, parked) — those mean no agent yet
        $acceptedStates = ['connected', 'active', 'answered', 'established', 'accepted', 'acd',
                           'transferring', 'transferred', 'held', 'hold', 'consulting'];

        try {
            $interaction = $this->mitel->getOpenMediaInteraction($interactionId);

            $state    = strtolower($interaction['conversationState'] ?? '');
            $assigned = in_array($state, $acceptedStates);

            if ($assigned) {
                ChatbotSession::where('interaction_id', $interactionId)->update([
                    'status'     => 'active',
                    'agent_id'   => $interaction['agentId']   ?? null,
                    'agent_name' => $interaction['agentName'] ?? null,
                ]);
            } elseif ($state === 'notfound') {
                // Interaction gone from Mitel.
                // Only close if the agent had already accepted (status=active) — if still
                // queuing, Mitel may just be processing the new interaction (race condition).
                ChatbotSession::where('interaction_id', $interactionId)
                    ->where('status', 'active')
                    ->update(['status' => 'closed']);
            }

            return response()->json([
                'success'            => true,
                'interaction_id'     => $interactionId,
                'assigned'           => $assigned,
                'conversation_state' => $state,
                'agent_name'         => $interaction['agentName'] ?? null,
                'agent_id'           => $interaction['agentId']   ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get interaction status', [
                'interaction_id' => $interactionId,
                'error'          => $e->getMessage(),
            ]);

            // If Mitel call fails but DB already shows active, keep the live session alive
            if ($session && $session->status === 'active') {
                return response()->json([
                    'success'        => true,
                    'interaction_id' => $interactionId,
                    'assigned'       => true,
                ]);
            }

            // Mitel API unavailable — ask the Python WebSocket service directly.
            // Python knows in real-time when an agent has connected to a session.
            try {
                $pyResp = \Illuminate\Support\Facades\Http::timeout(3)
                    ->get('http://localhost:8000/api/sessions');
                if ($pyResp->ok()) {
                    $sessions = $pyResp->json('sessions', []);
                    foreach ($sessions as $s) {
                        if (($s['session_id'] ?? '') === $interactionId && !empty($s['has_agent'])) {
                            // Agent is connected — mark DB active so future calls use the fast path
                            ChatbotSession::where('interaction_id', $interactionId)
                                ->whereIn('status', ['queuing', 'active'])
                                ->update([
                                    'status'   => 'active',
                                    'agent_id' => $s['agent_id'] ?? null,
                                ]);
                            return response()->json([
                                'success'        => true,
                                'interaction_id' => $interactionId,
                                'assigned'       => true,
                                'agent_id'       => $s['agent_id'] ?? null,
                            ]);
                        }
                    }
                }
            } catch (\Exception $pyErr) {
                Log::warning('Python session check failed', ['error' => $pyErr->getMessage()]);
            }

            return response()->json([
                'success'        => true,
                'interaction_id' => $interactionId,
                'assigned'       => false,
            ]);
        }
    }

    /**
     * GET /api/v1/public/chatbot/conversation/{id}/state?token={bearerToken}
     *
     * Proxy the Mitel conversation endpoint using the agent's own Bearer token.
     * Returns the raw conversation data so the iframe can detect end vs transfer
     * without worrying about CORS between chat.telediant.com.my and mcx.telediant.com.my.
     */
    public function conversationState(string $id, Request $request): JsonResponse
    {
        try {
            $data = $this->mitel->getOpenMediaInteraction($id);

            $state = strtolower(
                $data['interactionState'] ?? $data['conversationState'] ?? $data['state'] ?? ''
            );

            // Terminal end states — session is genuinely over
            $endStates    = ['handled', 'abandoned', 'timedout', 'failed'];
            // Active states — interaction is alive (in queue or being handled)
            // "Offered" = interaction is in queue / offered to agent (also appears while agent handles it)
            $activeStates = ['offered', 'queued', 'pending', 'executing', 'active', 'connected'];

            return response()->json([
                'success'   => true,
                'state'     => $state,
                'is_ended'  => in_array($state, $endStates),
                'is_active' => in_array($state, $activeStates),
                'raw'       => $data,
            ]);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '404') || str_contains(strtolower($msg), 'not found')) {
                return response()->json(['success' => true, 'state' => 'notfound', 'is_ended' => true, 'is_queued' => false]);
            }
            return response()->json(['success' => false, 'message' => $msg], 500);
        }
    }

    /**
     * POST /api/v1/public/chatbot/agent-token
     *
     * Returns a per-agent Mitel Bearer token using server-side impersonation.
     * Agent provides only their Mitel username — no password needed on the client.
     * Used by agent.html to connect to Mitel's employeehub SignalR for native
     * end vs forward detection.
     */
    public function agentToken(Request $request): JsonResponse
    {
        $validated = $request->validate(['username' => 'required|string|max:100']);

        try {
            $data = $this->mitel->impersonateLogin($validated['username']);
            return response()->json([
                'success'    => true,
                'token'      => $data['access_token'],
                'expires_in' => $data['expires_in'] ?? 3600,
            ]);
        } catch (\Exception $e) {
            Log::warning('Agent token impersonation failed', [
                'username' => $validated['username'],
                'error'    => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Login gagal. Semak nama pengguna.'], 401);
        }
    }

    /**
     * POST /api/v1/public/chatbot/transfer
     *
     * Creates a new OpenMedia interaction for the target queue, carrying
     * the same targetUri (agent.html?sessionId=original_id) so Agent 2
     * sees the live chat iframe in Mitel Ignite.
     */
    public function transferChat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'interaction_id' => 'required|string',
            'target_queue'   => 'required|string|max:255',
        ]);

        $session = ChatbotSession::where('interaction_id', $validated['interaction_id'])->first();

        if (!$session) {
            return response()->json(['success' => false, 'message' => 'Session not found'], 404);
        }

        try {
            $agentBaseUrl = rtrim(config('services.chat.agent_url', 'https://chat.telediant.com.my'), '/');
            // Point targetUri at the SAME session so Agent 2 joins the existing WS session
            $targetUri = "{$agentBaseUrl}/agent.html?sessionId={$validated['interaction_id']}";

            $vars = [
                'Name'        => $session->customer_name  ?? '',
                'IC'          => $session->customer_ic    ?? '',
                'MobilePhone' => $session->customer_phone ?? '',
                'Email'       => $session->customer_email ?? '',
                'Topic'       => $session->topic          ?? '',
                'FaqPath'     => $session->faq_path       ?? '',
                'Channel'     => 'ChatbotPortal',
            ];

            $this->mitel->escalateToAgent(
                queue:             $validated['target_queue'],
                vars:              $vars,
                targetUri:         $targetUri,
                targetUriEmbedded: true,
                subject:           'Live Chat (Pindah) — ' . ($session->topic ?? 'Pertanyaan'),
                from:              $session->customer_name ?? 'Pelanggan',
            );

            Log::info('Chat transferred to new queue', [
                'interaction_id' => $validated['interaction_id'],
                'target_queue'   => $validated['target_queue'],
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Chat transfer failed', [
                'interaction_id' => $validated['interaction_id'],
                'error'          => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Transfer gagal: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/public/chatbot/interaction/{interactionId}/agent-connected
     *
     * Called by the Python WebSocket service the moment an agent's WebSocket
     * connects to a session. Marks the DB session as active so the customer's
     * queuing poll immediately returns assigned=true without relying on Mitel's
     * OpenMedia status API (which can return 404 for valid interactions).
     */
    public function agentConnected(string $interactionId, Request $request): JsonResponse
    {
        $agentId = $request->input('agent_id');

        $updated = ChatbotSession::where('interaction_id', $interactionId)
            ->whereIn('status', ['queuing', 'active'])
            ->update(array_filter([
                'status'   => 'active',
                'agent_id' => $agentId ?: null,
            ]));

        Log::info('Agent connected — session marked active', [
            'interaction_id' => $interactionId,
            'agent_id'       => $agentId,
            'rows_updated'   => $updated,
        ]);

        return response()->json(['success' => true, 'updated' => $updated > 0]);
    }

    /**
     * POST /api/v1/public/chatbot/interaction/{interactionId}/register-agent
     *
     * Called by agent.html immediately after the agent logs into the CRM login gate.
     * Stores the CRM agent's database ID on the session so autoCreateTicket can
     * assign the ticket even if the iframe is destroyed before the chat ends.
     */
    public function registerCrmAgent(string $interactionId, Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['success' => false, 'message' => 'Token diperlukan.'], 401);
            }

            $user = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->authenticate();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Token tidak sah.'], 401);
            }

            $agent = $user->agent;
            if (!$agent) {
                return response()->json(['success' => false, 'message' => 'Pengguna bukan ejen.'], 403);
            }

            $updated = ChatbotSession::where('interaction_id', $interactionId)
                ->update(['crm_agent_id' => $agent->id]);

            Log::info('CRM agent registered for chat session', [
                'interaction_id' => $interactionId,
                'agent_id'       => $agent->id,
                'user_name'      => $user->name,
                'updated'        => $updated,
            ]);

            return response()->json(['success' => true, 'agent_name' => $user->name]);
        } catch (\Exception $e) {
            Log::error('registerCrmAgent failed', [
                'interaction_id' => $interactionId,
                'error'          => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/public/chatbot/interaction/{interactionId}/assign-ticket
     *
     * Called by agent.html after the agent logs in post-chat.
     * Finds the ticket created for this interaction and assigns it to the logged-in agent.
     * Requires a valid JWT Bearer token in the Authorization header.
     */
    public function assignTicket(string $interactionId, Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['success' => false, 'message' => 'Token diperlukan.'], 401);
            }

            $user = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->authenticate();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Token tidak sah.'], 401);
            }

            $agent = $user->agent;
            if (!$agent) {
                return response()->json(['success' => false, 'message' => 'Pengguna bukan ejen.'], 403);
            }

            // Ticket may still be creating (e.g. agent clicked end in native Mitel before
            // Python finished calling endSession). Retry up to 5× with 1s intervals.
            $ticket = null;
            for ($i = 0; $i < 5; $i++) {
                $ticket = \App\Models\Ticket::where('case_id', $interactionId)->first();
                if ($ticket) break;
                sleep(1);
            }
            if (!$ticket) {
                return response()->json(['success' => false, 'message' => 'Tiket belum dicipta. Cuba sebentar lagi.'], 404);
            }

            $ticket->assigned_to = $agent->id;
            $ticket->save();

            Log::info('Chat ticket assigned to agent', [
                'interaction_id' => $interactionId,
                'ticket_id'      => $ticket->id,
                'agent_id'       => $agent->id,
                'user_name'      => $user->name,
            ]);

            return response()->json([
                'success'       => true,
                'ticket_number' => $ticket->ticket_number,
                'agent_name'    => $user->name,
            ]);
        } catch (\Exception $e) {
            Log::error('Ticket assignment failed', [
                'interaction_id' => $interactionId,
                'error'          => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Ralat: ' . $e->getMessage()], 500);
        }
    }
}
