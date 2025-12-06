<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AgentCallStatistic extends Model
{
    protected $fillable = [
        'agent_id',
        'statistic_date',
        // Call Volume
        'total_calls',
        'answered_calls',
        'missed_calls',
        'transferred_calls',
        'outbound_calls',
        // Call Duration
        'total_talk_time',
        'avg_call_duration',
        'longest_call_duration',
        'shortest_call_duration',
        // After Call Work
        'total_acw_time',
        'avg_acw_time',
        // Availability
        'available_time',
        'busy_time',
        'away_time',
        'dnd_time',
        'logged_in_time',
        // Performance Metrics
        'answer_rate',
        'occupancy_rate',
        'utilization_rate',
    ];

    protected function casts(): array
    {
        return [
            'agent_id' => 'integer',
            'statistic_date' => 'date',
            'total_calls' => 'integer',
            'answered_calls' => 'integer',
            'missed_calls' => 'integer',
            'transferred_calls' => 'integer',
            'outbound_calls' => 'integer',
            'total_talk_time' => 'integer',
            'avg_call_duration' => 'integer',
            'longest_call_duration' => 'integer',
            'shortest_call_duration' => 'integer',
            'total_acw_time' => 'integer',
            'avg_acw_time' => 'integer',
            'available_time' => 'integer',
            'busy_time' => 'integer',
            'away_time' => 'integer',
            'dnd_time' => 'integer',
            'logged_in_time' => 'integer',
            'answer_rate' => 'float',
            'occupancy_rate' => 'float',
            'utilization_rate' => 'float',
        ];
    }

    /**
     * Get the agent that owns this statistic.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Scope to get statistics for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('statistic_date', Carbon::today());
    }

    /**
     * Scope to get statistics for a specific date.
     */
    public function scopeForDate($query, Carbon|string $date)
    {
        return $query->whereDate('statistic_date', $date);
    }

    /**
     * Scope to get statistics within a date range.
     */
    public function scopeDateRange($query, Carbon|string $startDate, Carbon|string $endDate)
    {
        return $query->whereBetween('statistic_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get statistics for a specific agent.
     */
    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Calculate and update performance metrics based on call data.
     */
    public function calculateMetrics(): void
    {
        // Calculate answer rate
        if ($this->total_calls > 0) {
            $this->answer_rate = round(($this->answered_calls / $this->total_calls) * 100, 2);
        }

        // Calculate average call duration
        if ($this->answered_calls > 0) {
            $this->avg_call_duration = round($this->total_talk_time / $this->answered_calls);
        }

        // Calculate average ACW time
        if ($this->answered_calls > 0 && $this->total_acw_time > 0) {
            $this->avg_acw_time = round($this->total_acw_time / $this->answered_calls);
        }

        // Calculate occupancy rate (time on calls + ACW / logged in time)
        if ($this->logged_in_time > 0) {
            $occupiedTime = $this->total_talk_time + $this->total_acw_time;
            $this->occupancy_rate = round(($occupiedTime / $this->logged_in_time) * 100, 2);
        }

        // Calculate utilization rate (time on calls / logged in time)
        if ($this->logged_in_time > 0) {
            $this->utilization_rate = round(($this->total_talk_time / $this->logged_in_time) * 100, 2);
        }

        $this->save();
    }

    /**
     * Get formatted statistics summary.
     */
    public function getSummaryAttribute(): array
    {
        return [
            'date' => $this->statistic_date->format('Y-m-d'),
            'call_volume' => [
                'total' => $this->total_calls,
                'answered' => $this->answered_calls,
                'missed' => $this->missed_calls,
                'transferred' => $this->transferred_calls,
                'outbound' => $this->outbound_calls,
            ],
            'duration' => [
                'total_talk_time' => $this->total_talk_time,
                'avg_call_duration' => $this->avg_call_duration,
                'longest_call' => $this->longest_call_duration,
                'shortest_call' => $this->shortest_call_duration,
            ],
            'after_call_work' => [
                'total_acw_time' => $this->total_acw_time,
                'avg_acw_time' => $this->avg_acw_time,
            ],
            'availability' => [
                'available_time' => $this->available_time,
                'busy_time' => $this->busy_time,
                'away_time' => $this->away_time,
                'dnd_time' => $this->dnd_time,
                'logged_in_time' => $this->logged_in_time,
            ],
            'performance' => [
                'answer_rate' => $this->answer_rate . '%',
                'occupancy_rate' => $this->occupancy_rate . '%',
                'utilization_rate' => $this->utilization_rate . '%',
            ],
        ];
    }

    /**
     * Create or update today's statistics for an agent.
     */
    public static function updateOrCreateToday(int $agentId, array $data): self
    {
        $statistic = static::updateOrCreate(
            [
                'agent_id' => $agentId,
                'statistic_date' => Carbon::today(),
            ],
            $data
        );

        $statistic->calculateMetrics();

        return $statistic;
    }
}
