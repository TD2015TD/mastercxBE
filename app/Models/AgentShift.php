<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AgentShift extends Model
{
    protected $fillable = [
        'agent_id',
        'shift_date',
        'shift_start',
        'shift_end',
        'shift_type',       // morning, afternoon, evening, night
        'break_duration',   // break duration in minutes
        'is_off_day',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'agent_id' => 'integer',
            'shift_date' => 'date',
            'shift_start' => 'datetime',
            'shift_end' => 'datetime',
            'break_duration' => 'integer',
            'is_off_day' => 'boolean',
        ];
    }

    /**
     * Get the agent that owns this shift.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Scope to get shifts for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('shift_date', Carbon::today());
    }

    /**
     * Scope to get shifts for a specific date.
     */
    public function scopeForDate($query, Carbon|string $date)
    {
        return $query->whereDate('shift_date', $date);
    }

    /**
     * Scope to get shifts within a date range.
     */
    public function scopeDateRange($query, Carbon|string $startDate, Carbon|string $endDate)
    {
        return $query->whereBetween('shift_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get shifts for a specific agent.
     */
    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Scope to filter working shifts (not off days).
     */
    public function scopeWorkingDays($query)
    {
        return $query->where('is_off_day', false);
    }

    /**
     * Scope to filter off days.
     */
    public function scopeOffDays($query)
    {
        return $query->where('is_off_day', true);
    }

    /**
     * Scope to filter by shift type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('shift_type', $type);
    }

    /**
     * Calculate shift duration in hours.
     */
    public function getShiftDurationAttribute(): float
    {
        if ($this->is_off_day || !$this->shift_start || !$this->shift_end) {
            return 0;
        }

        $duration = $this->shift_start->diffInMinutes($this->shift_end);
        $duration -= $this->break_duration ?? 0;
        
        return round($duration / 60, 2);
    }

    /**
     * Check if shift is currently active.
     */
    public function isActive(): bool
    {
        if ($this->is_off_day) {
            return false;
        }

        $now = now();
        return $this->shift_date->isToday() 
            && $now->between($this->shift_start, $this->shift_end);
    }

    /**
     * Get formatted shift summary.
     */
    public function getSummaryAttribute(): array
    {
        return [
            'date' => $this->shift_date->format('Y-m-d'),
            'type' => $this->shift_type,
            'start' => $this->shift_start?->format('H:i'),
            'end' => $this->shift_end?->format('H:i'),
            'duration_hours' => $this->shift_duration,
            'break_minutes' => $this->break_duration,
            'is_off_day' => $this->is_off_day,
            'is_active' => $this->isActive(),
        ];
    }
}
