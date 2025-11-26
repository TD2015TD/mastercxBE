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
        Schema::create('escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('from_agent_id')->nullable()->constrained('agents')->onDelete('set null');
            $table->foreignId('to_agent_id')->constrained('agents')->onDelete('cascade');
            $table->foreignId('escalated_by')->constrained('users')->onDelete('cascade');
            $table->enum('level', ['L1', 'L2', 'Supervisor', 'Manager']);
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->enum('escalation_type', ['manual', 'auto', 'sla_risk']);
            $table->enum('status', ['pending', 'acknowledged', 'completed'])->default('pending');
            $table->timestamp('escalated_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['ticket_id', 'status']);
            $table->index(['to_agent_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escalations');
    }
};
