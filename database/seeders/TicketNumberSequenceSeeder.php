<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TicketNumberSequence;

class TicketNumberSequenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sequences = [
            [
                'name' => 'default',
                'prefix' => 'TKT',
                'pattern' => '{PREFIX}{YYYY}{MM}{DD}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 4,
                'reset_daily' => true,
                'reset_monthly' => false,
                'reset_yearly' => false,
                'is_active' => true,
                'description' => 'Default ticket numbering sequence with daily reset',
            ],
            [
                'name' => 'urgent',
                'prefix' => 'URG',
                'pattern' => '{PREFIX}-{YYYY}{MM}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 5,
                'reset_daily' => false,
                'reset_monthly' => true,
                'reset_yearly' => false,
                'is_active' => true,
                'description' => 'Urgent ticket numbering sequence with monthly reset',
            ],
            [
                'name' => 'vip',
                'prefix' => 'VIP',
                'pattern' => '{PREFIX}{YY}{MM}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 3,
                'reset_daily' => false,
                'reset_monthly' => true,
                'reset_yearly' => false,
                'is_active' => true,
                'description' => 'VIP customer ticket numbering sequence with monthly reset',
            ],
            [
                'name' => 'continuous',
                'prefix' => 'TK',
                'pattern' => '{PREFIX}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 6,
                'reset_daily' => false,
                'reset_monthly' => false,
                'reset_yearly' => false,
                'is_active' => false,
                'description' => 'Continuous numbering without date and no reset',
            ],
        ];

        foreach ($sequences as $sequenceData) {
            $sequence = TicketNumberSequence::updateOrCreate(
                ['name' => $sequenceData['name']],
                $sequenceData
            );
            
            // Generate and save sample output
            $sequence->updateSampleOutput();
        }

        $this->command->info('Ticket number sequences seeded successfully!');
        $this->command->info('');
        $this->command->info('Available Sequences:');
        $this->command->info('══════════════════════════════════════════════════════');
        
        foreach (TicketNumberSequence::all() as $seq) {
            $status = $seq->is_active ? '✓ Active' : '✗ Inactive';
            $this->command->info("  {$seq->name}: {$seq->sample_output} ({$status})");
        }
        
        $this->command->info('══════════════════════════════════════════════════════');
    }
}
