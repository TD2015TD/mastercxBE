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
        Schema::create('customer_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('cascade');
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->onDelete('set null');
            $table->foreignId('agent_id')->nullable()->constrained('agents')->onDelete('set null');
            $table->enum('interaction_type', ['call', 'email', 'chat', 'sms', 'meeting', 'note']);
            $table->enum('channel', ['phone', 'email', 'chat', 'web', 'walk-in', 'social_media']);
            $table->string('subject', 500);
            $table->text('description')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('interaction_at');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['customer_id', 'interaction_at']);
            $table->index(['agent_id', 'interaction_at']);
            $table->index('interaction_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_interactions');
    }
};
