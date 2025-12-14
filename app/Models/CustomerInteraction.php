<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerInteraction extends Model
{
    use SoftDeletes;

    /**
     * Valid interaction types
     */
    public const INTERACTION_TYPES = ['call', 'email', 'chat', 'sms', 'meeting', 'note'];

    /**
     * Valid channels
     */
    public const CHANNELS = ['phone', 'email', 'chat', 'web', 'walk-in', 'social_media'];

    protected $fillable = [
        'customer_id',
        'ticket_id',
        'agent_id',
        'conversation_id',
        'interaction_type',
        'channel',
        'subject',
        'description',
        'duration_minutes',
        'sentiment',
        'notes',
        'metadata',
        'interaction_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'duration_minutes' => 'integer',
        'interaction_at' => 'datetime',
    ];

    /**
     * Get the customer for this interaction.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the ticket for this interaction.
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the agent for this interaction.
     */
    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Scope to filter by customer.
     */
    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to filter by interaction type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('interaction_type', $type);
    }

    /**
     * Scope to filter by channel.
     */
    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope to get recent interactions.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('interaction_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to filter by conversation ID.
     */
    public function scopeByConversation($query, $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }
}
