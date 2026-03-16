<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Update phone/email/chat sequences to new TC/TE/TT format with monthly reset.
     * Pattern: {PREFIX}-{YY}{MM}{DD}-{SEQUENCE}, 4-digit padding, resets every new month.
     */
    public function up(): void
    {
        $sequences = [
            'phone' => [
                'prefix'        => 'TC',
                'pattern'       => '{PREFIX}-{YY}{MM}{DD}-{SEQUENCE}',
                'padding'       => 5,
                'reset_daily'   => false,
                'reset_monthly' => true,
                'reset_yearly'  => false,
                'description'   => 'Phone call tickets. Resets monthly. Format: TC-250316-00001',
            ],
            'email' => [
                'prefix'        => 'TE',
                'pattern'       => '{PREFIX}-{YY}{MM}{DD}-{SEQUENCE}',
                'padding'       => 5,
                'reset_daily'   => false,
                'reset_monthly' => true,
                'reset_yearly'  => false,
                'description'   => 'Email tickets. Resets monthly. Format: TE-250316-00001',
            ],
            'chat' => [
                'prefix'        => 'TT',
                'pattern'       => '{PREFIX}-{YY}{MM}{DD}-{SEQUENCE}',
                'padding'       => 5,
                'reset_daily'   => false,
                'reset_monthly' => true,
                'reset_yearly'  => false,
                'description'   => 'Live chat tickets. Resets monthly. Format: TT-250316-00001',
            ],
        ];

        foreach ($sequences as $name => $data) {
            DB::table('ticket_number_sequences')
                ->where('name', $name)
                ->update($data);
        }
    }

    /**
     * Reverse to the previous daily-reset format.
     */
    public function down(): void
    {
        $sequences = [
            'phone' => [
                'prefix'        => 'PH',
                'pattern'       => '{PREFIX}{YY}{MM}{DD}-{SEQUENCE}',
                'padding'       => 4,
                'reset_daily'   => true,
                'reset_monthly' => false,
                'reset_yearly'  => false,
                'description'   => 'Phone call tickets. Resets daily. Format: PH251205-0001',
            ],
            'email' => [
                'prefix'        => 'EM',
                'pattern'       => '{PREFIX}{YY}{MM}{DD}-{SEQUENCE}',
                'padding'       => 4,
                'reset_daily'   => true,
                'reset_monthly' => false,
                'reset_yearly'  => false,
                'description'   => 'Email tickets. Resets daily. Format: EM251205-0001',
            ],
            'chat' => [
                'prefix'        => 'CH',
                'pattern'       => '{PREFIX}{YY}{MM}{DD}-{SEQUENCE}',
                'padding'       => 4,
                'reset_daily'   => true,
                'reset_monthly' => false,
                'reset_yearly'  => false,
                'description'   => 'Live chat tickets. Resets daily. Format: CH251205-0001',
            ],
        ];

        foreach ($sequences as $name => $data) {
            DB::table('ticket_number_sequences')
                ->where('name', $name)
                ->update($data);
        }
    }
};
