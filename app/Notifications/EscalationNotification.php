<?php

namespace App\Notifications;

use App\Models\Escalation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * Notifies an escalation's target agent that a ticket was routed to them.
 *
 * Without this, a target could only ever discover a pending escalation by already knowing which
 * ticket it landed on — nothing surfaced it in their notification list.
 *
 * Deliberately NOT ShouldQueue: this server has no queue worker running (no
 * systemd unit or supervisor installed for `queue:work`), so a queued
 * notification never actually gets processed — it just sits in the jobs
 * table forever, meaning nothing was ever written to the notifications
 * table and nothing was ever broadcast. Sent synchronously instead, same
 * reasoning as SignalingEvent's ShouldBroadcastNow.
 */
class EscalationNotification extends Notification
{
    protected Escalation $escalation;

    public function __construct(Escalation $escalation)
    {
        $this->escalation = $escalation;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $ticket = $this->escalation->ticket;

        return (new MailMessage)
            ->subject("Ticket Escalated to You: #{$ticket->ticket_number}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Ticket #{$ticket->ticket_number} has been escalated to you at level {$this->escalation->level}.")
            ->line("Reason: {$this->escalation->reason}")
            ->line("Priority: {$ticket->priority}")
            ->line("Department: {$ticket->department}")
            ->action('View Ticket', rtrim(config('app.frontend_url', 'https://cxsuite.telediant.com.my'), '/') . "/ticket-management/{$ticket->id}")
            ->line('Please acknowledge this escalation.');
    }

    public function toArray(object $notifiable): array
    {
        $ticket = $this->escalation->ticket;

        return [
            'type' => 'ticket_escalated',
            'level' => $this->escalation->level,
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'priority' => $ticket->priority,
            'department' => $ticket->department,
            'message' => "Ticket #{$ticket->ticket_number} escalated to you ({$this->escalation->level}): {$this->escalation->reason}",
            'escalation_id' => $this->escalation->id,
            'escalation_type' => $this->escalation->escalation_type,
            // Relative path, not a full url() — this is fed straight into React
            // Router's navigate() on the frontend (NotificationCenter.jsx), which
            // expects an in-app path. url("/tickets/{id}") previously generated
            // "http://localhost/tickets/{id}" (APP_URL's default) — wrong domain
            // AND a path ("/tickets/...") that isn't even a real frontend route
            // (the actual route is "/ticket-management/:id", confirmed in
            // Routes.tsx) — so clicking this notification always 404'd.
            'url' => "/ticket-management/{$ticket->id}",
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
