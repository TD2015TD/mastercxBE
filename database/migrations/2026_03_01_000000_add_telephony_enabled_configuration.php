<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('mitel_configurations')->insertOrIgnore([
            'key' => 'TELEPHONY_ENABLED',
            'value' => 'false',
            'description' => 'Enable or disable telephony features for all agents. When disabled, the telephony bar is hidden and agents cannot make or receive calls through the CRM.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('mitel_configurations')
            ->where('key', 'TELEPHONY_ENABLED')
            ->delete();
    }
};
