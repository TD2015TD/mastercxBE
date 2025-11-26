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
        Schema::create('sla_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('department');
            $table->enum('priority', ['P1', 'P2', 'P3', 'P4']);
            $table->integer('duration_minutes');
            $table->boolean('business_hours_only')->default(false);
            $table->boolean('pause_on_hold')->default(true);
            $table->boolean('pause_on_pending')->default(false);
            $table->tinyInteger('escalation_threshold_percent')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['department', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_configurations');
    }
};
