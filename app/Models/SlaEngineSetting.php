<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlaEngineSetting extends Model
{
    protected $fillable = [
        'business_hours_enabled',
        'business_hours_start',
        'business_hours_end',
        'timezone',
        'working_days',
        'escalation_enabled',
        'default_escalation_threshold',
        'notify_agent',
        'notify_supervisor',
        'notify_manager',
        'pause_on_hold',
        'pause_on_pending',
        'pause_on_awaiting_customer',
        'auto_escalate_on_breach',
        'escalation_delay_minutes',
        'updated_by',
        'notes',
    ];

    protected $casts = [
        'business_hours_enabled' => 'boolean',
        'working_days' => 'array',
        'escalation_enabled' => 'boolean',
        'default_escalation_threshold' => 'integer',
        'notify_agent' => 'boolean',
        'notify_supervisor' => 'boolean',
        'notify_manager' => 'boolean',
        'pause_on_hold' => 'boolean',
        'pause_on_pending' => 'boolean',
        'pause_on_awaiting_customer' => 'boolean',
        'auto_escalate_on_breach' => 'boolean',
        'escalation_delay_minutes' => 'integer',
    ];

    /**
     * Get the singleton settings record.
     */
    public static function getSettings()
    {
        $settings = static::first();
        
        if (!$settings) {
            // Create default settings if none exist
            $settings = static::create([
                'business_hours_enabled' => true,
                'business_hours_start' => '09:00:00',
                'business_hours_end' => '17:00:00',
                'timezone' => 'Asia/Kuala_Lumpur',
                'working_days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                'escalation_enabled' => true,
                'default_escalation_threshold' => 80,
                'notify_agent' => true,
                'notify_supervisor' => true,
                'notify_manager' => false,
                'pause_on_hold' => true,
                'pause_on_pending' => true,
                'pause_on_awaiting_customer' => true,
                'auto_escalate_on_breach' => true,
                'escalation_delay_minutes' => 0,
            ]);
        }
        
        return $settings;
    }

    /**
     * Check if a given datetime falls within business hours
     */
    public function isWithinBusinessHours(?\DateTime $dateTime = null): bool
    {
        if (!$this->business_hours_enabled) {
            return true; // 24/7 operation
        }

        $dateTime = $dateTime ?? now($this->timezone);
        
        // Check if current day is a working day
        $dayName = $dateTime->format('l');
        if (!in_array($dayName, $this->working_days)) {
            return false;
        }

        // Check if current time is within business hours
        $currentTime = $dateTime->format('H:i:s');
        return $currentTime >= $this->business_hours_start && $currentTime <= $this->business_hours_end;
    }

    /**
     * Get business hours duration in minutes.
     */
    public function getBusinessHoursDuration(): int
    {
        $start = \Carbon\Carbon::parse($this->business_hours_start);
        $end = \Carbon\Carbon::parse($this->business_hours_end);
        
        return $end->diffInMinutes($start);
    }

    /**
     * Get working days count per week.
     */
    public function getWorkingDaysCount(): int
    {
        return count($this->working_days);
    }
}
