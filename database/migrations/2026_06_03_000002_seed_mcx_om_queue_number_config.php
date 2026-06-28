<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the MCX_OM_QUEUE_NUMBER config entry into mitel_configurations.
 *
 * Replace the placeholder value with your actual Queue Reporting Number
 * before running this migration, or update it afterwards via the admin UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        $configs = [
            [
                'key'         => 'MCX_OM_QUEUE_NUMBER',
                'value'       => '<QUEUE_NUMBER>',
                'description' => 'MCX Open Media v2 — Queue Reporting Number for inbound email interactions',
                'is_active'   => true,
            ],
        ];

        foreach ($configs as $config) {
            DB::table('mitel_configurations')->updateOrInsert(
                ['key' => $config['key']],
                array_merge($config, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        DB::table('mitel_configurations')
            ->where('key', 'MCX_OM_QUEUE_NUMBER')
            ->delete();
    }
};
