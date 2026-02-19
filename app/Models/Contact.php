<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'contact_type_id',
        'value',
        'is_primary',
        'notes',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    /**
     * Get the customer for this contact.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the contact type lookup.
     */
    public function contactType(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'contact_type_id');
    }

    /**
     * Scope to get primary contacts.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to filter by contact type.
     */
    public function scopeByType($query, $contactTypeId)
    {
        return $query->where('contact_type_id', $contactTypeId);
    }

    /**
     * Scope to search by value.
     */
    public function scopeByValue($query, $value)
    {
        return $query->where('value', 'like', '%' . $value . '%');
    }

    /**
     * Scope to search by customer.
     */
    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }
}

