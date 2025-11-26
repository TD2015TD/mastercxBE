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
        Schema::create('ticket_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'default', 'urgent', 'vip'
            $table->string('prefix')->default('TKT'); // e.g., 'TKT', 'URG', 'VIP'
            $table->string('pattern')->default('{PREFIX}{YYYY}{MM}{DD}-{SEQUENCE}'); // Pattern template
            $table->integer('current_number')->default(0); // Current sequence number
            $table->integer('padding')->default(4); // Number padding (e.g., 0001, 00001)
            $table->boolean('reset_daily')->default(false); // Reset sequence daily
            $table->boolean('reset_monthly')->default(false); // Reset sequence monthly
            $table->boolean('reset_yearly')->default(false); // Reset sequence yearly
            $table->date('last_reset_date')->nullable(); // Track last reset
            $table->string('sample_output')->nullable(); // Example: TKT20251123-0001
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index('name');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_number_sequences');
    }
};
