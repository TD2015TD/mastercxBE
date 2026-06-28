<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatbotSession;
use App\Models\ChatMessage;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatbotHealthController extends Controller
{
    /**
     * GET /api/v1/admin/chatbot/health
     *
     * Returns aggregated reporting data for the Chatbot/Livechat Health dashboard.
     * Restricted to admin and super_admin roles via route middleware.
     *
     * Query params:
     *   date_from  string  ISO date (default: 30 days ago)
     *   date_to    string  ISO date (default: today)
     */
    public function report(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from')
                ? \Carbon\Carbon::parse($request->get('date_from'))->startOfDay()
                : now()->subDays(30)->startOfDay();

            $dateTo = $request->get('date_to')
                ? \Carbon\Carbon::parse($request->get('date_to'))->endOfDay()
                : now()->endOfDay();

            // ── Chatbot Session Stats ──────────────────────────────────────────
            $sessionQuery = ChatbotSession::whereBetween('created_at', [$dateFrom, $dateTo]);

            $totalSessions     = (clone $sessionQuery)->count();
            $queuingSessions   = (clone $sessionQuery)->where('status', 'queuing')->count();
            $activeSessions    = (clone $sessionQuery)->where('status', 'active')->count();
            $closedSessions    = (clone $sessionQuery)->where('status', 'closed')->count();
            $abandonedSessions = (clone $sessionQuery)->where('status', 'abandoned')->count();
            $escalatedSessions = (clone $sessionQuery)->whereNotNull('agent_id')->count();

            $escalationRate = $totalSessions > 0
                ? round(($escalatedSessions / $totalSessions) * 100, 1)
                : 0;

            // Sessions today (all-time, no date filter)
            $todaySessions = ChatbotSession::whereDate('created_at', today())->count();

            // Avg session duration for ALL closed sessions (created_at → updated_at)
            $avgSessionMins = ChatbotSession::whereBetween('created_at', [$dateFrom, $dateTo])
                ->where('status', 'closed')
                ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 60) as avg_mins'))
                ->value('avg_mins');

            // Avg handling time for escalated sessions only (agent accepted → session closed)
            // created_at = citizen escalated, updated_at = session closed, agent_id = agent who handled
            $avgEscalatedMins = ChatbotSession::whereBetween('created_at', [$dateFrom, $dateTo])
                ->where('status', 'closed')
                ->whereNotNull('agent_id')
                ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 60) as avg_mins'))
                ->value('avg_mins');

            // ── Top 10 Topics ─────────────────────────────────────────────────
            $topTopics = ChatbotSession::whereBetween('created_at', [$dateFrom, $dateTo])
                ->whereNotNull('topic')
                ->select('topic', DB::raw('COUNT(*) as count'))
                ->groupBy('topic')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(fn($r) => ['topic' => $r->topic, 'count' => $r->count]);

            // ── Sessions by Day (trend) ───────────────────────────────────────
            $dailyTrend = ChatbotSession::whereBetween('created_at', [$dateFrom, $dateTo])
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN agent_id IS NOT NULL THEN 1 ELSE 0 END) as escalated')
                )
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get()
                ->map(fn($r) => [
                    'date'      => $r->date,
                    'total'     => (int) $r->total,
                    'escalated' => (int) $r->escalated,
                ]);

            // ── Recent Sessions (last 20) ────────────────────────────────────
            $recentSessions = ChatbotSession::orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn($s) => [
                    'interaction_id' => $s->interaction_id,
                    'customer_name'  => $s->customer_name ?? 'Anonymous',
                    'topic'          => $s->topic,
                    'status'         => $s->status,
                    'agent_name'     => $s->agent_name,
                    'created_at'     => $s->created_at?->toISOString(),
                    'updated_at'     => $s->updated_at?->toISOString(),
                    'queue_name'     => $s->queue_name,
                ]);

            // ── Livechat CRM Ticket Stats ─────────────────────────────────────
            $livechatBase = Ticket::where(function ($q) {
                $q->where('channel', 'livechat')
                  ->orWhere('category', 'LiveChat')
                  ->orWhere('category', 'Live Chat');
            })->whereBetween('created_at', [$dateFrom, $dateTo]);

            $totalLivechatTickets  = (clone $livechatBase)->count();
            $resolvedTickets       = (clone $livechatBase)->whereIn('status', ['Resolved', 'Closed'])->count();
            $openTickets           = (clone $livechatBase)->whereNotIn('status', ['Resolved', 'Closed'])->count();
            $ticketResolutionRate  = $totalLivechatTickets > 0
                ? round(($resolvedTickets / $totalLivechatTickets) * 100, 1)
                : 0;

            // Livechat avg resolution = avg escalated session duration (escalation time → session close)
            // Pulled from chatbot_sessions where agent handled the session, not from CRM tickets
            $avgResolutionMins = $avgEscalatedMins;

            // ── Chat Message Volume (agent messages only) ─────────────────────
            $totalMessages = ChatMessage::whereBetween('created_at', [$dateFrom, $dateTo])
                ->where('sender', 'agent')
                ->count();

            // ── Integration Status ────────────────────────────────────────────
            // TCP socket probe: try port 80 first (service may not have SSL), then scheme default.
            $chatServiceUrl = config('services.chat.agent_url', 'http://chat.telediant.com.my');
            $chatServiceReachable = false;
            try {
                $parsed        = parse_url($chatServiceUrl);
                $host          = $parsed['host'] ?? 'chat.telediant.com.my';
                $schemeDefault = (($parsed['scheme'] ?? 'http') === 'https') ? 443 : 80;
                $explicitPort  = $parsed['port'] ?? null;
                // Try the ports in order: explicit → 80 → scheme default
                $portsToTry = array_unique(array_filter([$explicitPort, 80, $schemeDefault]));
                foreach ($portsToTry as $port) {
                    $fp = @fsockopen($host, $port, $errno, $errstr, 3);
                    if ($fp !== false) {
                        fclose($fp);
                        $chatServiceReachable = true;
                        break;
                    }
                }
            } catch (\Throwable) {
                // unreachable — leave as false
            }

            $dbConnected = true; // if we got this far, DB is connected

            return response()->json([
                'success' => true,
                'data'    => [
                    'period' => [
                        'from' => $dateFrom->toDateString(),
                        'to'   => $dateTo->toDateString(),
                    ],
                    'chatbot' => [
                        'total_sessions'     => $totalSessions,
                        'today_sessions'     => $todaySessions,
                        'queuing'            => $queuingSessions,
                        'active'             => $activeSessions,
                        'closed'             => $closedSessions,
                        'abandoned'          => $abandonedSessions,
                        'escalated'          => $escalatedSessions,
                        'escalation_rate'    => $escalationRate,
                        'avg_session_mins'      => $avgSessionMins ? round((float) $avgSessionMins, 1) : null,
                        'avg_escalated_mins'    => $avgEscalatedMins ? round((float) $avgEscalatedMins, 1) : null,
                        'total_messages'     => $totalMessages,
                        'top_topics'         => $topTopics,
                        'daily_trend'        => $dailyTrend,
                        'recent_sessions'    => $recentSessions,
                    ],
                    'livechat' => [
                        'total_tickets'      => $totalLivechatTickets,
                        'resolved_tickets'   => $resolvedTickets,
                        'open_tickets'       => $openTickets,
                        'resolution_rate'    => $ticketResolutionRate,
                        'avg_resolution_mins'=> $avgResolutionMins ? round((float) $avgResolutionMins, 1) : null,
                    ],
                    'integration' => [
                        'database'     => $dbConnected ? 'connected' : 'error',
                        'chat_service' => $chatServiceReachable ? 'connected' : 'unreachable',
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('ChatbotHealth report failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load health report.',
            ], 500);
        }
    }
}
