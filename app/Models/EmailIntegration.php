<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailIntegration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'message_id',
        'ticket_id',
        'customer_id',
        'from_email',
        'to_email',
        'cc_emails',
        'subject',
        'body',
        'body_html',
        'received_at',
        'processed_at',
        'status',
        'error_message',
        'attachments',
        'headers',
    ];

    protected $casts = [
        'cc_emails' => 'array',
        'attachments' => 'array',
        'headers' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the ticket created from this email.
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the customer associated with this email.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scope for pending emails.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for processed emails.
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }

    /**
     * Scope for failed emails.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Mark as processed.
     */
    public function markProcessed()
    {
        $this->status = 'processed';
        $this->processed_at = now();
        $this->save();
    }

    /**
     * Mark as failed.
     */
    public function markFailed($errorMessage)
    {
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        $this->processed_at = now();
        $this->save();
    }
}
