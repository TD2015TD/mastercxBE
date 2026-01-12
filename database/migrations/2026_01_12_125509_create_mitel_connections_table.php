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
        Schema::create('mitel_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->string('host');
            $table->integer('port')->default(1433);
            $table->string('database');
            $table->string('username');
            $table->string('password'); // Should be encrypted in the model
            $table->boolean('encrypt')->default(true);
            $table->boolean('trust_server_certificate')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->string('sync_status')->nullable(); // 'success', 'failed', 'running'
            $table->text('last_sync_message')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mitel_connections');
    }
};
