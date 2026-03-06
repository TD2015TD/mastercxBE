<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TicketNumberSequence;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TicketNumberSequenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('ticket_number_sequences')->delete();

        $sequences = [
            // Default Sequence (Daily Reset)
            [
                'name' => 'default',
                'prefix' => 'TKT',
                'pattern' => '{PREFIX}{YYYY}{MM}{DD}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 4,
                'reset_daily' => true,
                'reset_monthly' => false,
                'reset_yearly' => false,
                'last_reset_date' => Carbon::today()->toDateString(),
                'is_active' => true,
                'description' => 'Default ticket numbering sequence for general tickets. Resets daily. Format: TKT20251205-0001',
            ],

            // Phone Channel Sequence (Daily Reset)
            [
                'name' => 'phone',
                'prefix' => 'PH',
                'pattern' => '{PREFIX}{YY}{MM}{DD}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 4,
                'reset_daily' => true,
                'reset_monthly' => false,
                'reset_yearly' => false,
                'last_reset_date' => Carbon::today()->toDateString(),
                'is_active' => true,
                'description' => 'Phone call tickets. Resets daily. Format: PH251205-0001',
            ],

            // Email Channel Sequence (Daily Reset)
            [
                'name' => 'email',
                'prefix' => 'EM',
                'pattern' => '{PREFIX}{YY}{MM}{DD}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 4,
                'reset_daily' => true,
                'reset_monthly' => false,
                'reset_yearly' => false,
                'last_reset_date' => Carbon::today()->toDateString(),
                'is_active' => true,
                'description' => 'Email tickets. Resets daily. Format: EM251205-0001',
            ],

            // Chat Channel Sequence (Daily Reset)
            [
                'name' => 'chat',
                'prefix' => 'CH',
                'pattern' => '{PREFIX}{YY}{MM}{DD}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 4,
                'reset_daily' => true,
                'reset_monthly' => false,
                'reset_yearly' => false,
                'last_reset_date' => Carbon::today()->toDateString(),
                'is_active' => true,
                'description' => 'Live chat tickets. Resets daily. Format: CH251205-0001',
            ],

            // Web Portal Sequence (Daily Reset)
            [
                'name' => 'web',
                'prefix' => 'WB',
                'pattern' => '{PREFIX}{YY}{MM}{DD}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 4,
                'reset_daily' => true,
                'reset_monthly' => false,
                'reset_yearly' => false,
                'last_reset_date' => Carbon::today()->toDateString(),
                'is_active' => true,
                'description' => 'Web portal tickets. Resets daily. Format: WB251205-0001',
            ],

            // Walk-in Sequence (Daily Reset)
            [
                'name' => 'walkIn',
                'prefix' => 'WI',
                'pattern' => '{PREFIX}{YY}{MM}{DD}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 4,
                'reset_daily' => true,
                'reset_monthly' => false,
                'reset_yearly' => false,
                'last_reset_date' => Carbon::today()->toDateString(),
                'is_active' => true,
                'description' => 'Walk-in counter tickets. Resets daily. Format: WI251205-0001',
            ],

            // Social Media Sequence (Daily Reset)
            [
                'name' => 'social_media',
                'prefix' => 'SM',
                'pattern' => '{PREFIX}{YY}{MM}{DD}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 4,
                'reset_daily' => true,
                'reset_monthly' => false,
                'reset_yearly' => false,
                'last_reset_date' => Carbon::today()->toDateString(),
                'is_active' => true,
                'description' => 'Social media tickets. Resets daily. Format: SM251205-0001',
            ],

            // Priority 1 (Critical) Sequence (Monthly Reset)
            [
                'name' => 'priority_p1',
                'prefix' => 'CRIT',
                'pattern' => '{PREFIX}-{YYYY}{MM}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 3,
                'reset_daily' => false,
                'reset_monthly' => true,
                'reset_yearly' => false,
                'last_reset_date' => Carbon::today()->toDateString(),
                'is_active' => false,
                'description' => 'Critical priority tickets (P1). Resets monthly. Format: CRIT-202512-001',
            ],

            // VIP Customer Sequence (Yearly Reset)
            [
                'name' => 'vip',
                'prefix' => 'VIP',
                'pattern' => '{PREFIX}{YYYY}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 5,
                'reset_daily' => false,
                'reset_monthly' => false,
                'reset_yearly' => true,
                'last_reset_date' => Carbon::today()->toDateString(),
                'is_active' => false,
                'description' => 'VIP customer tickets. Resets yearly. Format: VIP2025-00001',
            ],

            // Escalation Sequence (Monthly Reset)
            [
                'name' => 'escalation',
                'prefix' => 'ESC',
                'pattern' => '{PREFIX}-{YY}{MM}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 3,
                'reset_daily' => false,
                'reset_monthly' => true,
                'reset_yearly' => false,
                'last_reset_date' => Carbon::today()->toDateString(),
                'is_active' => false,
                'description' => 'Escalated tickets. Resets monthly. Format: ESC-2512-001',
            ],

            // Complaint Sequence (Monthly Reset)
            [
                'name' => 'complaint',
                'prefix' => 'CMP',
                'pattern' => '{PREFIX}{YYYY}{MM}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 4,
                'reset_daily' => false,
                'reset_monthly' => true,
                'reset_yearly' => false,
                'last_reset_date' => Carbon::today()->toDateString(),
                'is_active' => false,
                'description' => 'Complaint tickets. Resets monthly. Format: CMP202512-0001',
            ],

            // Internal Ticket Sequence (Daily Reset)
            [
                'name' => 'internal',
                'prefix' => 'INT',
                'pattern' => '{PREFIX}{YYYY}{MM}{DD}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 3,
                'reset_daily' => true,
                'reset_monthly' => false,
                'reset_yearly' => false,
                'last_reset_date' => Carbon::today()->toDateString(),
                'is_active' => false,
                'description' => 'Internal department tickets. Resets daily. Format: INT20251205-001',
            ],

            // Simple Sequential (No Reset)
            [
                'name' => 'sequential',
                'prefix' => 'SEQ',
                'pattern' => '{PREFIX}-{SEQUENCE}',
                'current_number' => 0,
                'padding' => 6,
                'reset_daily' => false,
                'reset_monthly' => false,
                'reset_yearly' => false,
                'last_reset_date' => null,
                'is_active' => false,
                'description' => 'Simple sequential numbering without reset. Format: SEQ-000001',
            ],
        ];

        foreach ($sequences as $sequence) {
            $model = TicketNumberSequence::create($sequence);
            // Generate and update sample output
            $model->sample_output = $model->generateSample();
            $model->save();
        }

        $this->command->info('Ticket number sequences seeded successfully!');
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('ACTIVE SEQUENCES (Channel-based, Daily Reset):');
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('  ✓ default     : General tickets      - TKT20251205-0001');
        $this->command->info('  ✓ phone       : Phone tickets        - PH251205-0001');
        $this->command->info('  ✓ email       : Email tickets        - EM251205-0001');
        $this->command->info('  ✓ chat        : Chat tickets         - CH251205-0001');
        $this->command->info('  ✓ web         : Web portal tickets   - WB251205-0001');
        $this->command->info('  ✓ walkIn      : Walk-in tickets      - WI251205-0001');
        $this->command->info('  ✓ social_media: Social media tickets - SM251205-0001');
        $this->command->info('');
        $this->command->info('INACTIVE SEQUENCES (Can be activated as needed):');
        $this->command->info('═══════════════════════════════════════════════════════════════');
        $this->command->info('  ✗ priority_p1 : Critical tickets (Monthly reset)');
        $this->command->info('  ✗ vip         : VIP customers (Yearly reset)');
        $this->command->info('  ✗ escalation  : Escalated tickets (Monthly reset)');
        $this->command->info('  ✗ complaint   : Complaints (Monthly reset)');
        $this->command->info('  ✗ internal    : Internal tickets (Daily reset)');
        $this->command->info('  ✗ sequential  : Simple sequential (No reset)');
        $this->command->info('═══════════════════════════════════════════════════════════════');
    }
}

