<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketNumberSequenceController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SlaConfigurationController;
use App\Http\Controllers\Api\SlaEngineController;
use App\Http\Controllers\Api\EscalationController;
use App\Http\Controllers\Api\CustomerInteractionController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\Mitel\CallController as MitelCallController;
use App\Http\Controllers\Api\Mitel\QueueController as MitelQueueController;
use App\Http\Controllers\Api\Mitel\WebhookController as MitelWebhookController;
use App\Http\Controllers\Api\Mitel\AgentController as MitelAgentController;
use App\Http\Controllers\Api\Mitel\OpenMediaController as MitelOpenMediaController;
use App\Http\Controllers\Api\Mitel\McxEmailInteractionController;
use App\Http\Controllers\Api\WebSocketController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PublicTicketController;
use App\Http\Controllers\Api\PublicKnowledgeBaseController;
use App\Http\Controllers\Api\ChatbotEscalationController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\ChatbotMessageController;
use App\Http\Controllers\Api\KnowledgeBaseController;
use App\Http\Controllers\Api\KbaseShareController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\ChatbotHealthController;
use App\Http\Controllers\Api\MitelController; // <--- ADDED: New Mitel Controller
use App\Http\Controllers\Api\SystemConfigurationController;
use App\Http\Controllers\Api\SupersetController;
use App\Http\Controllers\Api\SupersetDashboardController;
use App\Http\Controllers\MitelConfigurationController;
use App\Http\Controllers\SupersetConfigurationController;
use App\Http\Controllers\MitelConnectionController;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    
    // Public Ticket Submission (Citizen Portal - No Auth Required)
    Route::prefix('public')->group(function () {
        // Public Authentication (Phone + Email OTP)
        Route::post('/auth/request-otp', [App\Http\Controllers\Api\PublicAuthController::class, 'requestOtp']);
        Route::post('/auth/verify-otp', [App\Http\Controllers\Api\PublicAuthController::class, 'verifyOtp']);
        Route::post('/auth/register', [App\Http\Controllers\Api\PublicAuthController::class, 'register']);
        
        // Public Ticket APIs
        Route::post('/tickets', [PublicTicketController::class, 'submitTicket']);
        Route::post('/tickets/track', [PublicTicketController::class, 'trackTicket']);

        // Mitel Ignite embedded triage screen (token-authenticated, see EmbedTokenService)
        Route::get('/pending-emails/{emailMessageId}', [PublicTicketController::class, 'getPendingEmail']);
        Route::post('/pending-emails/create-ticket', [PublicTicketController::class, 'createTicketFromPendingEmail']);

        // Mitel Ignite embedded full ticket view (token-authenticated) — used once a
        // ticket already exists, so the same iframe can view/reply/close without ever
        // hitting the normal JWT-authenticated /tickets/* routes (which 401 in this
        // cross-origin embed and bounce the whole iframe to /agent-login).
        Route::get('/tickets/{ticketNumber}/email-thread', [PublicTicketController::class, 'getTicketThread']);
        Route::post('/tickets/{ticketNumber}/triage', [PublicTicketController::class, 'updateTicketTriage']);
        Route::post('/tickets/{ticketNumber}/reply-email', [PublicTicketController::class, 'sendTicketReply']);
        Route::post('/tickets/{ticketNumber}/close', [PublicTicketController::class, 'closeTicket']);
        Route::post('/tickets/{ticketNumber}/mitel-ping', [PublicTicketController::class, 'ticketMitelPing']);
        Route::post('/tickets/{ticketNumber}/mitel-end-conversation', [PublicTicketController::class, 'ticketMitelEndConversation']);
        Route::post('/email/upload-inline-image', [PublicTicketController::class, 'uploadInlineImage']);

        // Authenticated Citizen Routes (requires session token)
        Route::middleware('citizen.session')->group(function () {
            Route::get('/tickets/my-tickets', [PublicTicketController::class, 'getMyTickets']);
        });
        
        // Route with parameter must come after specific routes
        Route::get('/tickets/{ticketNumber}', [PublicTicketController::class, 'viewTicket']);
        Route::post('/tickets/{ticketNumber}/attachments', [PublicTicketController::class, 'uploadAttachment']);
        
        // Chatbot FAQ menu (public — no auth required)
        Route::get('/chatbot/categories', [ChatbotController::class, 'categories']);
        Route::get('/chatbot/items/{categoryId}', [ChatbotController::class, 'items']);
        Route::get('/chatbot/answer/{faqItemId}', [ChatbotController::class, 'answer']);

        // Chatbot escalation to Mitel queue (public — no auth required)
        Route::post('/chatbot/escalate', [ChatbotEscalationController::class, 'escalate']);
        Route::get('/chatbot/interaction/{interactionId}/status', [ChatbotEscalationController::class, 'getInteractionStatus']);
        Route::get('/chatbot/session/{interactionId}', [ChatbotEscalationController::class, 'getSessionInfo']);
        Route::get('/chatbot/sessions', [ChatbotEscalationController::class, 'listSessions']);

        // Chatbot message relay — forwards customer messages into Mitel OpenMedia so they appear in Ignite
        Route::post('/chatbot/message', [ChatbotMessageController::class, 'send']);

        // Agent file upload — any file dragged from filesystem into the chat
        Route::post('/chat/upload', function (\Illuminate\Http\Request $request) {
            $request->validate(['file' => 'required|file|max:20480']); // 20 MB max
            $file = $request->file('file');
            $path = $file->store('chat-uploads', 'public');
            $url  = \Illuminate\Support\Facades\Storage::url($path);
            return response()->json([
                'success'   => true,
                'url'       => url($url),
                'name'      => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size'      => $file->getSize(),
            ]);
        });

        // Customer ends chat — marks Mitel interaction as Handled and closes DB session
        Route::post('/chatbot/end/{interactionId}', [ChatbotEscalationController::class, 'endSession']);

        // Store a single chat message in real-time (called by agent widget for each message)
        Route::post('/chatbot/messages', [ChatbotEscalationController::class, 'storeMessage']);

        // Agent transfers chat to another queue — creates new OpenMedia interaction with same targetUri
        Route::post('/chatbot/transfer', [ChatbotEscalationController::class, 'transferChat']);

        // Proxy the Mitel conversation state endpoint using the agent's own token.
        // The iframe polls this to detect end vs transfer without CORS issues.
        Route::get('/chatbot/conversation/{id}/state', [ChatbotEscalationController::class, 'conversationState']);

        // Mitel OSCMS webhook — receives real-time interaction state events from Mitel.
        // Public because Mitel cannot send a JWT token. Verified via shared secret header.
        Route::post('/mitel-event', [MitelWebhookController::class, 'handlePublic']);

        // Beacon close — called by sendBeacon on page unload when iframe is destroyed.
        // Must be public (no auth middleware) because sendBeacon cannot send headers.
        // JWT is validated from the request body._jwt field inside the controller.
        Route::post('/tickets/{id}/close-beacon', [\App\Http\Controllers\Api\TicketController::class, 'closeBeacon']);

        // Get a per-agent Mitel Bearer token via server-side impersonation.
        // Only the username is required — no password (server uses client_credentials + Impersonate header).
        // Used by agent.html to authenticate its own SignalR connection for native end/forward detection.
        Route::post('/chatbot/agent-token', [ChatbotEscalationController::class, 'agentToken']);

        // Register the CRM agent on the session at login time so the ticket is auto-assigned
        // even if the agent iframe is destroyed before the chat ends.
        Route::post('/chatbot/interaction/{interactionId}/register-agent', [ChatbotEscalationController::class, 'registerCrmAgent']);

        // Assign the auto-created ticket to the logged-in agent after chat ends.
        // Validates a JWT Bearer token supplied by agent.html after the agent logs in.
        Route::post('/chatbot/interaction/{interactionId}/assign-ticket', [ChatbotEscalationController::class, 'assignTicket']);

        // KBase endpoints for agent chat panel (no auth — embedded Mitel Ignite iframe)
        Route::post('/kbase/share', [KbaseShareController::class, 'share']);
        Route::get('/kbase/documents', function () {
            $articles = \App\Models\KnowledgeBaseArticle::select('id', 'title', 'category_code', 'file_type', 'file_name')
                ->orderBy('title')
                ->limit(200)
                ->get()
                ->map(fn($a) => [
                    'id'        => $a->id,
                    'title'     => $a->title,
                    'category'  => $a->category_code,
                    'file_type' => $a->file_type ?? 'doc',
                    'file_size' => '-',
                ]);
            return response()->json($articles);
        });

        Route::get('/kbase/articles/{id}', function (string $id) {
            $article = \App\Models\KnowledgeBaseArticle::find($id);
            if (!$article) {
                return response()->json(['success' => false, 'message' => 'Not found'], 404);
            }
            // Build URL from the actual request host — APP_URL may be localhost in .env
            $fileUrl = $article->file_path
                ? request()->getSchemeAndHttpHost() . '/api/v1/public/kbase/articles/' . $article->id . '/file'
                : null;
            return response()->json([
                'success'   => true,
                'id'        => $article->id,
                'title'     => $article->title,
                'content'   => $article->content,
                'file_type' => $article->file_type,
                'file_name' => $article->file_name,
                'file_url'  => $fileUrl,
            ]);
        });

        Route::get('/kbase/articles/{id}/file', function (string $id) {
            $article = \App\Models\KnowledgeBaseArticle::find($id);
            if (!$article || !$article->file_path) {
                abort(404, 'File not found.');
            }
            if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($article->file_path)) {
                abort(404, 'File not found.');
            }
            $mime     = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($article->file_path);
            $contents = \Illuminate\Support\Facades\Storage::disk('public')->get($article->file_path);
            $viewable = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
            $disposition = in_array($mime, $viewable) ? 'inline' : 'attachment';
            return response($contents, 200)
                ->header('Content-Type', $mime)
                ->header('Content-Disposition', $disposition . '; filename="' . ($article->file_name ?? 'document') . '"');
        });

        // Public Knowledge Base APIs (Elasticsearch)
        Route::prefix('knowledge-base')->group(function () {
            Route::get('/search', [PublicKnowledgeBaseController::class, 'search']);
            Route::get('/articles/{id}', [PublicKnowledgeBaseController::class, 'getArticle']);
            Route::get('/popular', [PublicKnowledgeBaseController::class, 'getPopular']);
            Route::get('/categories', [PublicKnowledgeBaseController::class, 'getCategories']);
            Route::post('/articles/{id}/feedback', [PublicKnowledgeBaseController::class, 'submitFeedback']);
            Route::get('/faqs', [PublicKnowledgeBaseController::class, 'getFAQs']);
        });
    });
    
    // Language routes (public)
    Route::get('/languages', [LanguageController::class, 'getLanguages']);
    Route::get('/translations/{locale?}', [LanguageController::class, 'getTranslations']);
    Route::get('/translations', [LanguageController::class, 'getAllTranslations']);

    // Lookups (public - for dropdowns and enums with bilingual support)
    Route::get('/lookups', [LookupController::class, 'index']);
    Route::get('/lookups/types', [LookupController::class, 'types']);
    Route::get('/lookups/{type}', [LookupController::class, 'index']); // Get lookups by type

    // Categories (public - for ticket submission dropdowns)
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/tree/view', [CategoryController::class, 'tree']);
    Route::get('/categories/department-level/filter', [CategoryController::class, 'byDepartmentLevel']);

    // Mitel Configuration (public - for frontend to fetch config)
    Route::get("/mitel-config", [MitelConfigurationController::class, "index"]);

    // Superset Configuration (public - frontend fetches URL)
    Route::get("/superset-config", [SupersetConfigurationController::class, "index"]);

    // Protected routes (with auto-refresh for call center agents)
    Route::middleware(['jwt.verify', 'jwt.auto.refresh'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        
        // User Management (Admin only - requires user.manage permission)
        Route::middleware(['permission:user.manage'])->group(function () {
            Route::get('/users', [UserController::class, 'index']);
            Route::post('/users', [UserController::class, 'store']);
            Route::get('/users/{id}', [UserController::class, 'show']);
            Route::put('/users/{id}', [UserController::class, 'update']);
            Route::delete('/users/{id}', [UserController::class, 'destroy']);
            Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
        });
        
        // User Profile (All authenticated users can update their own profile)
        Route::put('/users/profile', [UserController::class, 'updateProfile']);
        Route::post('/users/change-password', [UserController::class, 'changePassword']);
        
        // Agent Management
        Route::apiResource('agents', AgentController::class);
        Route::put('/agents/{id}/availability', [AgentController::class, 'updateAvailability']);
        Route::get('/agents/{id}/metrics', [AgentController::class, 'metrics']);
        
        // Agent - Mitel Integration (Telephony State Management)
        Route::get('/agents/{id}/mitel/state', [AgentController::class, 'getMitelState']);
        Route::put('/agents/{id}/mitel/state', [AgentController::class, 'updateMitelState']);
        Route::get('/agents/{id}/mitel/statistics', [AgentController::class, 'getMitelStatistics']);
        
        // Agent - Shift Management
        Route::get('/agents/{id}/shifts', [AgentController::class, 'getShifts']);
        Route::post('/agents/{id}/shifts', [AgentController::class, 'storeShift']);
        
        // Agent - Performance Metrics
        Route::get('/agents/{id}/performance', [AgentController::class, 'getPerformance']);
        Route::post('/agents/{id}/performance', [AgentController::class, 'storePerformance']);
        
        // Agent - Call Statistics
        Route::get('/agents/{id}/call-statistics', [AgentController::class, 'getCallStatistics']);
        Route::post('/agents/{id}/call-statistics', [AgentController::class, 'storeCallStatistics']);
        
        // Customer Management
        Route::apiResource('customers', CustomerController::class);
        Route::post('/customers/search', [CustomerController::class, 'search']);
        Route::get('/customers/{id}/statistics', [CustomerController::class, 'statistics']);

        // Customer Contacts
        Route::get('/customers/{customerId}/contacts', [ContactController::class, 'index']);
        Route::get('/contacts/find-by-phone/{phoneNumber}', [ContactController::class, 'findByPhone']);
        Route::apiResource('contacts', ContactController::class);
        
        // Customer Attachments
        Route::post('/customers/{customerId}/attachments', [CustomerController::class, 'uploadAttachment'])->name('api.customers.attachments.upload');
        Route::get('/customers/{customerId}/attachments', [CustomerController::class, 'getAttachments'])->name('api.customers.attachments.index');
        Route::get('/customers/{customerId}/attachments/{attachmentId}/download', [CustomerController::class, 'downloadAttachment'])->name('api.customers.attachments.download');
        Route::delete('/customers/{customerId}/attachments/{attachmentId}', [CustomerController::class, 'deleteAttachment'])->name('api.customers.attachments.delete');
        
        // Customer Notes
        Route::get('/customers/{customerId}/notes', [CustomerController::class, 'getNotes'])->name('api.customers.notes.index');
        Route::post('/customers/{customerId}/notes', [CustomerController::class, 'createNote'])->name('api.customers.notes.create');
        Route::put('/customers/{customerId}/notes/{noteId}', [CustomerController::class, 'updateNote'])->name('api.customers.notes.update');
        Route::delete('/customers/{customerId}/notes/{noteId}', [CustomerController::class, 'deleteNote'])->name('api.customers.notes.delete');
        
        // Ticket Management
        Route::post('/tickets/reserve-number', [TicketController::class, 'reserveTicketNumber']);
        Route::apiResource('tickets', TicketController::class);
        Route::get('/tickets/{id}/history', [TicketController::class, 'history']);
        Route::get('/tickets/{ticketNumber}/email-thread', [TicketController::class, 'emailThread']);
        Route::post('/tickets/{ticketNumber}/reply-email', [TicketController::class, 'replyEmail']);
        Route::post('/email/upload-inline-image', [TicketController::class, 'uploadInlineImage']);
        Route::put('/tickets/{id}/assign', [TicketController::class, 'assign']);
        Route::put('/tickets/{id}/status', [TicketController::class, 'updateStatus']);
        Route::post('/tickets/{id}/resolve', [TicketController::class, 'resolve']);
        Route::post('/tickets/{id}/close', [TicketController::class, 'close']);
        Route::post('/tickets/close-by-mitel-id', [TicketController::class, 'closeByMitelId']);
        Route::post('/tickets/{id}/mitel-ping', [TicketController::class, 'mitelPing']);
        Route::post('/tickets/{id}/mitel-end-conversation', [TicketController::class, 'mitelEndConversation']);
        Route::post('/tickets/{id}/reopen', [TicketController::class, 'reopen']);
        Route::post('/tickets/{id}/response', [TicketController::class, 'recordResponse']);
        Route::get('/tickets/statistics/summary', [TicketController::class, 'statistics']);
        Route::get('/agents/{agentId}/statistics', [TicketController::class, 'getAgentStatistics']);

        // Ticket Attachments
        Route::post('/tickets/{ticketId}/attachments', [TicketController::class, 'uploadAttachment']);
        Route::get('/tickets/{ticketId}/attachments', [TicketController::class, 'getAttachments']);
        Route::get('/tickets/{ticketId}/attachments/{attachmentId}/download', [TicketController::class, 'downloadAttachment'])->name('api.tickets.attachments.download');
        Route::delete('/tickets/{ticketId}/attachments/{attachmentId}', [TicketController::class, 'deleteAttachment']);
        
        // Ticket Number Sequence Management (Super Admin only)
        Route::apiResource('ticket-sequences', TicketNumberSequenceController::class);
        Route::post('/ticket-sequences/{id}/reset', [TicketNumberSequenceController::class, 'reset']);
        Route::get('/ticket-sequences/{id}/preview', [TicketNumberSequenceController::class, 'preview']);
        
        // Category Management (Admin only - create, update, delete)
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
        
        // SLA Configuration Management
        Route::apiResource('sla-configurations', SlaConfigurationController::class);
        Route::get('/sla-configurations/get/by-ticket', [SlaConfigurationController::class, 'getSla']);
        Route::get('/sla-configurations/grouped/by-department', [SlaConfigurationController::class, 'groupedByDepartment']);
        
        // SLA Engine Settings
        Route::get('/sla-engine/settings', [SlaEngineController::class, 'getSettings']);
        Route::put('/sla-engine/settings', [SlaEngineController::class, 'updateSettings']);
        Route::post('/sla-engine/reset-defaults', [SlaEngineController::class, 'resetToDefaults']);
        Route::post('/sla-engine/test-business-hours', [SlaEngineController::class, 'testBusinessHours']);
        
        // Escalation Management
        Route::apiResource('escalations', EscalationController::class)->only(['index', 'store', 'show']);
        Route::post('/escalations/{id}/acknowledge', [EscalationController::class, 'acknowledge']);
        Route::post('/escalations/{id}/resolve', [EscalationController::class, 'resolve']);
        Route::get('/escalations/statistics/summary', [EscalationController::class, 'statistics']);
        Route::get('/escalations/check/{ticketId}', [EscalationController::class, 'checkAutoEscalation']);
        
        // Customer Interactions (Customer 360)
        Route::apiResource('customer-interactions', CustomerInteractionController::class);
        Route::get('/customer-interactions/timeline/{customerId}', [CustomerInteractionController::class, 'customerTimeline']);
        
        // Customer Contacts Management
        Route::apiResource('contacts', ContactController::class);
        Route::get('/contacts/find-by-phone/{phoneNumber}', [ContactController::class, 'findByPhone']);
        Route::get('/customers/{customerId}/contacts', [ContactController::class, 'index']);
        
        // Chatbot & Livechat Health Report (admin/super_admin only — requires system.config permission)
        Route::middleware(['permission:system.config'])->group(function () {
            Route::get('/admin/chatbot/health', [ChatbotHealthController::class, 'report']);
            Route::get('/admin/chatbot/session/{interactionId}', [\App\Http\Controllers\Api\ChatbotEscalationController::class, 'getSessionInfo']);
        });

        // Reports & Metrics (for Superset/BI integration)
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/agent-metrics', [ReportController::class, 'getAgentMetrics']);
        Route::get('/reports/supervisor-metrics', [ReportController::class, 'getSupervisorMetrics']);
        Route::get('/reports/ticket-volume', [ReportController::class, 'getTicketVolumeMetrics']);
        Route::get('/reports/sla-compliance', [ReportController::class, 'getSlaComplianceMetrics']);
        Route::get('/reports/customer-satisfaction', [ReportController::class, 'getCustomerSatisfactionMetrics']);
        Route::get('/reports/all-tickets', [ReportController::class, 'getAllTicketsReport']);
        Route::get('/reports/tickets-per-agent', [ReportController::class, 'getTicketsPerAgentReport']);
        Route::get('/reports/interactions-vs-ticket', [ReportController::class, 'getInteractionsVsTicketReport']);
        Route::get('/reports/channel-summary', [ReportController::class, 'getChannelSummaryReport']);
        
        // Dashboards
        Route::get('/dashboard/agent', [DashboardController::class, 'agentDashboard']);
        Route::get('/dashboard/supervisor', [DashboardController::class, 'supervisorDashboard']);
        Route::get('/dashboard/sla-details', [DashboardController::class, 'getSlaDetails']);
        Route::get('/dashboard/workload', [DashboardController::class, 'getWorkloadManagement']);
        
        // Widget-based Dashboard Endpoints (isolated error handling per widget)
        Route::get('/dashboard/queue-stats', [DashboardController::class, 'getQueueStats']);
        Route::get('/dashboard/team-stats', [DashboardController::class, 'getTeamStats']);
        Route::get('/dashboard/sla-stats', [DashboardController::class, 'getSlaStats']);
        Route::get('/dashboard/agent-performance', [DashboardController::class, 'getAgentPerformance']);
        Route::get('/dashboard/trends', [DashboardController::class, 'getTrends']);
        
        // New Dashboard Metrics
        Route::get('/dashboard/ticket-metrics', [DashboardController::class, 'getTicketMetrics']);
        Route::get('/dashboard/tickets-closed-by-day', [DashboardController::class, 'getTicketsClosedByDay']);
        Route::get('/dashboard/department-performance', [DashboardController::class, 'getDepartmentPerformance']);
        Route::get('/dashboard/livechat-status', [DashboardController::class, 'getLiveChatStatus']);
        Route::get('/dashboard/tickets-by-channel', [DashboardController::class, 'getTicketsByChannel']);
        Route::get('/dashboard/departments', [DashboardController::class, 'getDepartments']);
        
        // System Administration
        Route::prefix('system')->group(function () {
            Route::get('/health', [SystemController::class, 'getSystemHealth']);
            Route::get('/services', [SystemController::class, 'getServiceStatus']);
            Route::get('/alerts', [SystemController::class, 'getSystemAlerts']);
            Route::post('/alerts/{id}/acknowledge', [SystemController::class, 'acknowledgeAlert']);
            Route::post('/health-check', [SystemController::class, 'performHealthCheck']);
            Route::post('/services/{serviceName}/restart', [SystemController::class, 'restartService']);
            Route::post('/connectivity/{service}', [SystemController::class, 'testConnectivity']);

            // Audit Logs
            Route::get('/audit-logs', [SystemController::class, 'getAuditLogs']);
            Route::get('/audit-logs/export', [SystemController::class, 'exportAuditLogs']);

            // System Configuration Management
            Route::get('/configurations', [SystemConfigurationController::class, 'index']);
            Route::get('/configurations/category/{category}', [SystemConfigurationController::class, 'getByCategory']);
            Route::get('/configurations/{id}', [SystemConfigurationController::class, 'show']);
            Route::put('/configurations/{id}', [SystemConfigurationController::class, 'update']);
            Route::post('/configurations/bulk-update', [SystemConfigurationController::class, 'bulkUpdate']);
            Route::post('/configurations/test-mail', [SystemConfigurationController::class, 'testMailConfig']);
            Route::post('/configurations/test-ldap', [SystemConfigurationController::class, 'testLdapConnection']);
            Route::post('/configurations/clear-cache', [SystemConfigurationController::class, 'clearCache']);
        });

        // Email Configuration Management
        Route::prefix('email-configurations')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\EmailConfigurationController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\EmailConfigurationController::class, 'store']);
            Route::get('/active-imap', [\App\Http\Controllers\Api\EmailConfigurationController::class, 'getActiveImap']);
            Route::get('/{id}', [\App\Http\Controllers\Api\EmailConfigurationController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\EmailConfigurationController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\EmailConfigurationController::class, 'destroy']);
            Route::post('/{id}/test-smtp', [\App\Http\Controllers\Api\EmailConfigurationController::class, 'testSmtp']);
            Route::post('/{id}/test-imap', [\App\Http\Controllers\Api\EmailConfigurationController::class, 'testImap']);
            Route::post('/{id}/set-default', [\App\Http\Controllers\Api\EmailConfigurationController::class, 'setDefault']);
            Route::post('/{id}/toggle-active', [\App\Http\Controllers\Api\EmailConfigurationController::class, 'toggleActive']);
        });
        
        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::post('/notifications/email', [NotificationController::class, 'createEmailNotification']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/notifications', [NotificationController::class, 'clearAll']);

        // Email Ticket Management
        Route::get('/tickets/pending-email/{emailMessageId}', [TicketController::class, 'pendingEmail']);
        Route::post('/tickets/from-email', [TicketController::class, 'createFromEmail']);
        
        // Menu Management
        Route::get('/menus', [MenuController::class, 'index']); // Get menus for authenticated user
        Route::get('/menus/all', [MenuController::class, 'all']); // Get all menus (admin)
        Route::get('/menus/role/{roleSlug}', [MenuController::class, 'getByRole']); // Get menus by role
        Route::post('/menus/clear-cache', [MenuController::class, 'clearCache']); // Clear menu cache
        
        // Role & Permission Management (RBAC)
        Route::apiResource('roles', RoleController::class);
        Route::post('/roles/{id}/menu-items', [RoleController::class, 'assignMenuItems']);
        Route::delete('/roles/{id}/menu-items', [RoleController::class, 'removeMenuItems']);
        Route::get('/roles/available/menu-items', [RoleController::class, 'getAvailableMenuItems']);
        Route::post('/roles/{id}/permissions', [RoleController::class, 'updatePermissions']);
        Route::get('/roles/{id}/permissions', [RoleController::class, 'getPermissions']);
        
        // Permission Management
        Route::apiResource('permissions', PermissionController::class);
        Route::get('/permissions-grouped', [PermissionController::class, 'grouped']);
        Route::get('/permissions-matrix', [PermissionController::class, 'matrix']);
        Route::post('/permissions/{role}/sync', [PermissionController::class, 'syncRolePermissions']);
        
        // Lookup Management (Admin only)
        Route::apiResource('admin/lookups', LookupController::class);
        Route::post('/admin/lookups/bulk-upsert', [LookupController::class, 'bulkUpsert']);
        
        // KBase document sharing (agent → customer via signed URL)
        Route::post('/kbase/share', [KbaseShareController::class, 'share']);

        // Knowledge Base Management (Admin/Agent)
        Route::apiResource('knowledge-base', KnowledgeBaseController::class);
        Route::post('/knowledge-base/bulk-import', [KnowledgeBaseController::class, 'bulkImport']);
        Route::get('/knowledge-base-statistics', [KnowledgeBaseController::class, 'statistics']);
        Route::get('/knowledge-base/aggregate/stats', [KnowledgeBaseController::class, 'aggregate']);
        Route::get('/knowledge-base/views/recent', [KnowledgeBaseController::class, 'recentViews']);
        Route::post('/knowledge-base/{id}/view', [KnowledgeBaseController::class, 'incrementView']);

        // Elasticsearch/OpenSearch Knowledge Base Article API
        Route::prefix('knowledge-base-articles')->group(function () {
            Route::get('/search', [\App\Http\Controllers\Api\KnowledgeBaseArticleController::class, 'search']);
            Route::get('/stats', [\App\Http\Controllers\Api\KnowledgeBaseArticleController::class, 'stats']);
            Route::get('/', [\App\Http\Controllers\Api\KnowledgeBaseArticleController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\KnowledgeBaseArticleController::class, 'store']);
            Route::get('/{id}', [\App\Http\Controllers\Api\KnowledgeBaseArticleController::class, 'show']);
            Route::put('/{id}', [\App\Http\Controllers\Api\KnowledgeBaseArticleController::class, 'update']);
            Route::post('/{id}', [\App\Http\Controllers\Api\KnowledgeBaseArticleController::class, 'update']); // For multipart form data
            Route::post('/{id}/publish', [\App\Http\Controllers\Api\KnowledgeBaseArticleController::class, 'publish']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\KnowledgeBaseArticleController::class, 'destroy']);
        });
        
        // Mitel/MiContact Center Integration (via Service Layer)
        Route::prefix('mitel')->group(function () {
            
            // Employee Management (General Mitel API endpoints from MitelController)
            Route::prefix('employees')->group(function () {
                Route::get('/', [MitelController::class, 'getEmployees']); // Get all employees (OData)
                Route::get('/{id}', [MitelController::class, 'getEmployee']); // Get employee by ID, username, or 'me'
                Route::get('/{id}/state', [MitelController::class, 'getEmployeeState']); // Get employee state
                Route::put('/{id}/state', [MitelController::class, 'updateEmployeeState']); // Update employee state
            });
            
            // Agent Management (Existing MitelAgentController)
            Route::get('/agents', [MitelAgentController::class, 'index']);
            Route::get('/agents/{agentId}/state', [MitelAgentController::class, 'getState']);
            Route::put('/agents/{agentId}/state', [MitelAgentController::class, 'updateState']);
            Route::get('/agents/{agentId}/statistics', [MitelAgentController::class, 'getStatistics']);
            
            // Agent Status (Specific Agent methods from MitelController)
            Route::get('/agents/{agentId}/status', [MitelController::class, 'getAgentStatus']); // GET /api/v1/mitel/agents/{agentId}/status
            Route::post('/agents/{agentId}/status', [MitelController::class, 'setAgentStatus']); // POST /api/v1/mitel/agents/{agentId}/status
            Route::get('/agents/{agentId}/stats', [MitelController::class, 'getAgentStats']); // GET /api/v1/mitel/agents/{agentId}/stats
            
            // Call/Conversation Management
            Route::get('/agents/{agentId}/calls', [MitelCallController::class, 'getActiveCalls']);
            Route::post('/agents/{agentId}/calls', [MitelCallController::class, 'makeCall']);
            Route::post('/agents/{agentId}/calls/{callId}/answer', [MitelCallController::class, 'answerCall']);
            Route::post('/agents/{agentId}/calls/{callId}/hold', [MitelCallController::class, 'holdCall']);
            Route::post('/agents/{agentId}/calls/{callId}/resume', [MitelCallController::class, 'resumeCall']);

            // Transfer endpoints
            Route::post('/agents/{agentId}/calls/{callId}/transfer/blind', [MitelCallController::class, 'blindTransferCall']);
            Route::post('/agents/{agentId}/calls/{callId}/transfer/consult/initiate', [MitelCallController::class, 'initiateConsultTransfer']);
            Route::post('/agents/{agentId}/calls/{callId}/transfer/consult/complete', [MitelCallController::class, 'completeConsultTransfer']);
            Route::post('/agents/{agentId}/calls/{callId}/transfer/consult/cancel', [MitelCallController::class, 'cancelConsultTransfer']);

            Route::post('/agents/{agentId}/calls/{callId}/end', [MitelCallController::class, 'endCall']);
            Route::get('/agents/{agentId}/calls/history', [MitelCallController::class, 'getCallHistory']);

            // Agent & Softphone endpoints
            Route::get('/agents/{agentId}/agentgrouppresence', [MitelCallController::class, 'getAgentGroupPresence']);
            Route::get('/agents/{agentId}/integratedsoftphone', [MitelCallController::class, 'getIntegratedSoftphoneCredentials']);
            Route::get('/agents/{agentId}/busyreasoncodes', [MitelCallController::class, 'getBusyReasonCodes']);

            // Directory Search
            Route::get('/directory/search', [MitelCallController::class, 'searchDirectory']);

            // Outbound Calls
            Route::post('/employees/{employeeId}/conversations', [MitelCallController::class, 'makeOutboundCall']);

            // Queue Monitoring
            Route::get('/queues', [MitelQueueController::class, 'index']);
            Route::get('/queues/{queueId}/statistics', [MitelQueueController::class, 'getStatistics']);

            // OpenMedia (external/chat interactions)
            Route::get('/openmedia/queues', [MitelOpenMediaController::class, 'queues']);
            Route::get('/openmedia', [MitelOpenMediaController::class, 'index']);
            Route::get('/openmedia/{id}', [MitelOpenMediaController::class, 'show']);
            Route::put('/openmedia/{id}', [MitelOpenMediaController::class, 'update']);

            // MCX Open Media v2 — email interaction management
            Route::prefix('mcx-email')->group(function () {
                Route::get('/{ticketId}/status', [McxEmailInteractionController::class, 'status']);
                Route::post('/{ticketId}/send', [McxEmailInteractionController::class, 'send']);
                Route::post('/{ticketId}/close', [McxEmailInteractionController::class, 'close']);
            });
        });

        // Mitel Configuration Management (Admin only)
        Route::prefix('mitel-config')->group(function () {
            Route::get('/{key}', [MitelConfigurationController::class, 'show']);
            Route::put('/{key}', [MitelConfigurationController::class, 'update']);
        });

        // Superset Configuration Management (Admin only)
        Route::prefix('superset-config')->group(function () {
            Route::get('/admin', [SupersetConfigurationController::class, 'adminIndex']);
            Route::put('/{key}', [SupersetConfigurationController::class, 'update']);
        });

        // Mitel Connection Management (Admin only)
        Route::prefix('mitel-connections')->group(function () {
            Route::get('/', [MitelConnectionController::class, 'index']);
            Route::get('/{id}', [MitelConnectionController::class, 'show']);
            Route::post('/', [MitelConnectionController::class, 'store']);
            Route::put('/{id}', [MitelConnectionController::class, 'update']);
            Route::delete('/{id}', [MitelConnectionController::class, 'destroy']);
            Route::post('/{id}/test', [MitelConnectionController::class, 'test']);
            Route::post('/{id}/sync', [MitelConnectionController::class, 'sync']);
            Route::get('/{id}/logs', [MitelConnectionController::class, 'logs']);
            Route::get('/{id}/stats', [MitelConnectionController::class, 'stats']);
        });
        
        // Mitel Webhook - Receives real-time events from Mitel system
        Route::post('/mitel/webhook', [MitelWebhookController::class, 'handle']);
        
        // WebSocket Management & Testing
        Route::prefix('websocket')->group(function () {
            Route::get('/info', [WebSocketController::class, 'getConnectionInfo']);
            Route::get('/health', [WebSocketController::class, 'healthCheck']);
            Route::get('/channels', [WebSocketController::class, 'getActiveChannels']);
            Route::post('/test', [WebSocketController::class, 'testBroadcast']);
            Route::post('/broadcast', [WebSocketController::class, 'broadcastCustomEvent']);
        });

        // Superset Embedded Dashboard Integration
        Route::prefix('superset')->group(function () {
            // Guest token generation
            Route::post('/guest-token', [SupersetController::class, 'getGuestToken']);
            Route::get('/dashboards', [SupersetController::class, 'getDashboards']);
            // PDF report export (proxied through Laravel)
            Route::post('/export-pdf', [SupersetController::class, 'exportPdf']);

            // Dashboard management (CRUD)
            Route::apiResource('dashboard-configs', SupersetDashboardController::class);

            // Custom dashboard endpoints
            Route::get('/my-dashboards', [SupersetDashboardController::class, 'myDashboards']);
            Route::get('/categories', [SupersetDashboardController::class, 'categories']);
            Route::post('/dashboard-configs/{id}/assign-roles', [SupersetDashboardController::class, 'assignRoles']);
        });
    });
});