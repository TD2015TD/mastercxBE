<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketNumberSequenceController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SlaConfigurationController;
use App\Http\Controllers\Api\EscalationController;
use App\Http\Controllers\Api\CustomerInteractionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\LanguageController;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    
    // Language routes (public)
    Route::get('/languages', [LanguageController::class, 'getLanguages']);
    Route::get('/translations/{locale?}', [LanguageController::class, 'getTranslations']);
    Route::get('/translations', [LanguageController::class, 'getAllTranslations']);

    // Lookups (public - for dropdowns and enums with bilingual support)
    Route::get('/lookups', [LookupController::class, 'index']);
    Route::get('/lookups/types', [LookupController::class, 'types']);

    // Protected routes
    Route::middleware('jwt.verify')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        
        // Agent Management
        Route::apiResource('agents', AgentController::class);
        Route::put('/agents/{id}/availability', [AgentController::class, 'updateAvailability']);
        Route::get('/agents/{id}/metrics', [AgentController::class, 'metrics']);
        
        // Customer Management
        Route::apiResource('customers', CustomerController::class);
        Route::post('/customers/search', [CustomerController::class, 'search']);
        Route::get('/customers/{id}/statistics', [CustomerController::class, 'statistics']);
        
        // Ticket Management
        Route::apiResource('tickets', TicketController::class);
        Route::put('/tickets/{id}/assign', [TicketController::class, 'assign']);
        Route::put('/tickets/{id}/status', [TicketController::class, 'updateStatus']);
        Route::post('/tickets/{id}/resolve', [TicketController::class, 'resolve']);
        Route::post('/tickets/{id}/close', [TicketController::class, 'close']);
        Route::post('/tickets/{id}/reopen', [TicketController::class, 'reopen']);
        Route::post('/tickets/{id}/response', [TicketController::class, 'recordResponse']);
        Route::get('/tickets/statistics/summary', [TicketController::class, 'statistics']);
        
        // Ticket Number Sequence Management (Super Admin only)
        Route::apiResource('ticket-sequences', TicketNumberSequenceController::class);
        Route::post('/ticket-sequences/{id}/reset', [TicketNumberSequenceController::class, 'reset']);
        Route::get('/ticket-sequences/{id}/preview', [TicketNumberSequenceController::class, 'preview']);
        
        // Category Management
        Route::apiResource('categories', CategoryController::class);
        Route::get('/categories/tree/view', [CategoryController::class, 'tree']);
        Route::get('/categories/department-level/filter', [CategoryController::class, 'byDepartmentLevel']);
        
        // SLA Configuration Management
        Route::apiResource('sla-configurations', SlaConfigurationController::class);
        Route::get('/sla-configurations/get/by-ticket', [SlaConfigurationController::class, 'getSla']);
        Route::get('/sla-configurations/grouped/by-department', [SlaConfigurationController::class, 'groupedByDepartment']);
        
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
        
        // Lookup Management (Admin only)
        Route::apiResource('admin/lookups', LookupController::class);
        Route::post('/admin/lookups/bulk-upsert', [LookupController::class, 'bulkUpsert']);
        
        // Add your other protected API routes here
        Route::get('/users', function () {
            return response()->json(['message' => 'Users endpoint']);
        });
    });
});
