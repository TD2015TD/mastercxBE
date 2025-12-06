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
        Schema::create('sla_engine_settings', function (Blueprint $table) {
            $table->id();
            
            // Business Hours Configuration
            $table->boolean('business_hours_enabled')->default(true);
            $table->time('business_hours_start')->default('09:00:00');
            $table->time('business_hours_end')->default('17:00:00');
            $table->string('timezone')->default('Asia/Kuala_Lumpur');
            $table->json('working_days')->nullable();
            
            // Escalation Rules
            $table->boolean('escalation_enabled')->default(true);
            $table->integer('default_escalation_threshold')->default(80);
            $table->boolean('notify_agent')->default(true);
            $table->boolean('notify_supervisor')->default(true);
            $table->boolean('notify_manager')->default(false);
            
            // Pause Conditions
            $table->boolean('pause_on_hold')->default(true);
            $table->boolean('pause_on_pending')->default(true);
            $table->boolean('pause_on_awaiting_customer')->default(true);
            
            // Auto-escalation settings
            $table->boolean('auto_escalate_on_breach')->default(true);
            $table->integer('escalation_delay_minutes')->default(0)->comment('Delay before auto-escalation after breach');
            
            // Metadata
            $table->string('updated_by')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
        
        // Insert default settings
        DB::table('sla_engine_settings')->insert([
            'business_hours_enabled' => true,
            'business_hours_start' => '09:00:00',
            'business_hours_end' => '17:00:00',
            'timezone' => 'Asia/Kuala_Lumpur',
            'working_days' => json_encode(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']),
            'escalation_enabled' => true,
            'default_escalation_threshold' => 80,
            'notify_agent' => true,
            'notify_supervisor' => true,
            'notify_manager' => false,
            'pause_on_hold' => true,
            'pause_on_pending' => true,
            'pause_on_awaiting_customer' => true,
            'auto_escalate_on_breach' => true,
            'escalation_delay_minutes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_engine_settings');
    }
};
