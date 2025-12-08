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

    // Protected routes
    Route::middleware('jwt.verify')->group(function () {
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
        Route::apiResource('tickets', TicketController::class);
        Route::get('/tickets/{id}/history', [TicketController::class, 'history']);
        Route::put('/tickets/{id}/assign', [TicketController::class, 'assign']);
        Route::put('/tickets/{id}/status', [TicketController::class, 'updateStatus']);
        Route::post('/tickets/{id}/resolve', [TicketController::class, 'resolve']);
        Route::post('/tickets/{id}/close', [TicketController::class, 'close']);
        Route::post('/tickets/{id}/reopen', [TicketController::class, 'reopen']);
        Route::post('/tickets/{id}/response', [TicketController::class, 'recordResponse']);
        Route::get('/tickets/statistics/summary', [TicketController::class, 'statistics']);
        
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
        });
        
        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/notifications', [NotificationController::class, 'clearAll']);
        
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
        
        // Mitel/MiContact Center Integration (via Service Layer)
        Route::prefix('mitel')->group(function () {
            // Agent Management
            Route::get('/agents', [MitelAgentController::class, 'index']);
            Route::get('/agents/{agentId}/state', [MitelAgentController::class, 'getState']);
            Route::put('/agents/{agentId}/state', [MitelAgentController::class, 'updateState']);
            Route::get('/agents/{agentId}/statistics', [MitelAgentController::class, 'getStatistics']);
            
            // Call/Conversation Management
            Route::get('/agents/{agentId}/calls', [MitelCallController::class, 'getActiveCalls']);
            Route::post('/agents/{agentId}/calls', [MitelCallController::class, 'makeCall']);
            Route::post('/agents/{agentId}/calls/{callId}/answer', [MitelCallController::class, 'answerCall']);
            Route::post('/agents/{agentId}/calls/{callId}/hold', [MitelCallController::class, 'holdCall']);
            Route::post('/agents/{agentId}/calls/{callId}/resume', [MitelCallController::class, 'resumeCall']);
            Route::post('/agents/{agentId}/calls/{callId}/transfer', [MitelCallController::class, 'transferCall']);
            Route::post('/agents/{agentId}/calls/{callId}/end', [MitelCallController::class, 'endCall']);
            Route::get('/agents/{agentId}/calls/history', [MitelCallController::class, 'getCallHistory']);
            
            // Queue Monitoring
            Route::get('/queues', [MitelQueueController::class, 'index']);
            Route::get('/queues/{queueId}/statistics', [MitelQueueController::class, 'getStatistics']);
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
    });
});
