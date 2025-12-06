<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

class SlaAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $data;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->data['ticket'];
        $level = $this->data['level'];
        $message = $this->data['data']['message'] ?? 'SLA Alert';

        return (new MailMessage)
            ->subject("SLA Alert: Ticket #{$ticket->ticket_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line($message)
            ->line("Ticket: #{$ticket->ticket_number}")
            ->line("Priority: {$ticket->priority}")
            ->line("Department: {$ticket->department}")
            ->action('View Ticket', url("/tickets/{$ticket->id}"))
            ->line('Please take immediate action to prevent SLA breach.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $ticket = $this->data['ticket'];
        $level = $this->data['level'];
        $alertData = $this->data['data'];

        return [
            'type' => 'sla_alert',
            'level' => $level,
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'priority' => $ticket->priority,
            'department' => $ticket->department,
            'message' => $alertData['message'] ?? 'SLA Alert',
            'remaining' => $alertData['remaining'] ?? null,
            'overdue_by' => $alertData['overdue_by'] ?? null,
            'url' => url("/tickets/{$ticket->id}"),
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
}
