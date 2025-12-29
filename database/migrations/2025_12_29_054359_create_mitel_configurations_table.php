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
        Schema::create('mitel_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert default Mitel configuration values
        DB::table('mitel_configurations')->insert([
            [
                'key' => 'MITEL_BASE_URL',
                'value' => 'https://micc.telediant.com.my',
                'description' => 'Mitel MiContact Center Base URL',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'MITEL_SIGNALR_URL',
                'value' => 'https://micc.telediant.com.my/MiccSdk/signalr',
                'description' => 'Mitel SignalR URL for real-time notifications',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'MITEL_CLIENT_ID',
                'value' => 'CXCRM',
                'description' => 'Mitel OAuth Client ID',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'MITEL_CLIENT_SECRET',
                'value' => '98538800-3af7-4b8f-bfca-f9065b88e5b4',
                'description' => 'Mitel OAuth Client Secret',
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
        Schema::dropIfExists('mitel_configurations');
    }
};
