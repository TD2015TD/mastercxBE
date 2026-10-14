<?php

namespace App\Http\Controllers\Api;

use App\Events\SignalingEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SignalingController extends Controller
{
    /**
     * Lightweight, permission-free user search for picking a call target.
     * Deliberately separate from UserController::index (gated behind
     * user.manage) — any logged-in agent needs to find a colleague to call,
     * not manage accounts, so this only returns id/name/role, nothing else.
     */
    public function directory(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = User::with('role')
            ->where('status', 'active')
            ->where('id', '!=', Auth::id());

        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $users = $query
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'role_id'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role?->name,
            ]);

        return response()->json(['success' => true, 'data' => $users]);
    }

    /**
     * Relay a WebRTC signaling message (call-request, offer, answer,
     * ice-candidate, hangup, reject) to a specific target user.
     */
    public function send(Request $request, int $targetUserId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:call-request,offer,answer,ice-candidate,hangup,reject',
            'payload' => 'present|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        // Temporary diagnostic — an offer/answer SDP is arriving at the
        // browser with a line boundary lost somewhere in transport. Log the
        // raw byte length and a JSON-encoded (escape-visible) dump of the
        // sdp field exactly as Laravel received it, so we can tell whether
        // the corruption happens before this point or after (in the
        // Reverb/Pusher broadcast step).
        if (in_array($request->input('type'), ['offer', 'answer'], true)) {
            $sdp = $request->input('payload.sdp');
            Log::info('[SignalingController] raw sdp received', [
                'type' => $request->input('type'),
                'sdp_length' => is_string($sdp) ? strlen($sdp) : null,
                'sdp_line_count' => is_string($sdp) ? substr_count($sdp, "\n") : null,
                'sdp_json' => json_encode($sdp),
            ]);
        }

        event(new SignalingEvent(
            targetUserId: $targetUserId,
            fromUserId: $user->id,
            fromUserName: $user->name,
            type: $request->input('type'),
            payload: $request->input('payload'),
        ));

        return response()->json(['success' => true]);
    }
}
