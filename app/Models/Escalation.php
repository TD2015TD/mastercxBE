<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Escalation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'from_agent_id',
        'to_agent_id',
        'escalated_by',
        'level',
        'reason',
        'notes',
        'escalation_type',
        'status',
        'escalated_at',
        'acknowledged_at',
        'resolved_at',
    ];

    protected $casts = [
        'escalated_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the ticket that was escalated.
     */
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the agent who escalated from.
     */
    public function fromAgent()
    {
        return $this->belongsTo(Agent::class, 'from_agent_id');
    }

    /**
     * Get the agent escalated to.
     */
    public function toAgent()
    {
        return $this->belongsTo(Agent::class, 'to_agent_id');
    }

    /**
     * Get the user who triggered escalation.
     */
    public function escalatedBy()
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    /**
     * Scope for pending escalations.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for acknowledged escalations.
     */
    public function scopeAcknowledged($query)
    {
        return $query->where('status', 'acknowledged');
    }

    /**
     * Scope for resolved escalations.
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    /**
     * Acknowledge the escalation.
     */
    public function acknowledge()
    {
        $this->status = 'acknowledged';
        $this->acknowledged_at = now();
        $this->save();
    }

    /**
     * Mark escalation as resolved.
     */
    public function markResolved()
    {
        $this->status = 'resolved';
        $this->resolved_at = now();
        $this->save();
    }
}
