<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
     *
     * Matches on the normalised department so a code ("SALES TAX"), a display name
     * ("Sales Tax") and a stray-whitespace variant all resolve to the same rows.
     */
    public function scopeByDepartment($query, $department)
    {
        $normalized = static::normalizeDepartment($department);

        if ($normalized === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw('UPPER(TRIM(department)) = ?', [Str::upper($normalized)]);
    }

    /**
     * Scope to filter by priority.
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Resolve a department value to its canonical lookup code.
     *
     * Tickets carry the department lookup code ("SALES TAX"); older SLA rows (and anything
     * hand-entered) may carry the display name ("Sales Tax") or a different case. Both must
     * resolve to the same policy, otherwise a configured SLA silently never applies.
     * Returns the input trimmed when it matches no lookup, so free-text departments still work.
     */
    public static function normalizeDepartment($department): ?string
    {
        if ($department === null || trim((string) $department) === '') {
            return null;
        }

        $needle = trim((string) $department);
        $upper = Str::upper($needle);

        $lookup = DB::table('lookups')
            ->where('type', 'department')
            ->whereNull('deleted_at')
            ->where(function ($query) use ($upper) {
                $query->whereRaw('UPPER(TRIM(code)) = ?', [$upper])
                    ->orWhereRaw('UPPER(TRIM(name_en)) = ?', [$upper])
                    ->orWhereRaw('UPPER(TRIM(name_ms)) = ?', [$upper]);
            })
            ->first();

        return $lookup->code ?? $needle;
    }

    /**
     * Get SLA configuration for a specific department and priority.
     *
     * Returns null when nothing is configured — callers must treat that as "no SLA applies"
     * rather than falling back to a default duration.
     */
    public static function getSlaForTicket($department, $priority)
    {
        $normalized = static::normalizeDepartment($department);

        if ($normalized === null || empty($priority)) {
            return null;
        }

        return static::active()
            ->byDepartment($normalized)
            ->where('priority', $priority)
            ->orderByDesc('id')
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
