<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Ticket extends Model
{
    use SoftDeletes;

    /**
     * Statuses that stop the SLA clock permanently — the ticket is out of the agent's hands.
     */
    public const SLA_STOPPED_STATUSES = ['Resolved', 'Closed', 'Cancelled'];

    /**
     * Resolved SLA policy for this ticket, memoised per instance.
     */
    protected $resolvedSlaConfiguration = null;
    protected bool $slaConfigurationLoaded = false;

    protected static function boot(): void
    {
        parent::boot();

        // Guarantee every email ticket has a case_id before it hits the DB.
        // Runs before INSERT — cannot be overwritten by subsequent saves.
        static::creating(function (self $ticket) {
            if ($ticket->channel === 'email' && empty($ticket->case_id)) {
                $ticket->case_id = (string) Str::uuid();
            }
        });

        // Pause/resume the SLA clock on every status change, whichever endpoint made it.
        // Doing this on the model rather than in the controllers means the status route,
        // the generic update route, resolve/close/reopen and background jobs all agree.
        static::saving(function (self $ticket) {
            if ($ticket->exists && $ticket->isDirty('status')) {
                $ticket->applySlaStatusTransition($ticket->getOriginal('status'), $ticket->status);
            }
        });
    }

    protected $fillable = [
        'ticket_number',
        'conversation_id',
        'call_id',
        'case_id',
        'mitel_interaction_id',
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
        'sla_paused_at',
        'sla_paused_minutes',
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
        'sla_duration_minutes' => 'integer',
        'sla_remaining_minutes' => 'integer',
        'sla_paused_minutes' => 'integer',
        'reopened_count' => 'integer',
        'customer_rating' => 'integer',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'sla_paused_at' => 'datetime',
        'first_response_at' => 'datetime',
        'last_updated_at' => 'datetime',
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
     * The SLA policy that applies to this ticket, or null when none is configured.
     */
    public function slaConfiguration(): ?SlaConfiguration
    {
        if (!$this->slaConfigurationLoaded) {
            $this->resolvedSlaConfiguration = SlaConfiguration::getSlaForTicket($this->department, $this->priority);
            $this->slaConfigurationLoaded = true;
        }

        return $this->resolvedSlaConfiguration;
    }

    /**
     * Drop the memoised policy — call after changing department or priority.
     */
    public function forgetSlaConfiguration(): void
    {
        $this->resolvedSlaConfiguration = null;
        $this->slaConfigurationLoaded = false;
    }

    /**
     * Calculate and set SLA due date based on configured SLA policies.
     * If no SLA configuration exists for this department + priority, SLA is not applied and
     * any previously calculated SLA state is cleared — a ticket must never show a stale or
     * invented deadline just because it once matched a different policy.
     */
    public function calculateSla()
    {
        $this->forgetSlaConfiguration();
        $slaConfig = $this->slaConfiguration();

        if (!$slaConfig) {
            \Log::debug("No SLA config found for department={$this->department}, priority={$this->priority}. SLA not applied.");
            $this->clearSla();
            return;
        }

        $this->sla_duration_minutes = (int) $slaConfig->duration_minutes;

        $start = $this->created_at ? Carbon::parse($this->created_at) : Carbon::now();
        $due = $this->addServiceMinutes($start, $this->sla_duration_minutes, (bool) $slaConfig->business_hours_only);

        // Time already spent paused stays credited to the ticket, so re-targeting the SLA
        // (SE-06) never silently swallows a hold period the ticket already earned back.
        $pausedMinutes = (int) ($this->sla_paused_minutes ?? 0);
        if ($pausedMinutes > 0) {
            $due = $due->copy()->addMinutes($pausedMinutes);
        }

        $this->sla_due_at = $due;

        // Re-derive the breach flag against the new deadline rather than carrying the old one:
        // a longer SLA can legitimately un-breach a ticket.
        $this->sla_breached = false;
        $this->updateSlaRemaining();
    }

    /**
     * Remove all SLA state from the ticket (no policy configured).
     */
    public function clearSla(): void
    {
        $this->sla_duration_minutes = null;
        $this->sla_due_at = null;
        $this->sla_remaining_minutes = null;
        $this->sla_breached = false;
        $this->sla_paused_at = null;
        $this->sla_paused_minutes = 0;
    }

    /**
     * Update SLA remaining time and the breach flag.
     *
     * The countdown is measured against a reference point rather than always "now": frozen at
     * the pause instant while the clock is paused, and frozen at resolution/closure once the
     * ticket stops the clock, so a finished ticket keeps the number it finished on.
     */
    public function updateSlaRemaining()
    {
        if (!$this->sla_due_at) {
            $this->sla_remaining_minutes = null;
            return;
        }

        $due = Carbon::parse($this->sla_due_at);

        if ($this->sla_paused_at) {
            $reference = Carbon::parse($this->sla_paused_at);
        } elseif (in_array($this->status, self::SLA_STOPPED_STATUSES, true)) {
            $reference = Carbon::parse($this->resolved_at ?? $this->closed_at ?? Carbon::now());
        } else {
            $reference = Carbon::now();
        }

        // Compare at whole-second precision: stored timestamps have no sub-second part but
        // now() does, and mixing the two makes the countdown jump a minute when the reference
        // switches between "now" and a stored pause/closure timestamp.
        //
        // floor(), not a cast: casting truncates toward zero, which reports "0 minutes left"
        // for a ticket that is actually 30 seconds overdue.
        $remaining = (int) floor($reference->startOfSecond()->diffInMinutes($due->startOfSecond(), false));
        $this->sla_remaining_minutes = $remaining;

        if ($remaining < 0) {
            $this->sla_breached = true;
        }
    }

    /**
     * Statuses that pause the SLA clock for this ticket.
     *
     * The ticket's own policy wins when one is configured; the global SLA Engine settings are
     * the fallback so tickets without a policy still behave consistently.
     */
    public function slaPausingStatuses(): array
    {
        $config = $this->slaConfiguration();
        $settings = SlaEngineSetting::getSettings();

        $pauseOnHold = $config ? (bool) $config->pause_on_hold : (bool) $settings->pause_on_hold;
        $pauseOnPending = $config ? (bool) $config->pause_on_pending : (bool) $settings->pause_on_pending;

        $statuses = [];

        if ($pauseOnHold) {
            $statuses[] = 'On Hold';
        }

        if ($pauseOnPending) {
            $statuses[] = 'Pending';
        }

        return $statuses;
    }

    /**
     * Whether the SLA clock is currently frozen.
     */
    public function getSlaPausedAttribute(): bool
    {
        return $this->sla_paused_at !== null;
    }

    /**
     * Freeze the SLA clock. No-op when there is no SLA or it is already paused.
     */
    public function pauseSla(?Carbon $at = null): void
    {
        if (!$this->sla_due_at || $this->sla_paused_at) {
            return;
        }

        $this->sla_paused_at = $at ?? Carbon::now();
        $this->updateSlaRemaining();
    }

    /**
     * Resume the SLA clock, pushing the deadline out by the time spent paused so the ticket
     * picks up exactly where it left off instead of losing (or resetting) the remaining time.
     */
    public function resumeSla(?Carbon $at = null): void
    {
        if (!$this->sla_paused_at) {
            return;
        }

        $resumedAt = $at ?? Carbon::now();
        $pausedFor = (int) round(Carbon::parse($this->sla_paused_at)->diffInMinutes($resumedAt, false));
        $pausedFor = max(0, $pausedFor);

        $this->sla_paused_minutes = (int) ($this->sla_paused_minutes ?? 0) + $pausedFor;

        if ($this->sla_due_at && $pausedFor > 0) {
            $this->sla_due_at = Carbon::parse($this->sla_due_at)->addMinutes($pausedFor);
        }

        $this->sla_paused_at = null;
        $this->updateSlaRemaining();
    }

    /**
     * Apply pause/resume for a status transition. Called automatically on save.
     */
    public function applySlaStatusTransition($oldStatus, $newStatus): void
    {
        if (!$this->sla_due_at) {
            return;
        }

        $pausingStatuses = $this->slaPausingStatuses();
        $wasPaused = in_array($oldStatus, $pausingStatuses, true);
        $isPaused = in_array($newStatus, $pausingStatuses, true);

        if ($isPaused && !$wasPaused) {
            $this->pauseSla();
            return;
        }

        // Covers both an explicit resume and leaving a paused status via resolve/close, so a
        // stale pause marker can never freeze a ticket's clock forever.
        if (!$isPaused && $this->sla_paused_at) {
            $this->resumeSla();
            return;
        }

        $this->updateSlaRemaining();
    }

    /**
     * Add SLA minutes to a starting point, walking business hours when the policy asks for it.
     */
    protected function addServiceMinutes(Carbon $start, int $minutes, bool $businessHoursOnly): Carbon
    {
        if ($minutes <= 0) {
            return $start->copy();
        }

        $settings = SlaEngineSetting::getSettings();

        if (!$businessHoursOnly || !$settings->business_hours_enabled) {
            return $start->copy()->addMinutes($minutes);
        }

        $timezone = $settings->timezone ?: config('app.timezone');
        $workingDays = $settings->working_days ?: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $openTime = (string) $settings->business_hours_start;
        $closeTime = (string) $settings->business_hours_end;

        $cursor = $start->copy()->setTimezone($timezone);
        $remaining = $minutes;

        // Bounded so a misconfigured window (e.g. no working days) fails loudly-ish instead of
        // spinning forever; ~10 years of calendar days is far past any real SLA.
        $guard = 0;

        while ($remaining > 0 && $guard++ < 4000) {
            $dayOpen = $cursor->copy()->setTimeFromTimeString($openTime);
            $dayClose = $cursor->copy()->setTimeFromTimeString($closeTime);

            if (!in_array($cursor->format('l'), $workingDays, true) || $cursor->greaterThanOrEqualTo($dayClose)) {
                $cursor = $cursor->copy()->addDay()->setTimeFromTimeString($openTime);
                continue;
            }

            if ($cursor->lessThan($dayOpen)) {
                $cursor = $dayOpen;
            }

            $available = (int) floor($cursor->diffInMinutes($dayClose, false));

            if ($available <= 0) {
                $cursor = $cursor->copy()->addDay()->setTimeFromTimeString($openTime);
                continue;
            }

            if ($remaining <= $available) {
                $cursor = $cursor->copy()->addMinutes($remaining);
                $remaining = 0;
                break;
            }

            $remaining -= $available;
            $cursor = $cursor->copy()->addDay()->setTimeFromTimeString($openTime);
        }

        if ($remaining > 0) {
            \Log::warning('SLA business-hours calculation did not converge; falling back to elapsed time.', [
                'ticket_id' => $this->id,
                'minutes' => $minutes,
            ]);

            return $start->copy()->addMinutes($minutes);
        }

        return $cursor->setTimezone($start->getTimezone());
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

        $this->customer?->updateMetrics();
    }

    /**
     * Close the ticket.
     */
    public function close()
    {
        $this->status = 'Closed';
        $this->closed_at = now();
        $this->save();

        $this->customer?->updateMetrics();
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

        $this->customer?->updateMetrics();
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

        if ($this->sla_remaining_minutes < 0) {
            return "Overdue by {$formatted}";
        }

        // Make a frozen clock visible rather than showing a countdown that never moves.
        return $this->sla_paused_at ? "{$formatted} (paused)" : $formatted;
    }

    /**
     * Check if ticket is overdue.
     */
    public function getIsOverdueAttribute()
    {
        return (bool) $this->sla_breached
            || ($this->sla_remaining_minutes !== null && $this->sla_remaining_minutes < 0);
    }
}
