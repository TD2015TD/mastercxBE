<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AgentPerformanceMetric extends Model
{
    protected $fillable = [
        'agent_id',
        'metric_date',
        // Ticket Performance
        'tickets_resolved',
        'tickets_assigned',
        'tickets_pending',
        'daily_target',
        'target_achievement_rate',
        // Response & Resolution Metrics
        'avg_first_response_time',      // in seconds
        'avg_resolution_time',           // in seconds
        'avg_response_time',             // in seconds
        // Quality Metrics
        'sla_compliance_rate',           // percentage 0-100
        'customer_satisfaction_score',   // 0-5 scale
        'customer_satisfaction_count',   // number of ratings
        'reopened_tickets',
        // Productivity Metrics
        'total_interactions',            // emails, chats, calls combined
        'avg_handle_time',               // in seconds
        'first_contact_resolution_rate', // percentage 0-100
    ];

    protected function casts(): array
    {
        return [
            'agent_id' => 'integer',
            'metric_date' => 'date',
            'tickets_resolved' => 'integer',
            'tickets_assigned' => 'integer',
            'tickets_pending' => 'integer',
            'daily_target' => 'integer',
            'target_achievement_rate' => 'float',
            'avg_first_response_time' => 'integer',
            'avg_resolution_time' => 'integer',
            'avg_response_time' => 'integer',
            'sla_compliance_rate' => 'float',
            'customer_satisfaction_score' => 'float',
            'customer_satisfaction_count' => 'integer',
            'reopened_tickets' => 'integer',
            'total_interactions' => 'integer',
            'avg_handle_time' => 'integer',
            'first_contact_resolution_rate' => 'float',
        ];
    }

    /**
     * Get the agent that owns this performance metric.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Scope to get metrics for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('metric_date', Carbon::today());
    }

    /**
     * Scope to get metrics for a specific date.
     */
    public function scopeForDate($query, Carbon|string $date)
    {
        return $query->whereDate('metric_date', $date);
    }

    /**
     * Scope to get metrics within a date range.
     */
    public function scopeDateRange($query, Carbon|string $startDate, Carbon|string $endDate)
    {
        return $query->whereBetween('metric_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get metrics for a specific agent.
     */
    public function scopeForAgent($query, int $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Calculate derived metrics.
     */
    public function calculateMetrics(): void
    {
        // Calculate target achievement rate
        if ($this->daily_target > 0) {
            $this->target_achievement_rate = round(($this->tickets_resolved / $this->daily_target) * 100, 2);
        }

        // Calculate average customer satisfaction
        if ($this->customer_satisfaction_count > 0 && $this->customer_satisfaction_score > 0) {
            // If score is total, divide by count to get average
            // Adjust this logic based on how satisfaction is stored
        }

        $this->save();
    }

    /**
     * Get formatted performance summary.
     */
    public function getSummaryAttribute(): array
    {
        return [
            'date' => $this->metric_date->format('Y-m-d'),
            'tickets' => [
                'resolved' => $this->tickets_resolved,
                'assigned' => $this->tickets_assigned,
                'pending' => $this->tickets_pending,
                'reopened' => $this->reopened_tickets,
                'daily_target' => $this->daily_target,
                'achievement_rate' => $this->target_achievement_rate . '%',
            ],
            'response_times' => [
                'first_response' => $this->formatTime($this->avg_first_response_time),
                'resolution' => $this->formatTime($this->avg_resolution_time),
                'average_response' => $this->formatTime($this->avg_response_time),
                'handle_time' => $this->formatTime($this->avg_handle_time),
            ],
            'quality' => [
                'sla_compliance' => $this->sla_compliance_rate . '%',
                'customer_satisfaction' => round($this->customer_satisfaction_score, 2),
                'satisfaction_responses' => $this->customer_satisfaction_count,
                'first_contact_resolution' => $this->first_contact_resolution_rate . '%',
            ],
            'productivity' => [
                'total_interactions' => $this->total_interactions,
            ],
        ];
    }

    /**
     * Format time in seconds to human readable format.
     */
    private function formatTime(?int $seconds): string
    {
        if (!$seconds) {
            return '0s';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        if ($secs > 0 || empty($parts)) $parts[] = "{$secs}s";

        return implode(' ', $parts);
    }

    /**
     * Create or update today's metrics for an agent.
     */
    public static function updateOrCreateToday(int $agentId, array $data): self
    {
        $metric = static::updateOrCreate(
            [
                'agent_id' => $agentId,
                'metric_date' => Carbon::today(),
            ],
            $data
        );

        $metric->calculateMetrics();

        return $metric;
    }
}
