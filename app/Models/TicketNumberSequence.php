<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TicketNumberSequence extends Model
{
    protected $fillable = [
        'name',
        'prefix',
        'pattern',
        'current_number',
        'padding',
        'reset_daily',
        'reset_monthly',
        'reset_yearly',
        'last_reset_date',
        'sample_output',
        'is_active',
        'description',
    ];

    protected $casts = [
        'reset_daily' => 'boolean',
        'reset_monthly' => 'boolean',
        'reset_yearly' => 'boolean',
        'is_active' => 'boolean',
        'last_reset_date' => 'date',
    ];

    /**
     * Generate next ticket number (thread-safe with database row locking)
     * 
     * @param string $sequenceName
     * @return string
     */
    public static function generateNextNumber(string $sequenceName = 'default'): string
    {
        return DB::transaction(function () use ($sequenceName) {
            // Lock the row for update to prevent race conditions
            $sequence = self::where('name', $sequenceName)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                throw new \Exception("Ticket number sequence '{$sequenceName}' not found or inactive");
            }

            // Check if we need to reset the sequence
            $now = Carbon::now();
            $needsReset = false;

            if ($sequence->last_reset_date) {
                $lastReset = Carbon::parse($sequence->last_reset_date);
                
                if ($sequence->reset_daily && !$lastReset->isToday()) {
                    $needsReset = true;
                } elseif ($sequence->reset_monthly && !$lastReset->isSameMonth($now)) {
                    $needsReset = true;
                } elseif ($sequence->reset_yearly && !$lastReset->isSameYear($now)) {
                    $needsReset = true;
                }
            } elseif ($sequence->reset_daily || $sequence->reset_monthly || $sequence->reset_yearly) {
                // First time, set last reset date
                $needsReset = false;
                $sequence->last_reset_date = $now->toDateString();
            }

            if ($needsReset) {
                $sequence->current_number = 0;
                $sequence->last_reset_date = $now->toDateString();
            }

            // Increment the sequence number
            $sequence->current_number += 1;
            $nextNumber = $sequence->current_number;

            // Generate the ticket number based on pattern
            $ticketNumber = self::formatTicketNumber($sequence, $nextNumber, $now);

            // Save the updated sequence
            $sequence->save();

            return $ticketNumber;
        });
    }

    /**
     * Format ticket number based on pattern
     * 
     * @param TicketNumberSequence $sequence
     * @param int $number
     * @param Carbon $date
     * @return string
     */
    private static function formatTicketNumber(self $sequence, int $number, Carbon $date): string
    {
        $pattern = $sequence->pattern;
        
        // Pad the sequence number
        $paddedNumber = str_pad($number, $sequence->padding, '0', STR_PAD_LEFT);

        // Replace placeholders
        $replacements = [
            '{PREFIX}' => $sequence->prefix,
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
            '{DD}' => $date->format('d'),
            '{SEQUENCE}' => $paddedNumber,
            '{M}' => $date->format('n'), // Month without leading zero
            '{D}' => $date->format('j'), // Day without leading zero
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $pattern);
    }

    /**
     * Generate sample output for preview
     * 
     * @return string
     */
    public function generateSample(): string
    {
        $sampleNumber = $this->current_number + 1;
        return self::formatTicketNumber($this, $sampleNumber, Carbon::now());
    }

    /**
     * Update sample output
     * 
     * @return void
     */
    public function updateSampleOutput(): void
    {
        $this->sample_output = $this->generateSample();
        $this->save();
    }
}
