<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAttachment;
use App\Models\CustomerNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers.
     */
    public function index(Request $request)
    {
        $query = Customer::query();

        // Filter by customer type
        if ($request->filled('customer_type')) {
            $query->byType($request->customer_type);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by ID type
        if ($request->filled('id_type')) {
            $query->where('id_type', $request->id_type);
        }

        // Search by name, email, phone, or ID number
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%")
                  ->orWhere('id_no', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        
        if ($request->has('with_tickets')) {
            $query->with('tickets');
        }

        $customers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers,email',
            'phone_number' => 'required|string|max:20',
            'id_type' => 'required|in:MyKad,MyTentera,MyPolis,MyPR,Passport',
            'id_no' => 'required|string|unique:customers,id_no',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'customer_type' => 'nullable|in:individual,business',
            'business_registration_no' => 'nullable|string|max:50',
            'preferred_language' => 'nullable|in:en,ms,zh,ta',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $customer = Customer::create($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => $customer,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(string $id)
    {
        $customer = Customer::with(['tickets' => function($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }])->find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        // Add computed attributes
        $customerData = $customer->toArray();
        $customerData['resolution_rate'] = $customer->resolution_rate;
        $customerData['open_tickets_count'] = $customer->open_tickets_count;
        $customerData['formatted_id'] = $customer->formatted_id;

        return response()->json([
            'success' => true,
            'data' => $customerData,
        ]);
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, string $id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:customers,email,' . $id,
            'phone_number' => 'sometimes|string|max:20',
            'id_type' => 'sometimes|in:MyKad,MyTentera,MyPolis,MyPR,Passport',
            'id_no' => 'sometimes|string|unique:customers,id_no,' . $id,
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'customer_type' => 'sometimes|in:individual,business',
            'business_registration_no' => 'nullable|string|max:50',
            'preferred_language' => 'nullable|in:en,ms,zh,ta',
            'status' => 'sometimes|in:active,inactive,blocked',
            'tags' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $customer->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $customer,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(string $id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        try {
            $customer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search customers by various criteria.
     */
    public function search(Request $request)
    {
        $query = Customer::query();

        if ($request->has('email')) {
            $customer = $query->where('email', $request->email)->first();
            
            if ($customer) {
                return response()->json([
                    'success' => true,
                    'data' => $customer,
                    'found' => true,
                ]);
            }
        }

        if ($request->has('id_no')) {
            $customer = $query->where('id_no', $request->id_no)->first();
            
            if ($customer) {
                return response()->json([
                    'success' => true,
                    'data' => $customer,
                    'found' => true,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => null,
            'found' => false,
            'message' => 'Customer not found',
        ]);
    }

    /**
     * Get customer statistics.
     */
    public function statistics(string $id)
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $stats = [
            'total_tickets' => $customer->total_tickets,
            'resolved_tickets' => $customer->resolved_tickets,
            'open_tickets' => $customer->open_tickets_count,
            'resolution_rate' => $customer->resolution_rate,
            'satisfaction_rating' => $customer->satisfaction_rating,
            'last_contact_at' => $customer->last_contact_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Upload attachment to a customer.
     */
    public function uploadAttachment(Request $request, string $customerId)
    {
        $customer = Customer::find($customerId);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // Max 10MB
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('customer_attachments/' . $customerId, $fileName);

            $attachment = CustomerAttachment::create([
                'customer_id' => $customerId,
                'uploaded_by' => auth()->id(),
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => CustomerAttachment::getFileTypeFromMime($file->getMimeType()),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'description' => $request->description,
            ]);

            $attachment->load('uploader');

            return response()->json([
                'success' => true,
                'message' => 'Attachment uploaded successfully',
                'data' => $attachment,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload attachment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all attachments for a customer.
     */
    public function getAttachments(string $customerId)
    {
        $customer = Customer::find($customerId);

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $attachments = CustomerAttachment::where('customer_id', $customerId)
            ->with('uploader')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attachments,
        ]);
    }

    /**
     * Download an attachment.
     */
    public function downloadAttachment(string $customerId, string $attachmentId)
    {
        $attachment = CustomerAttachment::where('customer_id', $customerId)
            ->where('id', $attachmentId)
            ->first();

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found',
            ], 404);
        }

        $disk = Storage::disk(config('filesystems.default'));

        if (!$disk->exists($attachment->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found in storage',
            ], 404);
        }

        return response()->download(
            $disk->path($attachment->file_path),
            $attachment->file_name,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    /**
     * Delete an attachment.
     */
    public function deleteAttachment(string $customerId, string $attachmentId)
    {
        $attachment = CustomerAttachment::where('customer_id', $customerId)
            ->where('id', $attachmentId)
            ->first();

        if (!$attachment) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment not found',
            ], 404);
        }

        try {
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all notes for a customer.
     */
    public function getNotes(string $customerId)
    {
        $customer = Customer::find($customerId);
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $notes = CustomerNote::where('customer_id', $customerId)
            ->with('creator:id,name,email')
            ->orderByPinned()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notes,
        ]);
    }

    /**
     * Create a new note for a customer.
     */
    public function createNote(Request $request, string $customerId)
    {
        $customer = Customer::find($customerId);
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000',
            'is_pinned' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $note = CustomerNote::create([
                'customer_id' => $customerId,
                'created_by' => auth()->id(),
                'content' => $request->input('content'),
                'is_pinned' => $request->boolean('is_pinned', false),
            ]);

            $note->load('creator:id,name,email');

            return response()->json([
                'success' => true,
                'message' => 'Note created successfully',
                'data' => $note,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create note',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a note.
     */
    public function updateNote(Request $request, string $customerId, string $noteId)
    {
        $customer = Customer::find($customerId);
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $note = CustomerNote::where('customer_id', $customerId)
            ->where('id', $noteId)
            ->first();

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'sometimes|required|string|max:5000',
            'is_pinned' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if ($request->has('content')) {
                $note->content = $request->input('content');
            }

            if ($request->has('is_pinned')) {
                $note->is_pinned = $request->boolean('is_pinned');
            }

            $note->save();
            $note->load('creator:id,name,email');

            return response()->json([
                'success' => true,
                'message' => 'Note updated successfully',
                'data' => $note,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update note',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a note.
     */
    public function deleteNote(string $customerId, string $noteId)
    {
        $customer = Customer::find($customerId);
        
        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found',
            ], 404);
        }

        $note = CustomerNote::where('customer_id', $customerId)
            ->where('id', $noteId)
            ->first();

        if (!$note) {
            return response()->json([
                'success' => false,
                'message' => 'Note not found',
            ], 404);
        }

        try {
            $note->delete();

            return response()->json([
                'success' => true,
                'message' => 'Note deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete note',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
