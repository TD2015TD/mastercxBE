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
        // Mitel Telephony Integration - Real-time State Only
        'mitel_agent_id',
        'mitel_extension',
        'mitel_queue_ids',
        'mitel_state',              // Current state: Available, Busy, DoNotDisturb, Away, Logout
        'mitel_state_reason',       // Reason for state change (e.g., "Break", "Training")
        'mitel_logged_in',          // Boolean: Is agent currently logged into Mitel
        'mitel_last_state_change',  // Timestamp of last state change
        // General Status
        'status',
        'availability',
        'skills',
        'languages',
        'max_concurrent_tickets',
        'notes',
        'last_activity_at',
    ];

    protected $casts = [
        'skills' => 'array',
        'languages' => 'array',
        'mitel_queue_ids' => 'array',
        'max_concurrent_tickets' => 'integer',
        // Mitel telephony fields
        'mitel_logged_in' => 'boolean',
        'mitel_last_state_change' => 'datetime',
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
     * Get call statistics history for this agent.
     */
    public function callStatistics(): HasMany
    {
        return $this->hasMany(AgentCallStatistic::class);
    }

    /**
     * Get today's call statistics for this agent.
     */
    public function todayStatistics()
    {
        return $this->callStatistics()
                    ->whereDate('statistic_date', today())
                    ->first();
    }

    /**
     * Get shift schedules for this agent.
     */
    public function shifts(): HasMany
    {
        return $this->hasMany(AgentShift::class);
    }

    /**
     * Get current active shift for this agent.
     */
    public function currentShift()
    {
        return $this->shifts()
                    ->where('shift_date', today())
                    ->first();
    }

    /**
     * Get performance metrics history for this agent.
     */
    public function performanceMetrics(): HasMany
    {
        return $this->hasMany(AgentPerformanceMetric::class);
    }

    /**
     * Get today's performance metrics for this agent.
     */
    public function todayPerformance()
    {
        return $this->performanceMetrics()
                    ->whereDate('metric_date', today())
                    ->first();
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
     * Scope to filter agents currently logged into Mitel.
     */
    public function scopeMitelLoggedIn($query)
    {
        return $query->where('mitel_logged_in', true);
    }

    /**
     * Scope to filter agents by Mitel state.
     */
    public function scopeByMitelState($query, $state)
    {
        return $query->where('mitel_state', $state);
    }

    /**
     * Scope to filter agents available for calls.
     */
    public function scopeMitelAvailable($query)
    {
        return $query->where('mitel_logged_in', true)
                    ->where('mitel_state', 'Available');
    }

    /**
     * Update agent's Mitel state.
     */
    public function updateMitelState(string $state, ?string $reason = null): bool
    {
        return $this->update([
            'mitel_state' => $state,
            'mitel_state_reason' => $reason,
            'mitel_last_state_change' => now(),
            'mitel_logged_in' => $state !== 'Logout',
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Check if agent is currently available for calls.
     */
    public function isAvailableForCalls(): bool
    {
        return $this->mitel_logged_in && 
               $this->mitel_state === 'Available' && 
               $this->status === 'active';
    }

    /**
     * Get agent's telephony status.
     */
    public function getTelephonyStatusAttribute(): array
    {
        return [
            'logged_in' => $this->mitel_logged_in,
            'state' => $this->mitel_state,
            'state_reason' => $this->mitel_state_reason,
            'last_state_change' => $this->mitel_last_state_change,
            'available_for_calls' => $this->isAvailableForCalls(),
        ];
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
            'mitel' => [
                'agent_id' => $this->mitel_agent_id,
                'extension' => $this->mitel_extension,
                'queue_ids' => $this->mitel_queue_ids,
                'logged_in' => $this->mitel_logged_in,
                'state' => $this->mitel_state,
                'state_reason' => $this->mitel_state_reason,
                'available_for_calls' => $this->isAvailableForCalls(),
            ],
        ];
    }
}
