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
        $query = CustomerInteraction::with(['customer', 'ticket', 'agent']);

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
            'customer_id' => 'required|exists:customers,id',
            'ticket_id' => 'nullable|exists:tickets,id',
            'agent_id' => 'nullable|exists:agents,id',
            'interaction_type' => 'required|in:call,email,chat,sms,meeting,note',
            'channel' => 'required|in:phone,email,chat,web,walk-in,social_media',
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
        $data['agent_id'] = $data['agent_id'] ?? auth()->user()->agent->id ?? null;
        $data['interaction_at'] = $data['interaction_at'] ?? now();

        $interaction = CustomerInteraction::create($data);

        // Update customer's last contact timestamp
        $customer = Customer::find($request->customer_id);
        $customer->last_contact_at = $interaction->interaction_at;
        $customer->save();

        $interaction->load(['customer', 'ticket', 'agent']);

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
        $interaction = CustomerInteraction::with(['customer', 'ticket', 'agent'])->findOrFail($id);

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
        $interaction->load(['customer', 'ticket', 'agent']);

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
            ->with(['ticket', 'agent'])
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
