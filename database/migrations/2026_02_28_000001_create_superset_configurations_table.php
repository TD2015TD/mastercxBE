<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('superset_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('superset_configurations')->insert([
            [
                'key' => 'SUPERSET_URL',
                'value' => 'http://localhost:8088',
                'description' => 'Apache Superset base URL (e.g., http://superset.example.com)',
                'is_secret' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'SUPERSET_USERNAME',
                'value' => 'admin',
                'description' => 'Superset admin username for API authentication',
                'is_secret' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'SUPERSET_PASSWORD',
                'value' => 'admin',
                'description' => 'Superset admin password for API authentication',
                'is_secret' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('superset_configurations');
    }
};
