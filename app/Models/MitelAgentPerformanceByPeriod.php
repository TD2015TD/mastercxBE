<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Mitel Agent Performance by Period
 * 
 * Stores granular agent performance metrics from Mitel's AgentPerformanceByPeriodStats view.
 * Provides interval-based data with detailed ACD, Non-ACD, and Outbound call breakdowns
 * including internal/external classification.
 * 
 * @property string $agent_id
 * @property \DateTime $interval_start_time
 * @property \DateTime $midnight_start_date
 * @property string $agent_reporting
 * @property string $employee_id
 * @property string $day_of_week_name
 * @property int $interval_number
 * @property int $agent_acd_count
 * @property int $agent_acd_duration
 * @property int $agent_acd_time_to_answer
 * @property int $agent_acd_internal_count
 * @property int $agent_acd_internal_duration
 * @property int $agent_acd_internal_time_to_answer
 * @property int $agent_acd_external_count
 * @property int $agent_acd_external_duration
 * @property int $agent_acd_external_time_to_answer
 * @property int $agent_non_acd_count
 * @property int $agent_non_acd_duration
 * @property int $agent_non_acd_time_to_answer
 * @property int $agent_non_acd_internal_count
 * @property int $agent_non_acd_internal_duration
 * @property int $agent_non_acd_internal_time_to_answer
 * @property int $agent_non_acd_external_count
 * @property int $agent_non_acd_external_duration
 * @property int $agent_non_acd_external_time_to_answer
 * @property int $agent_outbound_count
 * @property int $agent_outbound_duration
 * @property int $agent_outbound_internal_count
 * @property int $agent_outbound_internal_duration
 * @property int $agent_outbound_external_count
 * @property int $agent_outbound_external_duration
 * @property int $agent_transfer_count
 * @property int $agent_conference_count
 * @property int $agent_abandoned_count
 * @property int $agent_acw_duration
 * @property int $agent_available_duration
 * @property int $agent_unavailable_duration
 * @property int $agent_busy_duration
 * @property int $agent_system_busy_duration
 * @property int $agent_login_duration
 * @property \DateTime $agent_first_login_time
 * @property \DateTime $agent_last_logout_time
 * @property float $avg_speed_of_answer
 * @property float $avg_handle_time
 * @property float $occupancy_rate
 * @property \DateTime $synced_at
 * @property \DateTime $updated_at
 */
class MitelAgentPerformanceByPeriod extends Model
    /**
     * Get the agent's name from CRM (via employee_id).
     *
     * @return string|null
     */
    public function getAgentNameAttribute()
    {
        // Use the agent() relationship (employee_id join)
        return $this->agent ? ($this->agent->user->name ?? null) : null;
    }
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'mitel_agent_performance_by_period';

    /**
     * The primary key for the model (composite key).
     *
     * @var array
     */
    protected $primaryKey = ['agent_id', 'interval_start_time'];

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'agent_id' => 'string',
        'interval_start_time' => 'datetime',
        'midnight_start_date' => 'date',
        'interval_number' => 'integer',
        
        // ACD metrics
        'agent_acd_count' => 'integer',
        'agent_acd_duration' => 'integer',
        'agent_acd_time_to_answer' => 'integer',
        'agent_acd_internal_count' => 'integer',
        'agent_acd_internal_duration' => 'integer',
        'agent_acd_internal_time_to_answer' => 'integer',
        'agent_acd_external_count' => 'integer',
        'agent_acd_external_duration' => 'integer',
        'agent_acd_external_time_to_answer' => 'integer',
        
        // Non-ACD metrics
        'agent_non_acd_count' => 'integer',
        'agent_non_acd_duration' => 'integer',
        'agent_non_acd_time_to_answer' => 'integer',
        'agent_non_acd_internal_count' => 'integer',
        'agent_non_acd_internal_duration' => 'integer',
        'agent_non_acd_internal_time_to_answer' => 'integer',
        'agent_non_acd_external_count' => 'integer',
        'agent_non_acd_external_duration' => 'integer',
        'agent_non_acd_external_time_to_answer' => 'integer',
        
        // Outbound metrics
        'agent_outbound_count' => 'integer',
        'agent_outbound_duration' => 'integer',
        'agent_outbound_internal_count' => 'integer',
        'agent_outbound_internal_duration' => 'integer',
        'agent_outbound_external_count' => 'integer',
        'agent_outbound_external_duration' => 'integer',
        
        // Other metrics
        'agent_transfer_count' => 'integer',
        'agent_conference_count' => 'integer',
        'agent_abandoned_count' => 'integer',
        'agent_acw_duration' => 'integer',
        'agent_available_duration' => 'integer',
        'agent_unavailable_duration' => 'integer',
        'agent_busy_duration' => 'integer',
        'agent_system_busy_duration' => 'integer',
        'agent_login_duration' => 'integer',
        
        // Time stamps
        'agent_first_login_time' => 'datetime',
        'agent_last_logout_time' => 'datetime',
        
        // Calculated metrics
        'avg_speed_of_answer' => 'decimal:2',
        'avg_handle_time' => 'decimal:2',
        'occupancy_rate' => 'decimal:2',
        
        // Sync metadata
        'synced_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'agent_id',
        'interval_start_time',
        'midnight_start_date',
        'agent_reporting',
        'employee_id',
        'day_of_week_name',
        'interval_number',
        'agent_acd_count',
        'agent_acd_duration',
        'agent_acd_time_to_answer',
        'agent_acd_internal_count',
        'agent_acd_internal_duration',
        'agent_acd_internal_time_to_answer',
        'agent_acd_external_count',
        'agent_acd_external_duration',
        'agent_acd_external_time_to_answer',
        'agent_non_acd_count',
        'agent_non_acd_duration',
        'agent_non_acd_time_to_answer',
        'agent_non_acd_internal_count',
        'agent_non_acd_internal_duration',
        'agent_non_acd_internal_time_to_answer',
        'agent_non_acd_external_count',
        'agent_non_acd_external_duration',
        'agent_non_acd_external_time_to_answer',
        'agent_outbound_count',
        'agent_outbound_duration',
        'agent_outbound_internal_count',
        'agent_outbound_internal_duration',
        'agent_outbound_external_count',
        'agent_outbound_external_duration',
        'agent_transfer_count',
        'agent_conference_count',
        'agent_abandoned_count',
        'agent_acw_duration',
        'agent_available_duration',
        'agent_unavailable_duration',
        'agent_busy_duration',
        'agent_system_busy_duration',
        'agent_login_duration',
        'agent_first_login_time',
        'agent_last_logout_time',
        'avg_speed_of_answer',
        'avg_handle_time',
        'occupancy_rate',
        'synced_at',
        'updated_at',
    ];

    /**
     * Get the agent from CRM that this performance record belongs to.
     */
    public function agent()
    {
        return $this->belongsTo(Agent::class, 'employee_id', 'employee_id');
    }

    /**
     * Scope to get records for a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('midnight_start_date', $date);
    }

    /**
     * Scope to get records for a date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('midnight_start_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get records for a specific agent.
     */
    public function scopeForAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Scope to get records for a specific employee ID.
     */
    public function scopeForEmployeeId($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to get records for a specific interval.
     */
    public function scopeForInterval($query, $intervalStart)
    {
        return $query->where('interval_start_time', $intervalStart);
    }

    /**
     * Scope to get today's records.
     */
    public function scopeToday($query)
    {
        return $query->where('midnight_start_date', now()->toDateString());
    }

    /**
     * Scope to get current week's records.
     */
    public function scopeCurrentWeek($query)
    {
        return $query->whereBetween('midnight_start_date', [
            now()->startOfWeek()->toDateString(),
            now()->endOfWeek()->toDateString()
        ]);
    }

    /**
     * Scope to get current month's records.
     */
    public function scopeCurrentMonth($query)
    {
        return $query->whereBetween('midnight_start_date', [
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString()
        ]);
    }

    /**
     * Get total call count (ACD + Non-ACD + Outbound).
     */
    public function getTotalCallsAttribute()
    {
        return $this->agent_acd_count + 
               $this->agent_non_acd_count + 
               $this->agent_outbound_count;
    }

    /**
     * Get total internal calls.
     */
    public function getTotalInternalCallsAttribute()
    {
        return $this->agent_acd_internal_count + 
               $this->agent_non_acd_internal_count + 
               $this->agent_outbound_internal_count;
    }

    /**
     * Get total external calls.
     */
    public function getTotalExternalCallsAttribute()
    {
        return $this->agent_acd_external_count + 
               $this->agent_non_acd_external_count + 
               $this->agent_outbound_external_count;
    }

    /**
     * Get total talk time (ACD + Non-ACD + Outbound).
     */
    public function getTotalTalkTimeAttribute()
    {
        return $this->agent_acd_duration + 
               $this->agent_non_acd_duration + 
               $this->agent_outbound_duration;
    }

    /**
     * Get total productive time (talk + ACW).
     */
    public function getTotalProductiveTimeAttribute()
    {
        return $this->total_talk_time + $this->agent_acw_duration;
    }

    /**
     * Calculate occupancy percentage.
     */
    public function getOccupancyPercentageAttribute()
    {
        if ($this->agent_login_duration == 0) {
            return 0;
        }
        
        return round(($this->total_productive_time / $this->agent_login_duration) * 100, 2);
    }

    /**
     * Calculate average handle time (seconds).
     */
    public function getAverageHandleTimeAttribute()
    {
        if ($this->agent_acd_count == 0) {
            return 0;
        }
        
        return round(($this->agent_acd_duration + $this->agent_acw_duration) / $this->agent_acd_count, 2);
    }

    /**
     * Get transfer rate percentage.
     */
    public function getTransferRateAttribute()
    {
        if ($this->agent_acd_count == 0) {
            return 0;
        }
        
        return round(($this->agent_transfer_count / $this->agent_acd_count) * 100, 2);
    }

    /**
     * Check if agent was logged in during this interval.
     */
    public function isLoggedIn()
    {
        return $this->agent_login_duration > 0;
    }

    /**
     * Static method to get daily aggregate for an agent.
     */
    public static function getDailyAggregate($employeeId, $date)
    {
        return static::forEmployeeId($employeeId)
            ->forDate($date)
            ->selectRaw('
                employee_id,
                midnight_start_date,
                SUM(agent_acd_count) as total_acd_calls,
                SUM(agent_non_acd_count) as total_non_acd_calls,
                SUM(agent_outbound_count) as total_outbound_calls,
                SUM(agent_acd_duration + agent_non_acd_duration + agent_outbound_duration) as total_talk_time,
                SUM(agent_acw_duration) as total_acw_time,
                SUM(agent_login_duration) as total_login_time
            ')
            ->groupBy('employee_id', 'midnight_start_date')
            ->first();
    }
}
