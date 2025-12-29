<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TicketCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $ticket;
    protected $eventType;

    /**
     * Create a new notification instance.
     *
     * @param Ticket $ticket
     * @param string $eventType (created, assigned, etc.)
     */
    public function __construct(Ticket $ticket, string $eventType = 'created')
    {
        $this->ticket = $ticket;
        $this->eventType = $eventType;

        // Set queue name for async processing
        $this->onQueue('notifications');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Use database and broadcast channels (no email to avoid spam)
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $message = $this->getMessage($notifiable);

        return [
            'type' => $this->getNotificationType(),
            'level' => $this->getNotificationLevel(),
            'message' => $message,
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'priority' => $this->ticket->priority,
            'department' => $this->ticket->department,
            'category' => $this->ticket->category,
            'status' => $this->ticket->status,
            'customer_name' => $this->ticket->customer?->name,
            'url' => "/ticket-management/{$this->ticket->id}",
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Get notification type based on event type.
     */
    private function getNotificationType(): string
    {
        return match($this->eventType) {
            'created' => 'ticket_created',
            'assigned' => 'ticket_assigned',
            'escalated' => 'ticket_escalated',
            default => 'ticket_notification',
        };
    }

    /**
     * Get notification level based on priority.
     */
    private function getNotificationLevel(): string
    {
        return match($this->ticket->priority) {
            'P1' => 'critical',
            'P2' => 'warning',
            'P3' => 'info',
            'P4' => 'info',
            default => 'info',
        };
    }

    /**
     * Generate notification message based on event type and user role.
     */
    private function getMessage(object $notifiable): string
    {
        $ticketNumber = $this->ticket->ticket_number;
        $customerName = $this->ticket->customer?->name ?? 'Unknown Customer';
        $channel = ucfirst($this->ticket->channel);

        return match($this->eventType) {
            'created' => "New ticket #{$ticketNumber} created via {$channel} from {$customerName}",
            'assigned' => "Ticket #{$ticketNumber} has been assigned to you - {$customerName}",
            'escalated' => "Ticket #{$ticketNumber} has been escalated to you - {$customerName}",
            default => "Ticket #{$ticketNumber} updated",
        };
    }
}
