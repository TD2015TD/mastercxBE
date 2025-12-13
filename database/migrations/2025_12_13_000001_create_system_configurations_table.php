<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('category'); // e.g., 'mail', 'auth', 'general'
            $table->string('key')->unique(); // e.g., 'mail.driver', 'auth.method'
            $table->text('value')->nullable(); // Encrypted for sensitive data
            $table->string('type')->default('string'); // string, boolean, integer, json, encrypted
            $table->text('description')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Additional config data
            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_configurations');
    }
};
