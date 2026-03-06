<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\Customer;
use App\Models\TicketHistory;
use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class PublicTicketController extends Controller
{
    /**
     * Submit a new ticket (public - no authentication required)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitTicket(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'department' => 'nullable|string|max:100',
            'category' => 'required|string',
            'subcategory' => 'nullable|string',
            'priority' => 'required|in:low,medium,high,critical',
            'subject' => 'required|string|max:255',
            'description' => 'required|string|min:20',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'customer_company' => 'nullable|string|max:255',
            'customer_id_type' => 'nullable|string|max:20',
            'customer_id_number' => 'nullable|string|max:50',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max per file
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Find or create customer
            $customer = Customer::where('email', $request->customer_email)->first();
            
            if (!$customer) {
                $customer = Customer::create([
                    'name' => $request->customer_name,
                    'email' => $request->customer_email,
                    'phone_number' => $request->customer_phone,
                    'company_name' => $request->customer_company,
                    'id_type' => $request->customer_id_type ?? 'MyKad',
                    'id_no' => $request->customer_id_number,
                    'customer_type' => 'individual',
                    'status' => 'active',
                    'preferred_language' => $request->header('Accept-Language', 'en'),
                ]);
            }

            // Determine department - use provided department or determine from category
            $department = $request->department ?? $this->determineDepartment($request->category);

            // Create ticket
            $ticket = Ticket::create([
                'customer_id' => $customer->id,
                'ticket_number' => $this->generateTicketNumber(),
                'subject' => $request->subject,
                'description' => $request->description,
                'priority' => $this->mapPriority($request->priority),
                'status' => 'Closed',
                'closed_at' => now()->toDateTimeString(),
                'channel' => 'web',
                'department' => $department,
                'category' => $request->category,
                'subcategory' => $request->subcategory,
                'impact' => $this->determineImpact($request->priority),
                'urgency' => $this->determineUrgency($request->priority),
            ]);

            // Calculate and set SLA
            $ticket->calculateSla();
            $ticket->updateSlaRemaining();

            // Handle file uploads
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('public/citizen-attachments/' . $ticket->id);
                    
                    TicketAttachment::create([
                        'ticket_id' => $ticket->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => $customer->id,
                        'uploaded_by_type' => 'customer',
                    ]);
                }
            }

            // Add ticket history
            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'action' => 'created',
                'description' => 'Ticket created via citizen portal and automatically closed',
                'old_value' => null,
                'new_value' => 'Closed',
                'changed_by' => $customer->id,
                'changed_by_type' => 'customer',
            ]);

            DB::commit();

            // Send confirmation email (queue it)
            // Mail::to($customer->email)->queue(new TicketSubmittedMail($ticket));

            return response()->json([
                'success' => true,
                'message' => 'Ticket submitted successfully',
                'data' => [
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'estimated_resolution' => $this->getEstimatedResolution($request->priority),
                    'assigned_department' => $department,
                    'status' => $ticket->status,
                    'sla_due_at' => $ticket->sla_due_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Public ticket submission failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit ticket. Please try again.'
            ], 500);
        }
    }

    /**
     * Track ticket status by ticket number (requires email verification)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function trackTicket(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ticket_number' => 'required|string',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $ticket = Ticket::where('ticket_number', $request->ticket_number)
            ->whereHas('customer', function($query) use ($request) {
                $query->where('email', $request->email);
            })
            ->with(['customer', 'assignedAgent'])
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found or email does not match'
            ], 404);
        }

        // Get communications/history
        $communications = TicketHistory::where('ticket_id', $ticket->id)
            ->whereIn('action', ['created', 'updated', 'responded', 'status_changed'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($history) {
                return [
                    'id' => $history->id,
                    'type' => $history->changed_by_type === 'customer' ? 'system' : 'agent',
                    'message' => $history->description,
                    'author' => $history->changed_by_type === 'agent' 
                        ? $history->changedByAgent->name ?? 'Agent'
                        : 'System',
                    'timestamp' => $history->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'category' => $ticket->category,
                'assigned_department' => $ticket->department,
                'last_updated' => $ticket->updated_at->toIso8601String(),
                'submitted_at' => $ticket->created_at->toIso8601String(),
                'estimated_resolution' => $this->getEstimatedResolution($ticket->priority),
                'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
                'sla_remaining_minutes' => $ticket->sla_remaining_minutes,
                'sla_breached' => $ticket->sla_breached,
                'communications' => $communications,
            ]
        ], 200);
    }

    /**
     * View ticket details (requires email verification)
     * 
     * @param string $ticketNumber
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function viewTicket(string $ticketNumber, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Email is required for verification',
                'errors' => $validator->errors()
            ], 422);
        }

        $ticket = Ticket::where('ticket_number', $ticketNumber)
            ->whereHas('customer', function($query) use ($request) {
                $query->where('email', $request->email);
            })
            ->with(['customer', 'assignedAgent', 'attachments'])
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found or email does not match'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'category' => $ticket->category,
                'subcategory' => $ticket->subcategory,
                'assigned_department' => $ticket->department,
                'assigned_agent' => $ticket->assignedAgent ? [
                    'name' => $ticket->assignedAgent->name,
                ] : null,
                'attachments' => $ticket->attachments->map(function($attachment) {
                    return [
                        'id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                        'file_size' => $attachment->file_size,
                        'uploaded_at' => $attachment->created_at->toIso8601String(),
                    ];
                }),
                'created_at' => $ticket->created_at->toIso8601String(),
                'updated_at' => $ticket->updated_at->toIso8601String(),
            ]
        ], 200);
    }

    /**
     * Upload attachment to existing ticket
     * 
     * @param string $ticketNumber
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadAttachment(string $ticketNumber, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $ticket = Ticket::where('ticket_number', $ticketNumber)
            ->whereHas('customer', function($query) use ($request) {
                $query->where('email', $request->email);
            })
            ->first();

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found or email does not match'
            ], 404);
        }

        try {
            $file = $request->file('file');
            $path = $file->store('public/citizen-attachments/' . $ticket->id);
            
            $attachment = TicketAttachment::create([
                'ticket_id' => $ticket->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $ticket->customer_id,
                'uploaded_by_type' => 'customer',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'attachment_id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'file_size' => $attachment->file_size,
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'ticket_number' => $ticketNumber,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file'
            ], 500);
        }
    }

    // Helper methods

    private function generateTicketNumber()
    {
        // Use the same sequence logic as TicketController for web tickets
        return \App\Models\TicketNumberSequence::generateNextNumber('web');
    }

    private function determineDepartment($category)
    {
        $departments = [
            'customs_clearance' => 'Customs Operations Department',
            'tax_duties' => 'Assessment & Collection Department',
            'import_export' => 'Trade Facilitation Department',
            'documentation' => 'Documentation Services',
            'enforcement' => 'Enforcement Division',
            'general_inquiry' => 'Customer Service Department',
        ];

        return $departments[$category] ?? 'Customer Service Department';
    }

    private function mapPriority($priority)
    {
        $mapping = [
            'low' => 'P4',
            'medium' => 'P3',
            'high' => 'P2',
            'critical' => 'P1',
        ];

        return $mapping[$priority] ?? 'P3';
    }

    private function determineImpact($priority)
    {
        $impacts = [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'High',
        ];

        return $impacts[$priority] ?? 'Medium';
    }

    private function determineUrgency($priority)
    {
        $urgencies = [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'critical' => 'High',
        ];

        return $urgencies[$priority] ?? 'Medium';
    }

    private function getEstimatedResolution($priority)
    {
        $resolutions = [
            'low' => '5-7 business days',
            'P4' => '5-7 business days',
            'medium' => '3-5 business days',
            'P3' => '3-5 business days',
            'high' => '1-2 business days',
            'P2' => '1-2 business days',
            'critical' => '4-8 hours',
            'P1' => '4-8 hours',
        ];

        return $resolutions[$priority] ?? '3-5 business days';
    }

    /**
     * Get all tickets for authenticated citizen by phone number.
     * Requires X-Session-Token header.
     */
    public function getMyTickets(Request $request)
    {
        // Phone number is injected by ValidateCitizenSession middleware
        $phoneNumber = $request->input('citizen_phone_number');

        if (!$phoneNumber) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number not found in session',
            ], 400);
        }

        try {
            // Find customer by phone number (handle various formats)
            // Normalize phone number for comparison
            $normalizedPhone = preg_replace('/^(\+?60|60)/', '0', $phoneNumber);
            
            $customer = Customer::where(function($query) use ($phoneNumber, $normalizedPhone) {
                    $query->where('phone_number', $phoneNumber)
                          ->orWhere('phone_number', $normalizedPhone)
                          ->orWhere('phone_number', '+60' . ltrim($normalizedPhone, '0'))
                          ->orWhere('phone_number', '60' . ltrim($normalizedPhone, '0'));
                })
                ->first();

            if (!$customer) {
                return response()->json([
                    'success' => true,
                    'message' => 'No tickets found',
                    'data' => [],
                ]);
            }

            // Get all tickets for this customer
            $tickets = Ticket::where('customer_id', $customer->id)
                ->with(['histories' => function($query) {
                    $query->orderBy('created_at', 'desc');
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            // Format tickets for response
            $formattedTickets = $tickets->map(function ($ticket) use ($customer) {
                return [
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                    'description' => $ticket->description,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'department' => $ticket->department,
                    'category' => $ticket->category,
                    'sub_category' => $ticket->sub_category,
                    'customer_name' => $customer->name,
                    'customer_email' => $customer->email,
                    'customer_phone' => $customer->phone_number,
                    'created_at' => $ticket->created_at->toIso8601String(),
                    'updated_at' => $ticket->updated_at->toIso8601String(),
                    'timeline' => $ticket->histories->map(function ($history) {
                        return [
                            'id' => $history->id,
                            'action' => $history->action,
                            'description' => $history->description,
                            'field_name' => $history->field_name,
                            'old_value' => $history->old_value,
                            'new_value' => $history->new_value,
                            'created_at' => $history->created_at->toIso8601String(),
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedTickets,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve tickets', [
                'error' => $e->getMessage(),
                'phone' => substr($phoneNumber, 0, 3) . '***',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tickets',
            ], 500);
        }
    }
}
