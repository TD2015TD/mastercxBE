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
        Schema::create('chatbot_sessions', function (Blueprint $table) {
            $table->string('interaction_id', 64)->primary();
            $table->string('customer_name', 255)->nullable();
            $table->string('customer_ic', 50)->nullable();
            $table->string('topic', 255)->nullable();
            $table->text('faq_path')->nullable();
            $table->string('status', 20)->default('queuing'); // queuing, active, closed
            $table->string('agent_id', 64)->nullable();
            $table->string('agent_name', 255)->nullable();
            $table->string('queue_name', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_sessions');
    }
};
