<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerInteraction;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerInteractionController extends Controller
{
    /**
     * Display a listing of customer interactions.
     */
    public function index(Request $request)
    {
        $query = CustomerInteraction::with(['customer', 'ticket', 'agent', 'interactionTypeLookup']);

        // Filter by customer
        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        // Filter by interaction type
        if ($request->has('interaction_type')) {
            $query->byType($request->interaction_type);
        }

        // Filter by channel
        if ($request->has('channel')) {
            $query->byChannel($request->channel);
        }

        // Filter by agent
        if ($request->has('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        }

        // Filter by conversation ID
        if ($request->has('conversation_id')) {
            $query->byConversation($request->conversation_id);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('interaction_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('interaction_at', '<=', $request->end_date);
        }

        // Recent filter
        if ($request->has('recent_days')) {
            $query->recent($request->recent_days);
        }

        $perPage = $request->get('per_page', 20);
        $interactions = $query->orderBy('interaction_at', 'desc')->paginate($perPage);

        return response()->json($interactions);
    }

    /**
     * Store a newly created interaction.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'nullable|exists:customers,id',
            'ticket_id' => 'nullable|exists:tickets,id',
            'agent_id' => 'nullable|exists:agents,id',
            'conversation_id' => 'nullable|string|max:100',
            'interaction_type' => 'required|in:' . implode(',', CustomerInteraction::INTERACTION_TYPES),
            'interaction_type_id' => 'nullable|exists:lookups,id',
            'channel' => 'required|in:' . implode(',', CustomerInteraction::CHANNELS),
            'subject' => 'required|string|max:500',
            'description' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:0',
            'sentiment' => 'nullable|in:positive,neutral,negative',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
            'interaction_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();

        // Try to get agent_id from request, auth user, or set to null
        if (!isset($data['agent_id'])) {
            $user = auth()->user();
            if ($user && $user->agent) {
                $data['agent_id'] = $user->agent->id;
            } else {
                $data['agent_id'] = null;
            }
        }

        $data['interaction_at'] = $data['interaction_at'] ?? now();

        $interaction = CustomerInteraction::create($data);

        // Update customer's last contact timestamp (only if customer_id is provided)
        if ($request->customer_id) {
            $customer = Customer::find($request->customer_id);
            if ($customer) {
                $customer->last_contact_at = $interaction->interaction_at;
                $customer->save();
            }
        }

        $interaction->load(['customer', 'ticket', 'agent', 'interactionTypeLookup']);

        return response()->json([
            'success' => true,
            'message' => 'Interaction logged successfully',
            'data' => $interaction
        ], 201);
    }

    /**
     * Display the specified interaction.
     */
    public function show($id)
    {
        $interaction = CustomerInteraction::with(['customer', 'ticket', 'agent', 'interactionTypeLookup'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $interaction
        ]);
    }

    /**
     * Update the specified interaction.
     */
    public function update(Request $request, $id)
    {
        $interaction = CustomerInteraction::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'ticket_id' => 'nullable|exists:tickets,id',
            'interaction_type' => 'in:call,email,chat,sms,meeting,note',
            'interaction_type_id' => 'nullable|exists:lookups,id',
            'channel' => 'in:phone,email,chat,web,walk-in,social_media',
            'subject' => 'string|max:500',
            'description' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:0',
            'sentiment' => 'nullable|in:positive,neutral,negative',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $interaction->update($request->all());
        $interaction->load(['customer', 'ticket', 'agent', 'interactionTypeLookup']);

        return response()->json([
            'success' => true,
            'message' => 'Interaction updated successfully',
            'data' => $interaction
        ]);
    }

    /**
     * Remove the specified interaction.
     */
    public function destroy($id)
    {
        $interaction = CustomerInteraction::findOrFail($id);
        $interaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Interaction deleted successfully'
        ]);
    }

    /**
     * Get customer 360 timeline.
     */
    public function customerTimeline($customerId)
    {
        $customer = Customer::findOrFail($customerId);

        $interactions = CustomerInteraction::byCustomer($customerId)
            ->with(['ticket', 'agent', 'interactionTypeLookup'])
            ->orderBy('interaction_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => $customer,
                'interactions' => $interactions,
                'total_interactions' => $interactions->count(),
                'by_type' => $interactions->groupBy('interaction_type')->map->count(),
                'by_channel' => $interactions->groupBy('channel')->map->count(),
            ]
        ]);
    }
}
