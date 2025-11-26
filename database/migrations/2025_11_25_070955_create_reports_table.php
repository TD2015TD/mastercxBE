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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('report_type', ['agent_performance', 'supervisor_team', 'sla_compliance', 'customer_satisfaction', 'ticket_volume']);
            $table->foreignId('generated_by')->constrained('users')->onDelete('cascade');
            $table->json('parameters')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->enum('file_type', ['pdf', 'excel', 'csv']);
            $table->bigInteger('file_size')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('download_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['generated_by', 'report_type']);
            $table->index(['status', 'generated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
