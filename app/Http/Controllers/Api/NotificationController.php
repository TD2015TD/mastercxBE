<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get notifications for authenticated user
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = $user->notifications();
        
        // Filter by read/unread status
        if ($request->has('unread_only') && $request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }
        
        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        // Limit results
        $limit = $request->get('limit', 50);
        
        $notifications = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($notification) {
                $data = $notification->data;
                
                return [
                    'id' => $notification->id,
                    'type' => $data['type'] ?? 'general',
                    'level' => $data['level'] ?? 'info',
                    'title' => $this->generateTitle($data),
                    'message' => $data['message'] ?? '',
                    'ticket_id' => $data['ticket_id'] ?? null,
                    'ticket_number' => $data['ticket_number'] ?? null,
                    'priority' => $data['priority'] ?? null,
                    'department' => $data['department'] ?? null,
                    'url' => $data['url'] ?? null,
                    'read' => $notification->read_at !== null,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'timestamp' => $notification->created_at->toIso8601String(),
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->find($id);
        
        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }
        
        $notification->markAsRead();
        
        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();
        
        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }
    
    /**
     * Delete notification
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->find($id);
        
        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        }
        
        $notification->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Notification deleted'
        ]);
    }
    
    /**
     * Clear all notifications
     */
    public function clearAll(Request $request)
    {
        $user = Auth::user();
        $user->notifications()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'All notifications cleared'
        ]);
    }
    
    /**
     * Get unread count
     */
    public function unreadCount(Request $request)
    {
        $user = Auth::user();
        
        return response()->json([
            'success' => true,
            'count' => $user->unreadNotifications()->count()
        ]);
    }
    
    /**
     * Generate notification title based on type
     */
    private function generateTitle(array $data): string
    {
        $type = $data['type'] ?? 'general';
        $level = $data['level'] ?? 'info';

        $titles = [
            'sla_alert' => [
                'warning' => 'SLA Warning',
                'critical' => 'SLA Breach Alert',
                'info' => 'SLA Notice',
            ],
            'agent_assistance' => [
                'default' => 'Agent Assistance Request',
            ],
            'system_alert' => [
                'default' => 'System Alert',
            ],
            'workload_warning' => [
                'default' => 'Workload Warning',
            ],
            'ticket_assigned' => [
                'default' => 'Ticket Assigned',
            ],
            'ticket_escalated' => [
                'default' => 'Ticket Escalated',
            ],
            'email_ticket_created' => [
                'default' => 'New Email Ticket',
            ],
            'email_ticket_reply' => [
                'default' => 'Email Reply Received',
            ],
        ];

        return $titles[$type][$level] ?? $titles[$type]['default'] ?? 'Notification';
    }

    /**
     * Create notification for email ticket.
     * This is used by the email processing service.
     */
    public function createEmailNotification(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'ticket_id' => 'required|exists:tickets,id',
            'ticket_number' => 'required|string',
            'type' => 'required|in:email_ticket_created,email_ticket_reply',
            'message' => 'required|string',
            'priority' => 'nullable|string',
            'department' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = \App\Models\User::find($request->user_id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Create database notification
            $notification = $user->notifications()->create([
                'id' => \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Notifications\\EmailTicketNotification',
                'data' => [
                    'type' => $request->type,
                    'level' => 'info',
                    'message' => $request->message,
                    'ticket_id' => $request->ticket_id,
                    'ticket_number' => $request->ticket_number,
                    'priority' => $request->priority,
                    'department' => $request->department,
                    // Actual frontend route is "/ticket-management/:id" (Routes.tsx) —
                    // "/tickets/..." isn't a real route, same defect as
                    // EscalationNotification/SlaAlertNotification.
                    'url' => "/ticket-management/{$request->ticket_id}",
                ],
                'read_at' => null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email notification created successfully',
                'data' => $notification
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
