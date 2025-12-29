<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Ticket;
use App\Models\Agent;
use App\Models\EmailConfiguration;
use App\Services\ImapEmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProcessIncomingEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:process
                          {--once : Process emails once and exit}
                          {--interval=60 : Interval in seconds between checks (default: 60)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process incoming emails from IMAP server and create tickets';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $once = $this->option('once');
        $interval = (int) $this->option('interval');

        if ($once) {
            $this->info('Processing emails once...');
            $this->processEmails();
            return 0;
        }

        $this->info("Starting email processor. Checking every {$interval} seconds...");
        $this->info('Press Ctrl+C to stop.');

        while (true) {
            try {
                $this->processEmails();
            } catch (\Exception $e) {
                $this->error('Error processing emails: ' . $e->getMessage());
                Log::error('Email processing error: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
            }

            sleep($interval);
        }
    }

    /**
     * Process all unread emails from all active IMAP configurations.
     */
    protected function processEmails(): void
    {
        // Get all active IMAP configurations
        $configs = EmailConfiguration::getActiveImapConfigs();

        if ($configs->isEmpty()) {
            $this->warn('No active IMAP configurations found. Attempting to use environment configuration...');

            // Try with default/environment config
            try {
                $this->processEmailsForConfig(null);
            } catch (\Exception $e) {
                $this->error('Failed to process emails with environment config: ' . $e->getMessage());
                Log::error('Email processing error (env config): ' . $e->getMessage());
            }

            return;
        }

        // Process emails for each active configuration
        foreach ($configs as $config) {
            $this->info("Processing emails for configuration: {$config->name}");

            try {
                $this->processEmailsForConfig($config);
            } catch (\Exception $e) {
                $this->error("Failed to process emails for {$config->name}: " . $e->getMessage());
                Log::error("Email processing error for config {$config->name}: " . $e->getMessage(), [
                    'config_id' => $config->id,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Process emails for a specific configuration.
     */
    protected function processEmailsForConfig(?EmailConfiguration $config): void
    {
        try {
            $imapService = new ImapEmailService($config);
            $emails = $imapService->fetchUnreadEmails();

            if (empty($emails)) {
                $configName = $config ? $config->name : 'environment';
                $this->line('[' . now()->format('Y-m-d H:i:s') . "] No new emails found for {$configName}");
                return;
            }

            $this->info('[' . now()->format('Y-m-d H:i:s') . '] Found ' . count($emails) . ' new email(s)');

            foreach ($emails as $emailData) {
                try {
                    $this->processSingleEmail($emailData, $imapService, $config);
                } catch (\Exception $e) {
                    $this->error("Failed to process email {$emailData['message_id']}: " . $e->getMessage());
                    Log::error("Email processing failed for {$emailData['message_id']}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $imapService->disconnect();
        } catch (\Exception $e) {
            $this->error('IMAP service error: ' . $e->getMessage());
            Log::error('IMAP service error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process a single email message.
     */
    protected function processSingleEmail(array $emailData, ImapEmailService $imapService, ?EmailConfiguration $config = null): void
    {
        DB::beginTransaction();

        try {
            // Check for duplicate
            if (EmailMessage::where('message_id', $emailData['message_id'])->exists()) {
                $this->warn("Email {$emailData['message_id']} already processed, skipping");
                DB::rollBack();
                return;
            }

            // Find or create customer by email
            $customer = $this->findOrCreateCustomer($emailData['from_email'], $emailData['from_name']);

            // Check if this is a reply to an existing ticket
            $existingTicket = null;
            $ticketNumber = EmailMessage::extractTicketNumber($emailData['subject']);

            if ($ticketNumber) {
                $existingTicket = Ticket::where('ticket_number', $ticketNumber)->first();
            }

            // If no ticket number in subject, try to find by conversation thread
            if (!$existingTicket && isset($emailData['conversation_thread_id'])) {
                $previousEmail = EmailMessage::where('conversation_thread_id', $emailData['conversation_thread_id'])
                    ->whereNotNull('ticket_id')
                    ->latest()
                    ->first();

                if ($previousEmail && $previousEmail->ticket_id) {
                    $existingTicket = Ticket::find($previousEmail->ticket_id);
                }
            }

            // Store email message
            $emailNumber = $emailData['email_number'] ?? null;
            unset($emailData['email_number']); // Remove before storing

            $emailMessage = EmailMessage::create([
                ...$emailData,
                'customer_id' => $customer->id,
                'ticket_id' => $existingTicket?->id,
                'is_processed' => false,
            ]);

            // Create new ticket or update existing one
            if ($existingTicket) {
                $this->updateExistingTicket($existingTicket, $emailMessage);
                $this->info("Updated existing ticket: {$existingTicket->ticket_number}");
            } else {
                $ticket = $this->createTicketFromEmail($emailMessage, $customer, $config);
                $emailMessage->ticket_id = $ticket->id;
                $this->info("Created new ticket: {$ticket->ticket_number}");
            }

            // Mark email as processed
            $emailMessage->markAsProcessed();

            // Mark IMAP email as read
            if ($emailNumber) {
                $imapService->markAsRead($emailNumber);
            }

            // Send notifications
            $this->sendNotifications($emailMessage);

            DB::commit();

            $this->line("Successfully processed email from {$emailData['from_email']}");
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Find or create customer by email address.
     */
    protected function findOrCreateCustomer(string $email, ?string $name): Customer
    {
        $customer = Customer::where('email', $email)->first();

        if (!$customer) {
            $customer = Customer::create([
                'email' => $email,
                'name' => $name ?? $email,
                'customer_type' => 'individual',
                'status' => 'active',
                'phone_number' => null,
            ]);

            Log::info("Created new customer from email: {$email}");
        }

        return $customer;
    }

    /**
     * Create a new ticket from email.
     */
    protected function createTicketFromEmail(EmailMessage $emailMessage, Customer $customer, ?EmailConfiguration $config = null): Ticket
    {
        // Use config settings if available, otherwise use defaults
        $department = $config?->department ?? 'Support';
        $defaultPriority = $config?->default_priority ?? 'P3';
        $autoAssign = $config?->auto_assign ?? true;

        // Determine priority based on subject keywords, fallback to config default
        $priority = $this->determinePriority($emailMessage->subject, $emailMessage->body_text);
        if ($priority === 'P3' && $defaultPriority !== 'P3') {
            // If auto-detected priority is default, use config priority
            $priority = $defaultPriority;
        }

        // Auto-assign agent if enabled in config
        $assignedAgent = null;
        if ($autoAssign) {
            $assignedAgent = $this->autoAssignAgent($department);
        }

        // Generate ticket number
        $ticketNumber = $this->generateTicketNumber('email');

        // Create ticket
        $ticket = Ticket::create([
            'ticket_number' => $ticketNumber,
            'customer_id' => $customer->id,
            'assigned_to' => $assignedAgent?->id,
            'priority' => $priority,
            'department' => $department,
            'category' => 'Email Request',
            'subject' => $emailMessage->subject ?: 'No Subject',
            'description' => $emailMessage->body_text ?: $emailMessage->body_html ?: 'No content',
            'urgency' => $this->mapPriorityToUrgency($priority),
            'impact' => 'Medium',
            'status' => 'Open',
            'channel' => 'email',
            'source' => $config ? "IMAP ({$config->name})" : 'IMAP',
        ]);

        // Calculate SLA
        $ticket->calculateSla();
        $ticket->save();

        // Log ticket creation
        $ticket->logHistory(
            'created',
            null,
            null,
            null,
            "Ticket created from email: {$emailMessage->from_email}",
            [
                'email_message_id' => $emailMessage->id,
                'from_email' => $emailMessage->from_email,
            ]
        );

        // Log assignment
        if ($assignedAgent) {
            $ticket->logHistory(
                'assigned',
                'assigned_to',
                null,
                $assignedAgent->user->name ?? 'Unknown',
                "Ticket auto-assigned to " . ($assignedAgent->user->name ?? 'agent'),
                ['agent_id' => $assignedAgent->id]
            );
        }

        // Update subject to include ticket number for future replies
        $this->sendAutoReply($emailMessage, $ticket);

        return $ticket;
    }

    /**
     * Update existing ticket with new email.
     */
    protected function updateExistingTicket(Ticket $ticket, EmailMessage $emailMessage): void
    {
        // If ticket is closed or resolved, reopen it
        if (in_array($ticket->status, ['Closed', 'Resolved', 'Cancelled'])) {
            $ticket->reopen();

            $ticket->logHistory(
                'reopened',
                'status',
                'Closed',
                'Open',
                "Ticket reopened due to new email from customer",
                ['email_message_id' => $emailMessage->id]
            );

            $this->info("Reopened ticket: {$ticket->ticket_number}");
        }

        // Add email as a note/comment to the ticket
        $ticket->logHistory(
            'comment',
            null,
            null,
            null,
            "New email received from {$emailMessage->from_email}",
            [
                'email_message_id' => $emailMessage->id,
                'email_body' => substr($emailMessage->body_text ?: $emailMessage->body_html, 0, 500),
            ]
        );
    }

    /**
     * Determine priority from email content.
     */
    protected function determinePriority(?string $subject, ?string $body): string
    {
        $urgentKeywords = ['urgent', 'critical', 'emergency', 'asap', 'immediately', 'high priority'];
        $text = strtolower(($subject ?? '') . ' ' . ($body ?? ''));

        foreach ($urgentKeywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return 'P1';
            }
        }

        return 'P3'; // Default priority
    }

    /**
     * Map priority to urgency.
     */
    protected function mapPriorityToUrgency(string $priority): string
    {
        return match($priority) {
            'P1' => 'Critical',
            'P2' => 'High',
            'P3' => 'Medium',
            'P4' => 'Low',
            default => 'Medium',
        };
    }

    /**
     * Auto-assign an available agent.
     */
    protected function autoAssignAgent(?string $department = null): ?Agent
    {
        $query = Agent::where('availability', 'available')
            ->where('status', 'active');

        if ($department) {
            $query->where('department', $department);
        }

        return $query->first();
    }

    /**
     * Generate ticket number.
     */
    protected function generateTicketNumber(string $channel): string
    {
        try {
            $sequenceName = $channel === 'walk-in' ? 'walkIn' : $channel;
            return \App\Models\TicketNumberSequence::generateNextNumber($sequenceName);
        } catch (\Exception $e) {
            Log::error('Ticket number generation failed: ' . $e->getMessage());

            // Fallback
            do {
                $number = 'TKT' . date('Ymd') . strtoupper(\Illuminate\Support\Str::random(6));
            } while (Ticket::where('ticket_number', $number)->exists());

            return $number;
        }
    }

    /**
     * Send auto-reply to customer with ticket number.
     */
    protected function sendAutoReply(EmailMessage $emailMessage, Ticket $ticket): void
    {
        // TODO: Implement auto-reply email functionality
        // This would use Laravel's Mail facade to send a confirmation email
        // with the ticket number to the customer

        Log::info("Auto-reply should be sent to {$emailMessage->from_email} for ticket {$ticket->ticket_number}");
    }

    /**
     * Send notifications to relevant users.
     */
    protected function sendNotifications(EmailMessage $emailMessage): void
    {
        if (!$emailMessage->ticket) {
            return;
        }

        $ticket = $emailMessage->ticket;

        // Notify assigned agent
        if ($ticket->assignedAgent && $ticket->assignedAgent->user) {
            $user = $ticket->assignedAgent->user;

            // Create database notification
            $user->notifications()->create([
                'id' => \Illuminate\Support\Str::uuid(),
                'type' => 'App\\Notifications\\TicketNotification',
                'data' => [
                    'type' => 'ticket_assigned',
                    'level' => 'info',
                    'message' => "New ticket #{$ticket->ticket_number} assigned to you from email",
                    'ticket_id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'priority' => $ticket->priority,
                    'department' => $ticket->department,
                    'url' => "/tickets/{$ticket->id}",
                ],
                'read_at' => null,
            ]);

            Log::info("Notification sent to agent for ticket {$ticket->ticket_number}");
        }
    }
}
