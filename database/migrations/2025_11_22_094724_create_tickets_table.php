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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            
            // Unique Identifier
            $table->string('ticket_number', 50)->unique();
            
            // Relationships
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Classification
            $table->enum('priority', ['P1', 'P2', 'P3', 'P4'])->default('P3');
            $table->string('department', 100);
            $table->string('category', 100);
            $table->string('sub_category', 100)->nullable();
            
            // Content
            $table->string('subject');
            $table->text('description');
            
            // Assessment
            $table->enum('urgency', ['Low', 'Medium', 'High', 'Critical'])->nullable();
            $table->enum('impact', ['Low', 'Medium', 'High', 'Critical'])->nullable();
            
            // Status
            $table->enum('status', [
                'Open', 
                'In Progress', 
                'Pending', 
                'On Hold', 
                'Resolved', 
                'Closed', 
                'Cancelled'
            ])->default('Open');
            
            // Resolution
            $table->text('resolution_notes')->nullable();
            
            // SLA Management
            $table->integer('sla_duration_minutes')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->boolean('sla_breached')->default(false);
            $table->integer('sla_remaining_minutes')->nullable();
            
            // Response Times
            $table->timestamp('first_response_at')->nullable();
            $table->integer('first_response_time_minutes')->nullable();
            $table->integer('resolution_time_minutes')->nullable();

            // Resolution/Closure Timestamps
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            
            // Communication
            $table->enum('channel', ['phone', 'email', 'chat', 'web', 'walk-in'])->default('phone');
            $table->string('source', 100)->nullable();
            
            // Customer Feedback
            $table->integer('customer_rating')->nullable();
            $table->text('customer_feedback')->nullable();
            
            // Additional Information
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();
            
            // Reopened tracking
            $table->integer('reopened_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('status');
            $table->index('priority');
            $table->index('department');
            $table->index('category');
            $table->index('sla_due_at');
            $table->index('sla_breached');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
