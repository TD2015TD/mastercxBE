<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'id_type',
        'id_no',
        'company_name',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'customer_type',
        'business_registration_no',
        'preferred_language',
        'status',
        'total_tickets',
        'resolved_tickets',
        'satisfaction_rating',
        'last_contact_at',
        'tags',
        'notes',
    ];

    protected $casts = [
        'tags' => 'array',
        'total_tickets' => 'integer',
        'resolved_tickets' => 'integer',
        'satisfaction_rating' => 'decimal:2',
        'last_contact_at' => 'datetime',
    ];

    /**
     * Get all tickets for the customer.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Get all interactions for the customer.
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(CustomerInteraction::class);
    }

    /**
     * Get all attachments for the customer.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(CustomerAttachment::class);
    }

    /**
     * Get all notes for the customer.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    /**
     * Scope a query to only include active customers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to filter by customer type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('customer_type', $type);
    }

    /**
     * Get customer's resolution rate.
     */
    public function getResolutionRateAttribute()
    {
        if ($this->total_tickets == 0) {
            return 0;
        }
        return round(($this->resolved_tickets / $this->total_tickets) * 100, 2);
    }

    /**
     * Get open tickets count.
     */
    public function getOpenTicketsCountAttribute()
    {
        return $this->tickets()->whereIn('status', ['Open', 'In Progress', 'Pending'])->count();
    }

    /**
     * Update customer metrics.
     */
    public function updateMetrics()
    {
        $this->total_tickets = $this->tickets()->count();
        $this->resolved_tickets = $this->tickets()->whereIn('status', ['Resolved', 'Closed'])->count();
        $this->last_contact_at = now();
        $this->save();
    }

    /**
     * Format ID number based on type.
     */
    public function getFormattedIdAttribute()
    {
        return "{$this->id_type}: {$this->id_no}";
    }
}
