<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SlaEngineSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SlaEngineController extends Controller
{
    /**
     * Get SLA engine settings (singleton).
     */
    public function getSettings()
    {
        $settings = SlaEngineSetting::getSettings();

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update SLA engine settings.
     */
    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_hours_enabled' => 'boolean',
            'business_hours_start' => 'date_format:H:i',
            'business_hours_end' => 'date_format:H:i',
            'timezone' => 'string',
            'working_days' => 'array',
            'working_days.*' => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'escalation_enabled' => 'boolean',
            'default_escalation_threshold' => 'integer|min:0|max:100',
            'notify_agent' => 'boolean',
            'notify_supervisor' => 'boolean',
            'notify_manager' => 'boolean',
            'pause_on_hold' => 'boolean',
            'pause_on_pending' => 'boolean',
            'pause_on_awaiting_customer' => 'boolean',
            'auto_escalate_on_breach' => 'boolean',
            'escalation_delay_minutes' => 'integer|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $settings = SlaEngineSetting::getSettings();
        
        // Update with request data
        $updateData = $request->only([
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
            'notes',
        ]);

        // Convert time format if provided
        if (isset($updateData['business_hours_start'])) {
            $updateData['business_hours_start'] = $updateData['business_hours_start'] . ':00';
        }
        if (isset($updateData['business_hours_end'])) {
            $updateData['business_hours_end'] = $updateData['business_hours_end'] . ':00';
        }

        // Track who updated
        if ($request->user()) {
            $updateData['updated_by'] = $request->user()->name;
        }

        $settings->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'SLA engine settings updated successfully',
            'data' => $settings->fresh()
        ]);
    }

    /**
     * Reset to default settings.
     */
    public function resetToDefaults(Request $request)
    {
        $settings = SlaEngineSetting::getSettings();
        
        $settings->update([
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
            'updated_by' => $request->user() ? $request->user()->name : null,
            'notes' => 'Reset to default settings',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Settings reset to defaults successfully',
            'data' => $settings->fresh()
        ]);
    }

    /**
     * Test if given datetime is within business hours.
     */
    public function testBusinessHours(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'datetime' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $settings = SlaEngineSetting::getSettings();
        $datetime = $request->has('datetime') 
            ? new \DateTime($request->datetime)
            : now($settings->timezone);

        $isWithin = $settings->isWithinBusinessHours($datetime);

        return response()->json([
            'success' => true,
            'data' => [
                'datetime' => $datetime->format('Y-m-d H:i:s'),
                'is_within_business_hours' => $isWithin,
                'day_of_week' => $datetime->format('l'),
                'time' => $datetime->format('H:i:s'),
                'settings' => [
                    'business_hours_start' => $settings->business_hours_start,
                    'business_hours_end' => $settings->business_hours_end,
                    'working_days' => $settings->working_days,
                ]
            ]
        ]);
    }
}
