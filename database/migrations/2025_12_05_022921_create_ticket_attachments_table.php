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
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 50)->nullable(); // e.g., 'image', 'document', 'video'
            $table->integer('file_size')->unsigned(); // in bytes
            $table->string('mime_type', 100)->nullable(); // e.g., 'image/jpeg', 'application/pdf'
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('ticket_id');
            $table->index('uploaded_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
