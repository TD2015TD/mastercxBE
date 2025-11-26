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
        Schema::create('email_integrations', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->onDelete('set null');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->string('from_email');
            $table->string('to_email');
            $table->json('cc_emails')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->longText('body_html')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->json('attachments')->nullable();
            $table->json('headers')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'received_at']);
            $table->index('from_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_integrations');
    }
};
