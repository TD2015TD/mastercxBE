<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailMessage extends Model
{
    protected $fillable = [
        'message_id',
        'subject',
        'from_email',
        'from_name',
        'to_email',
        'cc',
        'bcc',
        'reply_to',
        'body_text',
        'body_html',
        'received_at',
        'ticket_id',
        'customer_id',
        'is_processed',
        'processed_at',
        'headers',
        'attachments',
        'conversation_thread_id',
        'in_reply_to',
        'references',
        'raw_message',
        'direction',
    ];

    protected $casts = [
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'is_processed' => 'boolean',
        'headers' => 'array',
        'attachments' => 'array',
    ];

    /**
     * Get the ticket associated with this email.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the customer associated with this email.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scope to get unprocessed emails.
     */
    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    /**
     * Scope to get processed emails.
     */
    public function scopeProcessed($query)
    {
        return $query->where('is_processed', true);
    }

    /**
     * Mark email as processed.
     */
    public function markAsProcessed(): void
    {
        $this->is_processed = true;
        $this->processed_at = now();
        $this->save();
    }

    /**
     * Extract ticket number from subject line if exists.
     * Matches bracketed references like [TE-260609-00044] or [TKT-20240101-001].
     */
    public static function extractTicketNumber(?string $subject): ?string
    {
        if (!$subject) {
            return null;
        }

        // Primary: bracketed ticket number e.g. [TE-260609-00044].
        // Use preg_match_all so that when a subject contains multiple ticket
        // numbers (e.g. "[TE-260615-00074] [TE-260616-00080]" from a chain of
        // tickets), we return the LAST one — that is always the most recently
        // opened ticket and is the one the reply belongs to.
        if (preg_match_all('/\[([A-Z]{1,10}-\d{4,8}-\d{3,8})\]/i', $subject, $matches)) {
            return strtoupper(end($matches[1]));
        }

        // Fallback: old TKT-style references [TKT-123] or TKT-123
        if (preg_match('/\[?(TKT[A-Z0-9\-]+)\]?/i', $subject, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    /**
     * Generate conversation thread ID from message headers.
     */
    public static function generateThreadId(?string $messageId, ?string $inReplyTo, ?string $references): string
    {
        // If in_reply_to exists, use it as base
        if ($inReplyTo) {
            return md5($inReplyTo);
        }

        // If references exist, use the first one
        if ($references) {
            $refs = explode(' ', trim($references));
            return md5($refs[0]);
        }

        // Otherwise use the message ID
        return md5($messageId ?? uniqid('email_', true));
    }
}
