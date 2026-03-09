<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SlaConfiguration extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'department',
        'priority',
        'duration_minutes',
        'compliance_target',
        'business_hours_only',
        'pause_on_hold',
        'pause_on_pending',
        'escalation_threshold_percent',
        'description',
        'is_active',
    ];

    protected $casts = [
        'duration_minutes' => 'integer',
        'compliance_target' => 'decimal:2',
        'business_hours_only' => 'boolean',
        'pause_on_hold' => 'boolean',
        'pause_on_pending' => 'boolean',
        'escalation_threshold_percent' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to get active configurations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by department.
     */
    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope to filter by priority.
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Get SLA configuration for a specific department and priority.
     */
    public static function getSlaForTicket($department, $priority)
    {
        return static::active()
            ->where('department', $department)
            ->where('priority', $priority)
            ->first();
    }

    /**
     * Get duration in hours.
     */
    public function getDurationHoursAttribute()
    {
        return round($this->duration_minutes / 60, 2);
    }

    /**
     * Get duration in days.
     */
    public function getDurationDaysAttribute()
    {
        return round($this->duration_minutes / 1440, 2);
    }
}
