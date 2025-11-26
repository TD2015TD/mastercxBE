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
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Agent Profile Information
            $table->string('agent_code')->unique();
            $table->string('employee_id')->unique()->nullable();
            $table->string('department');
            $table->string('designation')->nullable();
            
            // Mitel Integration
            $table->string('mitel_agent_id')->unique()->nullable();
            $table->string('mitel_extension')->nullable();
            $table->string('mitel_queue_ids')->nullable(); // JSON array of queue IDs
            
            // Agent Status
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->enum('availability', ['available', 'busy', 'away', 'offline'])->default('offline');
            
            // Skills and Permissions
            $table->json('skills')->nullable(); // e.g., ['customs', 'trade', 'gst']
            $table->json('languages')->nullable(); // e.g., ['en', 'ms', 'zh']
            $table->integer('max_concurrent_tickets')->default(5);
            
            // Performance Tracking
            $table->integer('tickets_resolved_today')->default(0);
            $table->integer('daily_target')->default(15);
            $table->decimal('avg_response_time', 8, 2)->default(0); // in minutes
            $table->decimal('sla_compliance_rate', 5, 2)->default(0); // percentage
            $table->decimal('customer_satisfaction', 3, 2)->default(0); // rating out of 5
            
            // Work Schedule
            $table->time('shift_start')->nullable();
            $table->time('shift_end')->nullable();
            $table->json('working_days')->nullable(); // e.g., [1,2,3,4,5] for Mon-Fri
            
            // Additional Info
            $table->text('notes')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('agent_code');
            $table->index('department');
            $table->index('status');
            $table->index('availability');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
