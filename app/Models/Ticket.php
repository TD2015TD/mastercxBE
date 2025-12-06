<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Ticket extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ticket_number',
        'customer_id',
        'assigned_to',
        'created_by',
        'priority',
        'department',
        'category',
        'sub_category',
        'subject',
        'description',
        'urgency',
        'impact',
        'status',
        'resolution_notes',
        'resolved_at',
        'closed_at',
        'sla_duration_minutes',
        'sla_due_at',
        'sla_breached',
        'sla_remaining_minutes',
        'first_response_at',
        'first_response_time_minutes',
        'resolution_time_minutes',
        'channel',
        'source',
        'customer_rating',
        'customer_feedback',
        'tags',
        'custom_fields',
        'reopened_count',
        'last_updated_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'custom_fields' => 'array',
        'sla_breached' => 'boolean',
        'reopened_count' => 'integer',
        'customer_rating' => 'integer',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'first_response_at' => 'datetime',
        'last_updated_at' => 'datetime',
    ];

    /**
     * SLA durations in minutes based on priority.
     */
    const SLA_DURATIONS = [
        'P1' => 60,      // 1 hour
        'P2' => 240,     // 4 hours
        'P3' => 1440,    // 24 hours
        'P4' => 4320,    // 72 hours
    ];

    /**
     * Get the customer that owns the ticket.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the agent assigned to the ticket.
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_to');
    }

    /**
     * Get the agent who created the ticket.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'created_by');
    }

    /**
     * Get the ticket history records.
     */
    public function histories()
    {
        return $this->hasMany(TicketHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get customer interactions related to this ticket.
     */
    public function interactions()
    {
        return $this->hasMany(CustomerInteraction::class);
    }

    /**
     * Get escalations for this ticket.
     */
    public function escalations()
    {
        return $this->hasMany(Escalation::class)->orderBy('escalated_at', 'desc');
    }

    /**
     * Get attachments for this ticket.
     */
    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class)->orderBy('created_at', 'desc');
    }

    /**
     * Log a history entry for this ticket
     */
    public function logHistory(
        string $action,
        ?string $fieldName = null,
        $oldValue = null,
        $newValue = null,
        ?string $description = null,
        ?array $metadata = null
    ) {
        return TicketHistory::log(
            $this->id,
            $action,
            auth()->id(),
            $fieldName,
            $oldValue,
            $newValue,
            $description,
            $metadata
        );
    }

    /**
     * Scope a query to only include open tickets.
     */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['Open', 'In Progress', 'Pending']);
    }

    /**
     * Scope a query to only include resolved tickets.
     */
    public function scopeResolved($query)
    {
        return $query->whereIn('status', ['Resolved', 'Closed']);
    }

    /**
     * Scope a query to filter by priority.
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope a query to filter by department.
     */
    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    /**
     * Scope a query to find SLA breached tickets.
     */
    public function scopeSlaBreached($query)
    {
        return $query->where('sla_breached', true);
    }

    /**
     * Priority matrix: Auto-calculate priority based on Impact × Urgency.
     * 
     * Matrix:
     *              Impact
     *         Low  Med  High Critical
     * Urgency
     * Critical P2   P1   P1    P1
     * High     P3   P2   P2    P1
     * Medium   P4   P3   P2    P2
     * Low      P4   P4   P3    P3
     */
    public function calculatePriority()
    {
        $matrix = [
            'Critical' => [
                'Low' => 'P2',
                'Medium' => 'P1',
                'High' => 'P1',
                'Critical' => 'P1',
            ],
            'High' => [
                'Low' => 'P3',
                'Medium' => 'P2',
                'High' => 'P2',
                'Critical' => 'P1',
            ],
            'Medium' => [
                'Low' => 'P4',
                'Medium' => 'P3',
                'High' => 'P2',
                'Critical' => 'P2',
            ],
            'Low' => [
                'Low' => 'P4',
                'Medium' => 'P4',
                'High' => 'P3',
                'Critical' => 'P3',
            ],
        ];

        if (isset($this->urgency) && isset($this->impact)) {
            $this->priority = $matrix[$this->urgency][$this->impact] ?? 'P3';
        }
    }

    /**
     * Calculate and set SLA due date based on priority.
     * Uses SLA configuration if available, otherwise uses defaults.
     */
    public function calculateSla()
    {
        // Try to get SLA configuration from database
        $slaConfig = SlaConfiguration::getSlaForTicket($this->department, $this->priority);
        
        if ($slaConfig) {
            $this->sla_duration_minutes = $slaConfig->duration_minutes;
        } else {
            // Fallback to default durations
            $this->sla_duration_minutes = self::SLA_DURATIONS[$this->priority] ?? 1440;
        }
        
        $this->sla_due_at = Carbon::parse($this->created_at)->addMinutes($this->sla_duration_minutes);
        $this->updateSlaRemaining();
    }

    /**
     * Update SLA remaining time.
     */
    public function updateSlaRemaining()
    {
        if ($this->sla_due_at && !in_array($this->status, ['Resolved', 'Closed', 'Cancelled'])) {
            $remaining = Carbon::now()->diffInMinutes($this->sla_due_at, false);
            $this->sla_remaining_minutes = (int) $remaining;
            
            if ($remaining < 0 && !$this->sla_breached) {
                $this->sla_breached = true;
            }
        } else {
            $this->sla_remaining_minutes = null;
        }
    }

    /**
     * Record first response time.
     */
    public function recordFirstResponse()
    {
        if (!$this->first_response_at) {
            $this->first_response_at = now();
            $this->first_response_time_minutes = (int) Carbon::parse($this->created_at)
                ->diffInMinutes($this->first_response_at);
            $this->save();
        }
    }

    /**
     * Resolve the ticket.
     */
    public function resolve(?string $resolutionNotes = null)
    {
        $this->status = 'Resolved';
        $this->resolved_at = now();
        $this->resolution_notes = $resolutionNotes;
        $this->resolution_time_minutes = (int) Carbon::parse($this->created_at)
            ->diffInMinutes($this->resolved_at);
        $this->updateSlaRemaining();
        $this->save();

        // Update customer metrics
        $this->customer->updateMetrics();
    }

    /**
     * Close the ticket.
     */
    public function close()
    {
        $this->status = 'Closed';
        $this->closed_at = now();
        $this->save();
    }

    /**
     * Reopen the ticket.
     */
    public function reopen()
    {
        $this->status = 'Open';
        $this->reopened_count++;
        $this->resolved_at = null;
        $this->closed_at = null;
        $this->calculateSla();
        $this->save();
    }

    /**
     * Get formatted SLA remaining time.
     */
    public function getFormattedSlaRemainingAttribute()
    {
        if ($this->sla_remaining_minutes === null) {
            return 'N/A';
        }

        $minutes = abs($this->sla_remaining_minutes);
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        $formatted = $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
        return $this->sla_remaining_minutes < 0 ? "Overdue by {$formatted}" : $formatted;
    }

    /**
     * Check if ticket is overdue.
     */
    public function getIsOverdueAttribute()
    {
        return $this->sla_remaining_minutes !== null && $this->sla_remaining_minutes < 0;
    }
}
