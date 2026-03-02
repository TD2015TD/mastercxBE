<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    /**
     * Display all contacts for a customer.
     */
    public function index(Request $request)
    {
        $query = Contact::with(['customer', 'contactType']);

        // Filter by customer
        $customerId = $request->get('customer_id', $request->route('customerId'));
        if ($customerId) {
            $query->byCustomer($customerId);
        }

        // Filter by contact type
        if ($request->has('contact_type_id')) {
            $query->byType($request->contact_type_id);
        }

        // Filter by primary contacts
        if ($request->has('is_primary')) {
            $isPrimary = filter_var($request->get('is_primary'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isPrimary !== null) {
                $query->where('is_primary', $isPrimary);
            }
        }

        // Search by value
        if ($request->has('search')) {
            $query->byValue($request->search);
        }

        $perPage = $request->get('per_page', 20);
        $contacts = $query->orderBy('is_primary', 'desc')->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($contacts);
    }

    /**
     * Store a newly created contact.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'contact_type_id' => 'required|exists:lookups,id',
            'value' => 'required|string|max:255',
            'is_primary' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // If marking as primary, unmark other primary contacts of same type
        if ($request->get('is_primary', false)) {
            Contact::where('customer_id', $request->customer_id)
                ->where('contact_type_id', $request->contact_type_id)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $contact = Contact::updateOrCreate(
            [
                'customer_id'     => $request->customer_id,
                'contact_type_id' => $request->contact_type_id,
                'value'           => $request->value,
            ],
            [
                'is_primary' => $request->get('is_primary', false),
                'notes'      => $request->get('notes'),
            ]
        );
        $contact->load(['customer', 'contactType']);

        return response()->json([
            'success' => true,
            'message' => 'Contact saved successfully',
            'data' => $contact
        ], 201);
    }

    /**
     * Display the specified contact.
     */
    public function show($id)
    {
        $contact = Contact::with(['customer', 'contactType'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $contact
        ]);
    }

    /**
     * Update the specified contact.
     */
    public function update(Request $request, $id)
    {
        $contact = Contact::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'contact_type_id' => 'exists:lookups,id',
            'value' => 'string|max:255',
            'is_primary' => 'nullable|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // If marking as primary, unmark other primary contacts of same type
        $typeId = $request->get('contact_type_id', $contact->contact_type_id);
        if ($request->get('is_primary', false) && !$contact->is_primary) {
            Contact::where('customer_id', $contact->customer_id)
                ->where('contact_type_id', $typeId)
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        }

        $contact->update($request->all());
        $contact->load(['customer', 'contactType']);

        return response()->json([
            'success' => true,
            'message' => 'Contact updated successfully',
            'data' => $contact
        ]);
    }

    /**
     * Remove the specified contact.
     */
    public function destroy($id)
    {
        $contact = Contact::findOrFail($id);
        $contact->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact deleted successfully'
        ]);
    }

    /**
     * Find customer by phone number (for incoming calls).
     */
    public function findByPhone($phoneNumber)
    {
        try {
            // Normalize phone number (remove non-digits)
            $normalizedPhone = preg_replace('/\D/', '', $phoneNumber);

            if (empty($normalizedPhone)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone number'
                ], 400);
            }

            // Get phone contact types from lookups (both phone and mobile)
            $phoneContactTypes = \App\Models\Lookup::where('type', 'contact_type')
                ->whereIn('code', ['phone', 'mobile'])
                ->get();

            if ($phoneContactTypes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone contact types not configured'
                ], 500);
            }

            $contactTypeIds = $phoneContactTypes->pluck('id')->toArray();

            // Search for contact by phone number
            $contacts = Contact::whereIn('contact_type_id', $contactTypeIds)
                ->with(['customer', 'contactType'])
                ->get();

            // Filter contacts by normalized phone number
            $contact = $contacts->first(function ($c) use ($normalizedPhone) {
                $contactNormalized = preg_replace('/\D/', '', $c->value);
                return $contactNormalized === $normalizedPhone;
            });

            if ($contact && $contact->customer) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'contact' => $contact,
                        'customer' => $contact->customer
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No contact found with this phone number'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching for contact',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
