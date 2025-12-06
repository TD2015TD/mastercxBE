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
        Schema::create('agent_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents')->onDelete('cascade')
                  ->comment('Reference to the agent');
            $table->date('shift_date')
                  ->comment('Date for this shift');
            $table->time('shift_start')->nullable()
                  ->comment('Shift start time');
            $table->time('shift_end')->nullable()
                  ->comment('Shift end time');
            $table->string('shift_type')->nullable()
                  ->comment('Type: morning, afternoon, evening, night');
            $table->integer('break_duration')->default(0)
                  ->comment('Break duration in minutes');
            $table->boolean('is_off_day')->default(false)
                  ->comment('Whether this is an off day');
            $table->text('notes')->nullable()
                  ->comment('Additional notes about the shift');
            
            $table->timestamps();
            
            // Composite unique index: one shift per agent per date
            $table->unique(['agent_id', 'shift_date'], 'agent_shifts_agent_date_unique');
            
            // Indexes for common queries
            $table->index('shift_date');
            $table->index(['agent_id', 'shift_date']);
            $table->index('shift_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_shifts');
    }
};
