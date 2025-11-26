<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'agent_code',
        'employee_id',
        'department',
        'designation',
        'mitel_agent_id',
        'mitel_extension',
        'mitel_queue_ids',
        'status',
        'availability',
        'skills',
        'languages',
        'max_concurrent_tickets',
        'tickets_resolved_today',
        'daily_target',
        'avg_response_time',
        'sla_compliance_rate',
        'customer_satisfaction',
        'shift_start',
        'shift_end',
        'working_days',
        'notes',
        'last_activity_at',
    ];

    protected $casts = [
        'skills' => 'array',
        'languages' => 'array',
        'mitel_queue_ids' => 'array',
        'working_days' => 'array',
        'tickets_resolved_today' => 'integer',
        'daily_target' => 'integer',
        'max_concurrent_tickets' => 'integer',
        'avg_response_time' => 'decimal:2',
        'sla_compliance_rate' => 'decimal:2',
        'customer_satisfaction' => 'decimal:2',
        'shift_start' => 'datetime:H:i',
        'shift_end' => 'datetime:H:i',
        'last_activity_at' => 'datetime',
    ];

    /**
     * Get the user that owns the agent profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get tickets assigned to this agent.
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    /**
     * Get tickets created by this agent.
     */
    public function createdTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'created_by');
    }

    /**
     * Scope a query to only include active agents.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include available agents.
     */
    public function scopeAvailable($query)
    {
        return $query->where('availability', 'available');
    }

    /**
     * Scope a query to filter by department.
     */
    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Get agent's full information including user details.
     */
    public function getFullProfileAttribute()
    {
        return [
            'id' => $this->id,
            'agent_code' => $this->agent_code,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'department' => $this->department,
            'designation' => $this->designation,
            'status' => $this->status,
            'availability' => $this->availability,
            'skills' => $this->skills,
            'languages' => $this->languages,
            'performance' => [
                'tickets_resolved_today' => $this->tickets_resolved_today,
                'daily_target' => $this->daily_target,
                'avg_response_time' => $this->avg_response_time,
                'sla_compliance_rate' => $this->sla_compliance_rate,
                'customer_satisfaction' => $this->customer_satisfaction,
            ],
        ];
    }
}
