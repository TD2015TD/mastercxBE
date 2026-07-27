<?php

namespace App\Services;

use App\Models\EmailMessage;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared email-thread/reply logic used by both the normal JWT-authenticated
 * ticket endpoints (TicketController) and the token-authenticated public
 * endpoints (PublicTicketController) that back the Mitel Ignite embedded
 * iframe — that browsing context can't see the agent's localStorage login,
 * so it can't hit the normal auth()->user()-dependent code path directly.
 */
class TicketConversationService
{
    public function getThreadData(Ticket $ticket, ?string $refNumber = null): array
    {
        $ticket->loadMissing(['customer', 'assignedAgent.user']);

        $messages = $this->mapMessages(EmailMessage::where('ticket_id', $ticket->id));

        $previousTicketData = null;
        $previousMessages   = [];
        if ($refNumber) {
            $refTicket = Ticket::where('ticket_number', $refNumber)->first();
            if ($refTicket) {
                $previousTicketData = [
                    'ticket_number' => $refTicket->ticket_number,
                    'subject'       => $refTicket->subject,
                    'status'        => $refTicket->status,
                    'created_at'    => $refTicket->created_at?->toISOString(),
                    'closed_at'     => $refTicket->closed_at?->toISOString(),
                ];
                $previousMessages = $this->mapMessages(EmailMessage::where('ticket_id', $refTicket->id));
            }
        }

        return [
            'ticket' => [
                'id'                   => $ticket->id,
                'ticket_number'        => $ticket->ticket_number,
                'subject'              => $ticket->subject,
                'status'               => $ticket->status,
                'priority'             => $ticket->priority,
                'department'           => $ticket->department,
                'category'             => $ticket->category,
                'channel'              => $ticket->channel,
                'mitel_interaction_id' => $ticket->mitel_interaction_id,
                'customer' => $ticket->customer ? [
                    'name'  => $ticket->customer->name,
                    'email' => $ticket->customer->email,
                    'phone' => $ticket->customer->phone,
                ] : null,
                'assigned_to'             => $ticket->assigned_to,
                'assigned_agent'          => $ticket->assignedAgent?->user?->name,
                'created_at'              => $ticket->created_at?->toISOString(),
                'resolved_at'             => $ticket->resolved_at?->toISOString(),
                'closed_at'               => $ticket->closed_at?->toISOString(),
                'resolution_time_minutes' => $ticket->resolution_time_minutes,
                'sla_due_at'              => $ticket->sla_due_at?->toISOString(),
                'sla_breached'            => (bool) $ticket->sla_breached,
            ],
            'messages'          => $messages,
            'previous_ticket'   => $previousTicketData,
            'previous_messages' => $previousMessages,
        ];
    }

    /**
     * @param array<int, \Illuminate\Http\UploadedFile> $files
     * @return array{success: bool, message: string}
     */
    public function sendReply(Ticket $ticket, string $bodyHtml, ?string $toOverride, array $files, string $agentName): array
    {
        $toEmail = $toOverride
            ?? $ticket->customer?->email
            ?? EmailMessage::where('ticket_id', $ticket->id)->value('from_email');

        if (!$toEmail) {
            return ['success' => false, 'message' => 'No recipient email address found'];
        }

        try {
            $subject   = 'Re: ' . $ticket->subject . ' [' . $ticket->ticket_number . ']';
            $bodyText  = strip_tags($bodyHtml);
            $fromEmail = config('mail.from.address');
            $fromName  = config('mail.from.name', config('app.name'));

            $previousMessages = EmailMessage::where('ticket_id', $ticket->id)
                ->orderBy('received_at', 'asc')
                ->get();

            $lastInbound = $previousMessages->where('direction', 'inbound')->last()
                ?? $previousMessages->first();
            $inReplyToId = $lastInbound?->message_id;

            $referencesHeader = $previousMessages
                ->pluck('message_id')
                ->filter()
                ->implode(' ');

            $threadId = $previousMessages->whereNotNull('conversation_thread_id')
                ->first()?->conversation_thread_id;

            $quotedHtml = $this->buildQuotedHtml($lastInbound);

            $footerForDb = $this->buildEmailFooter($ticket, $agentName, useUrl: true);
            $htmlForDb   = $bodyHtml . $footerForDb;

            $storedAttachments = [];
            foreach ($files as $file) {
                $path = $file->store('email_attachments/' . date('Y/m/d'), 'public');
                $storedAttachments[] = [
                    'filename'  => $file->getClientOriginalName(),
                    'path'      => $path,
                    'url'       => rtrim(config('app.frontend_url', config('app.url')), '/') . '/storage/' . $path,
                    'size'      => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ];
            }

            $msgDomain = parse_url(config('app.frontend_url', config('app.url')), PHP_URL_HOST) ?: 'mail.local';
            $messageId = '<' . Str::uuid() . '@' . $msgDomain . '>';

            $attachments = $storedAttachments;
            Mail::send([], [], function ($msg) use (
                $toEmail, $subject, $fromEmail, $fromName, $bodyHtml, $bodyText,
                $attachments, $inReplyToId, $referencesHeader,
                $ticket, $agentName, $quotedHtml, $messageId
            ) {
                $logoPath = public_path('logo-kastam.png');
                $logoCid  = file_exists($logoPath) ? $msg->embed($logoPath) : null;
                $footerForEmail = $logoCid
                    ? $this->buildEmailFooter($ticket, $agentName, useUrl: false, cidSrc: $logoCid)
                    : $this->buildEmailFooter($ticket, $agentName, useUrl: true);
                $fullHtml = $bodyHtml . $footerForEmail . $quotedHtml;

                $msg->to($toEmail)
                    ->subject($subject)
                    ->from($fromEmail, $fromName)
                    ->html($fullHtml)
                    ->text($bodyText);

                $symfonyMsg = $msg->getSymfonyMessage();
                $headers    = $symfonyMsg->getHeaders();
                if ($headers->has('Message-ID')) {
                    $headers->remove('Message-ID');
                }
                $headers->addIdHeader('Message-ID', trim($messageId, '<>'));
                if ($inReplyToId) {
                    $headers->addTextHeader('In-Reply-To', $inReplyToId);
                }
                if ($referencesHeader) {
                    $headers->addTextHeader('References', $referencesHeader);
                }

                foreach ($attachments as $att) {
                    $fullPath = Storage::disk('public')->path($att['path']);
                    $msg->attach($fullPath, [
                        'as'   => $att['filename'],
                        'mime' => $att['mime_type'],
                    ]);
                }
            });

            if (!$threadId) {
                $threadId = md5($messageId);
            }

            EmailMessage::create([
                'message_id'             => $messageId,
                'ticket_id'              => $ticket->id,
                'from_email'             => $fromEmail,
                'from_name'              => $fromName,
                'to_email'               => $toEmail,
                'subject'                => $subject,
                'body_html'              => $htmlForDb,
                'body_text'              => $bodyText,
                'attachments'            => $storedAttachments ?: null,
                'direction'              => 'outbound',
                'received_at'            => now(),
                'is_processed'           => true,
                'conversation_thread_id' => $threadId,
                'in_reply_to'            => $inReplyToId,
                'references'             => $referencesHeader ?: null,
            ]);

            $ticket->logHistory('reply_sent', null, null, null, "Email reply sent to {$toEmail}");

            return ['success' => true, 'message' => 'Reply sent successfully'];
        } catch (\Exception $e) {
            Log::error('TicketConversationService::sendReply failed: ' . $e->getMessage(), ['ticket' => $ticket->ticket_number]);
            return ['success' => false, 'message' => 'Failed to send reply: ' . $e->getMessage()];
        }
    }

    private function mapMessages($query)
    {
        return $query->orderBy('received_at', 'asc')->get()
            ->map(fn ($m) => [
                'id'          => $m->id,
                'from_email'  => $m->from_email,
                'from_name'   => $m->from_name,
                'to_email'    => $m->to_email,
                'subject'     => $m->subject,
                'body_text'   => $m->body_text,
                'body_html'   => $m->body_html,
                'attachments' => collect($m->attachments ?? [])->map(function ($att) {
                    if (!empty($att['path'])) {
                        $att['url'] = rtrim(config('app.frontend_url', 'https://cxsuite.telediant.com.my'), '/')
                            . '/storage/' . $this->encodeAttachmentPathForUrl($att['path']);
                    }
                    return $att;
                })->values()->all(),
                'received_at' => $m->received_at?->toISOString(),
                'direction'   => $m->direction ?? 'inbound',
            ]);
    }

    private function encodeAttachmentPathForUrl(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    private function buildQuotedHtml(?EmailMessage $original): string
    {
        if (!$original) return '';

        $date    = $original->received_at?->format('D, d M Y \a\t H:i T') ?? '';
        $from    = $original->from_name
            ? htmlspecialchars($original->from_name) . ' &lt;' . htmlspecialchars($original->from_email) . '&gt;'
            : htmlspecialchars($original->from_email);
        $content = $original->body_html ?: nl2br(htmlspecialchars($original->body_text ?? ''));

        return <<<HTML

<br>
<div class="gmail_quote">
  <div dir="ltr" style="font-size:13px;color:#6b7280;margin-bottom:4px;">On {$date}, {$from} wrote:</div>
  <blockquote class="gmail_quote" style="margin:0 0 0 0.8ex;border-left:1px solid #ccc;padding-left:1ex;">
    {$content}
  </blockquote>
</div>
HTML;
    }

    private function buildEmailFooter(Ticket $ticket, string $agentName = '', bool $useUrl = true, ?string $cidSrc = null): string
    {
        $fromEmail = config('mail.from.address', 'appsdev@tde.com.my');
        $ticketNo  = $ticket->ticket_number;
        $nameHtml  = $agentName
            ? "<strong style=\"font-size:14px;color:#111827;\">{$agentName}</strong><br>"
            : '';

        if ($useUrl) {
            $logoUrl = rtrim(config('app.frontend_url', 'https://cxsuite.telediant.com.my'), '/') . '/logo-kastam.png';
            $logoImg = "<img src=\"{$logoUrl}\" alt=\"Kastam\" style=\"height:52px;width:auto;display:block;\">";
        } else {
            $logoImg = $cidSrc
                ? "<img src=\"{$cidSrc}\" alt=\"Kastam\" style=\"height:52px;width:auto;display:block;\">"
                : '';
        }

        return <<<HTML

<br><hr style="border:none;border-top:1px solid #e5e7eb;margin:24px 0;">
<table style="font-family:Arial,sans-serif;font-size:12px;color:#6b7280;line-height:1.7;" cellpadding="0" cellspacing="0">
  <tr>
    <td style="padding-right:16px;vertical-align:middle;">
      {$logoImg}
    </td>
    <td style="vertical-align:middle;border-left:3px solid #e5e7eb;padding-left:16px;">
      {$nameHtml}<span style="color:#374151;font-weight:600;">Customer Support Team</span><br>
      <span style="color:#6b7280;">CX Ignite &bull; {$fromEmail}</span><br>
      <span style="color:#9ca3af;font-size:11px;">Ticket Reference: <strong style="color:#374151;">{$ticketNo}</strong></span>
    </td>
  </tr>
</table>
HTML;
    }
}
