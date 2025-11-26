<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketNumberSequence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TicketNumberSequenceController extends Controller
{
    /**
     * Display a listing of ticket number sequences.
     */
    public function index()
    {
        $sequences = TicketNumberSequence::orderBy('is_active', 'desc')
            ->orderBy('name')
            ->get();

        // Update sample output for each sequence
        foreach ($sequences as $sequence) {
            $sequence->sample_output = $sequence->generateSample();
        }

        return response()->json([
            'success' => true,
            'data' => $sequences,
        ]);
    }

    /**
     * Store a newly created ticket number sequence.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:ticket_number_sequences,name|max:50',
            'prefix' => 'required|string|max:10',
            'pattern' => 'required|string|max:100',
            'current_number' => 'nullable|integer|min:0',
            'padding' => 'required|integer|min:1|max:10',
            'reset_daily' => 'nullable|boolean',
            'reset_monthly' => 'nullable|boolean',
            'reset_yearly' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $sequence = TicketNumberSequence::create($validator->validated());
            $sequence->updateSampleOutput();

            return response()->json([
                'success' => true,
                'message' => 'Ticket number sequence created successfully',
                'data' => $sequence,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket number sequence',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified ticket number sequence.
     */
    public function show(string $id)
    {
        $sequence = TicketNumberSequence::find($id);

        if (!$sequence) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket number sequence not found',
            ], 404);
        }

        // Add sample output
        $sequence->sample_output = $sequence->generateSample();

        return response()->json([
            'success' => true,
            'data' => $sequence,
        ]);
    }

    /**
     * Update the specified ticket number sequence.
     */
    public function update(Request $request, string $id)
    {
        $sequence = TicketNumberSequence::find($id);

        if (!$sequence) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket number sequence not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|unique:ticket_number_sequences,name,' . $id . '|max:50',
            'prefix' => 'sometimes|string|max:10',
            'pattern' => 'sometimes|string|max:100',
            'current_number' => 'nullable|integer|min:0',
            'padding' => 'sometimes|integer|min:1|max:10',
            'reset_daily' => 'nullable|boolean',
            'reset_monthly' => 'nullable|boolean',
            'reset_yearly' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $sequence->update($validator->validated());
            $sequence->updateSampleOutput();

            return response()->json([
                'success' => true,
                'message' => 'Ticket number sequence updated successfully',
                'data' => $sequence,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket number sequence',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified ticket number sequence.
     */
    public function destroy(string $id)
    {
        $sequence = TicketNumberSequence::find($id);

        if (!$sequence) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket number sequence not found',
            ], 404);
        }

        // Prevent deleting active sequences
        if ($sequence->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an active sequence. Please deactivate it first.',
            ], 400);
        }

        try {
            $sequence->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ticket number sequence deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket number sequence',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset the current number for a sequence.
     */
    public function reset(string $id)
    {
        $sequence = TicketNumberSequence::find($id);

        if (!$sequence) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket number sequence not found',
            ], 404);
        }

        try {
            $sequence->current_number = 0;
            $sequence->last_reset_date = now()->toDateString();
            $sequence->save();
            $sequence->updateSampleOutput();

            return response()->json([
                'success' => true,
                'message' => 'Ticket number sequence reset successfully',
                'data' => $sequence,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset ticket number sequence',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview what the next ticket number will look like.
     */
    public function preview(string $id)
    {
        $sequence = TicketNumberSequence::find($id);

        if (!$sequence) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket number sequence not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'current_number' => $sequence->current_number,
                'next_number' => $sequence->current_number + 1,
                'sample_output' => $sequence->generateSample(),
                'pattern' => $sequence->pattern,
            ],
        ]);
    }
}
