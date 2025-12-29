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
     * Looks for patterns like [TKT-12345] or #TKT-12345
     */
    public static function extractTicketNumber(?string $subject): ?string
    {
        if (!$subject) {
            return null;
        }

        // Pattern for ticket numbers: [TKT...], #TKT..., or RE: [TKT...]
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
