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
        ];
        
        return $titles[$type][$level] ?? $titles[$type]['default'] ?? 'Notification';
    }
}
