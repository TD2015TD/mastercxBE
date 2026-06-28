<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\Ticket;
use App\Models\Agent;
use App\Models\EmailConfiguration;
use App\Services\ImapEmailService;
use App\Services\MitelService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

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
            $lockFile = sys_get_temp_dir() . '/crm-email-process.lock';
            $lock = @fopen($lockFile, 'c');
            if ($lock && flock($lock, LOCK_EX | LOCK_NB)) {
                try {
                    $this->processEmails();
                } finally {
                    flock($lock, LOCK_UN);
                    fclose($lock);
                }
            } else {
                if ($lock) fclose($lock);
                $this->line('email:process already running, skipping.');
            }
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
            Log::info('[ProcessEmail] Threading headers', [
                'subject'    => $emailData['subject'],
                'in_reply_to'=> $emailData['in_reply_to'] ?? null,
                'references' => $emailData['references'] ?? null,
            ]);
            // Extract ALL ticket numbers from subject and try from newest (last) to
            // oldest. Pick the first one that is still open/in-progress so we don't
            // accidentally create yet another new ticket when only the latest number
            // in the chain happens to be closed while an earlier one is still active.
            $allTicketNumbers = [];
            if (preg_match_all('/\[([A-Z]{1,10}-\d{4,8}-\d{3,8})\]/i', $emailData['subject'], $tnMatches)) {
                $allTicketNumbers = array_map('strtoupper', $tnMatches[1]);
            }
            foreach (array_reverse($allTicketNumbers) as $ticketNumber) {
                $candidate = Ticket::where('ticket_number', $ticketNumber)->first();
                if (!$candidate) continue;
                if (!in_array($candidate->status, ['Closed', 'Resolved', 'Cancelled'])) {
                    $existingTicket = $candidate;
                    Log::info('[ProcessEmail] Matched open ticket by subject', ['ticket' => $existingTicket->ticket_number, 'status' => $existingTicket->status]);
                    break;
                }
                // Candidate is closed — keep looking at older numbers in the list
                Log::info('[ProcessEmail] Subject ticket is closed, checking older', ['ticket' => $candidate->ticket_number]);
            }

            // If no ticket number in subject, try to find by In-Reply-To header matching a stored message
            if (!$existingTicket && !empty($emailData['in_reply_to'])) {
                $inReplyTo = trim($emailData['in_reply_to']);
                $previousEmail = EmailMessage::where('message_id', $inReplyTo)
                    ->whereNotNull('ticket_id')
                    ->first();

                if ($previousEmail && $previousEmail->ticket_id) {
                    $existingTicket = Ticket::find($previousEmail->ticket_id);
                    if ($existingTicket) {
                        Log::info('[ProcessEmail] Matched ticket by In-Reply-To', ['ticket' => $existingTicket->ticket_number]);
                    }
                }
            }

            // If still not found, try by conversation thread ID
            if (!$existingTicket && isset($emailData['conversation_thread_id'])) {
                $previousEmail = EmailMessage::where('conversation_thread_id', $emailData['conversation_thread_id'])
                    ->whereNotNull('ticket_id')
                    ->latest()
                    ->first();

                if ($previousEmail && $previousEmail->ticket_id) {
                    $existingTicket = Ticket::find($previousEmail->ticket_id);
                }
            }

            // 4th attempt: scan every message-ID listed in the References header.
            // This catches cases where the SMTP relay rewrites our Message-ID so the
            // In-Reply-To on the customer's next reply doesn't match what we stored,
            // but earlier message IDs in the thread (e.g. the customer's very first
            // email) are still present and ARE in our database.
            if (!$existingTicket && !empty($emailData['references'])) {
                $refs = preg_split('/\s+/', trim($emailData['references']));
                foreach (array_filter($refs) as $ref) {
                    $ref = trim($ref);
                    if (!$ref) continue;
                    $previousEmail = EmailMessage::where('message_id', $ref)
                        ->whereNotNull('ticket_id')
                        ->first();
                    if ($previousEmail && $previousEmail->ticket_id) {
                        $existingTicket = Ticket::find($previousEmail->ticket_id);
                        if ($existingTicket) {
                            Log::info('[ProcessEmail] Matched ticket via References header', [
                                'ref'    => $ref,
                                'ticket' => $existingTicket->ticket_number,
                            ]);
                            break;
                        }
                    }
                }
            }

            // If the matched ticket is already closed/resolved/cancelled, treat the
            // customer reply as a brand-new enquiry and open a fresh ticket instead
            // of reopening the old one. Keep a reference so the Mitel iframe can
            // show the previous conversation as context.
            $closedTicket = null;
            if ($existingTicket && in_array($existingTicket->status, ['Closed', 'Resolved', 'Cancelled'])) {
                $this->info("Ticket {$existingTicket->ticket_number} is {$existingTicket->status} — creating new ticket for this reply");
                $closedTicket    = $existingTicket;
                $existingTicket  = null;
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
                $ticket = $this->createTicketFromEmail($emailMessage, $customer, $config, $closedTicket);
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
    protected function createTicketFromEmail(EmailMessage $emailMessage, Customer $customer, ?EmailConfiguration $config = null, ?Ticket $previousTicket = null): Ticket
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
            'subject' => trim(preg_replace('/\s*\[[A-Z]{1,10}-\d{4,8}-\d{3,8}\]/i', '', $emailMessage->subject ?: 'No Subject')) ?: 'No Subject',
            'description' => $emailMessage->body_text ?: $emailMessage->body_html ?: 'No content',
            'urgency' => $this->mapPriorityToUrgency($priority),
            'impact' => 'Medium',
            'status' => 'Open',
            'channel' => 'email',
            'source' => $config ? "IMAP ({$config->name})" : 'IMAP',
            'case_id' => (string) \Illuminate\Support\Str::uuid(),
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

        // Push to Mitel Ignite so agents receive it in their inbox
        $this->pushEmailToMitel($ticket, $emailMessage, $config, $previousTicket);

        // Update subject to include ticket number for future replies
        $this->sendAutoReply($emailMessage, $ticket);

        return $ticket;
    }

    /**
     * Create an OpenMedia interaction in Mitel so the email appears in agent inboxes.
     */
    protected function pushEmailToMitel(Ticket $ticket, EmailMessage $emailMessage, ?EmailConfiguration $config = null, ?Ticket $previousTicket = null): void
    {
        try {
            $queue = $config?->settings['mitel_queue']
                ?? config('services.mitel.email_queue', 'KASTAM-Email');

            $interactionId = (string) Str::uuid();
            $frontendUrl   = rtrim(config('app.frontend_url', 'https://cxsuite.telediant.com.my'), '/');
            $targetUri     = "{$frontendUrl}/email-interaction?ticket={$ticket->ticket_number}";
            // Append previous ticket reference so the agent can see the old thread for context
            if ($previousTicket) {
                $targetUri .= "&ref={$previousTicket->ticket_number}";
            }

            $mitel  = app(MitelService::class);
            $result = $mitel->escalateToAgent(
                queue:             $queue,
                vars:              [
                    'Name'    => $emailMessage->from_name ?: $emailMessage->from_email,
                    'Email'   => $emailMessage->from_email,
                    'Subject' => $emailMessage->subject,
                    'Channel' => 'Email',
                ],
                id:                $interactionId,
                targetUri:         $targetUri,
                targetUriEmbedded: true,
                subject:           $emailMessage->subject ?: 'Email — ' . $emailMessage->from_email,
                from:              $emailMessage->from_name ?: $emailMessage->from_email,
            );

            $interactionId = $result['_interaction_id'] ?? $interactionId;
            $ticket->mitel_interaction_id = $interactionId;
            $ticket->save();

            Log::info('[email:process] Pushed to Mitel queue', [
                'ticket'         => $ticket->ticket_number,
                'queue'          => $queue,
                'interaction_id' => $interactionId,
            ]);
        } catch (\Exception $e) {
            Log::error('[email:process] Failed to push email to Mitel', [
                'ticket' => $ticket->ticket_number,
                'error'  => $e->getMessage(),
            ]);
            // Non-fatal: ticket was created; just won't appear in Ignite immediately
        }
    }

    /**
     * Update existing ticket with new email.
     */
    protected function updateExistingTicket(Ticket $ticket, EmailMessage $emailMessage): void
    {
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
        // Try department match first
        if ($department) {
            $agent = Agent::where('status', 'active')
                ->where('department', $department)
                ->where('availability', 'available')
                ->first();
            if ($agent) return $agent;

            // Department match without availability filter (any active agent in dept)
            $agent = Agent::where('status', 'active')
                ->where('department', $department)
                ->first();
            if ($agent) return $agent;
        }

        // Fallback: any active available agent
        return Agent::where('status', 'active')
            ->where('availability', 'available')
            ->first()
            // Last resort: any active agent
            ?? Agent::where('status', 'active')->first();
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
