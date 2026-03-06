<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Update web channel ticket number prefix from WEB (3 chars) to WB (2 chars)
     * for consistency with all other channel prefixes (PH, EM, CH, WI, SM).
     */
    public function up(): void
    {
        DB::table('ticket_number_sequences')
            ->where('name', 'web')
            ->update([
                'prefix' => 'WB',
                'description' => 'Web portal tickets. Resets daily. Format: WB260306-0001',
                'updated_at' => now(),
            ]);

        // Regenerate sample output
        $sequence = DB::table('ticket_number_sequences')->where('name', 'web')->first();
        if ($sequence) {
            $date = now();
            $sample = 'WB' . $date->format('y') . $date->format('m') . $date->format('d') . '-' . str_pad(1, 4, '0', STR_PAD_LEFT);
            DB::table('ticket_number_sequences')
                ->where('name', 'web')
                ->update(['sample_output' => $sample]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ticket_number_sequences')
            ->where('name', 'web')
            ->update([
                'prefix' => 'WEB',
                'description' => 'Web portal tickets. Resets daily. Format: WEB251205-0001',
                'updated_at' => now(),
            ]);
    }
};
