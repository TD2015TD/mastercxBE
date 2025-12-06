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
        Schema::create('citizen_auth_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 20);
            $table->string('email', 255);
            $table->string('otp_code', 6);
            $table->timestamp('otp_expires_at');
            $table->text('session_token')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('phone_number');
            $table->index(['phone_number', 'otp_code']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('citizen_auth_sessions');
    }
};
