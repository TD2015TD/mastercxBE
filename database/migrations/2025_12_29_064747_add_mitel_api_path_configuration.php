<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add MITEL_API_PATH configuration
        DB::table('mitel_configurations')->insert([
            [
                'key' => 'MITEL_API_PATH',
                'value' => '/miccsdk',
                'description' => 'API path for Mitel MiContact Center SDK (e.g., /miccsdk or /MiccSdk)',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('mitel_configurations')
            ->where('key', 'MITEL_API_PATH')
            ->delete();
    }
};
