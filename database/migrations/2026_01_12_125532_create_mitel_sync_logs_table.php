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
        Schema::create('mitel_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitel_connection_id')->constrained()->onDelete('cascade');
            $table->string('sync_type'); // 'full', 'incremental'
            $table->string('table_name')->nullable();
            $table->string('status'); // 'started', 'success', 'failed'
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('records_fetched')->default(0);
            $table->integer('records_inserted')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_failed')->default(0);
            $table->text('error_message')->nullable();
            $table->json('sync_details')->nullable(); // Additional metadata
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mitel_sync_logs');
    }
};
