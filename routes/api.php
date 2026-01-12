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
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\Mitel\CallController as MitelCallController;
use App\Http\Controllers\Api\Mitel\QueueController as MitelQueueController;
use App\Http\Controllers\Api\Mitel\WebhookController as MitelWebhookController;
use App\Http\Controllers\Api\Mitel\AgentController as MitelAgentController;
use App\Http\Controllers\Api\WebSocketController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PublicTicketController;
use App\Http\Controllers\Api\PublicKnowledgeBaseController;
use App\Http\Controllers\Api\KnowledgeBaseController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\MitelController; // <--- ADDED: New Mitel Controller
use App\Http\Controllers\Api\SystemConfigurationController;
use App\Http\Controllers\Api\SupersetController;
use App\Http\Controllers\Api\SupersetDashboardController;
use App\Http\Controllers\MitelConfigurationController;
use App\Http\Controllers\MitelConnectionController;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    
    // Public Ticket Submission (Citizen Portal - No Auth Required)
    Route::prefix('public')->group(function () {
        // Public Authentication (Phone + Email OTP)
        Route::post('/auth/request-otp', [App\Http\Controllers\Api\PublicAuthController::class, 'requestOtp']);
        Route::post('/auth/verify-otp', [App\Http\Controllers\Api\PublicAuthController::class, 'verifyOtp']);
        
        // Public Ticket APIs
        Route::post('/tickets', [PublicTicketController::class, 'submitTicket']);
        Route::post('/tickets/track', [PublicTicketController::class, 'trackTicket']);
        
        // Authenticated Citizen Routes (requires session token)
        Route::middleware('citizen.session')->group(function () {
            Route::get('/tickets/my-tickets', [PublicTicketController::class, 'getMyTickets']);
        });
        
        // Route with parameter must come after specific routes
        Route::get('/tickets/{ticketNumber}', [PublicTicketController::class, 'viewTicket']);
        Route::post('/tickets/{ticketNumber}/attachments', [PublicTicketController::class, 'uploadAttachment']);
        
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
    Route::get('/mitel-config', [MitelConfigurationController::class, 'index']);

    // Protected routes (with auto-refresh for call center agents)
    Route::middleware(['jwt.verify', 'jwt.auto.refresh'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        
        // User Management
        Route::apiResource('users', UserController::class);
        Route::put('/users/profile', [UserController::class, 'updateProfile']);
        Route::post('/users/change-password', [UserController::class, 'changePassword']);
        Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
        
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
        Route::put('/tickets/{id}/assign', [TicketController::class, 'assign']);
        Route::put('/tickets/{id}/status', [TicketController::class, 'updateStatus']);
        Route::post('/tickets/{id}/resolve', [TicketController::class, 'resolve']);
        Route::post('/tickets/{id}/close', [TicketController::class, 'close']);
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
        
        // Reports & Metrics (for Superset/BI integration)
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/agent-metrics', [ReportController::class, 'getAgentMetrics']);
        Route::get('/reports/supervisor-metrics', [ReportController::class, 'getSupervisorMetrics']);
        Route::get('/reports/ticket-volume', [ReportController::class, 'getTicketVolumeMetrics']);
        Route::get('/reports/sla-compliance', [ReportController::class, 'getSlaComplianceMetrics']);
        Route::get('/reports/customer-satisfaction', [ReportController::class, 'getCustomerSatisfactionMetrics']);
        
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
        
        // System Administration
        Route::prefix('system')->group(function () {
            Route::get('/health', [SystemController::class, 'getSystemHealth']);
            Route::get('/services', [SystemController::class, 'getServiceStatus']);
            Route::get('/alerts', [SystemController::class, 'getSystemAlerts']);
            Route::post('/alerts/{id}/acknowledge', [SystemController::class, 'acknowledgeAlert']);
            Route::post('/health-check', [SystemController::class, 'performHealthCheck']);
            Route::post('/services/{serviceName}/restart', [SystemController::class, 'restartService']);

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
        
        // Lookup Management (Admin only)
        Route::apiResource('admin/lookups', LookupController::class);
        Route::post('/admin/lookups/bulk-upsert', [LookupController::class, 'bulkUpsert']);
        
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
        });

        // Mitel Configuration Management (Admin only)
        Route::prefix('mitel-config')->group(function () {
            Route::get('/{key}', [MitelConfigurationController::class, 'show']);
            Route::put('/{key}', [MitelConfigurationController::class, 'update']);
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

            // Dashboard management (CRUD)
            Route::apiResource('dashboard-configs', SupersetDashboardController::class);

            // Custom dashboard endpoints
            Route::get('/my-dashboards', [SupersetDashboardController::class, 'myDashboards']);
            Route::get('/categories', [SupersetDashboardController::class, 'categories']);
            Route::post('/dashboard-configs/{id}/assign-roles', [SupersetDashboardController::class, 'assignRoles']);
        });
    });
});