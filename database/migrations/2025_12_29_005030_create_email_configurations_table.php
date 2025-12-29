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
        Schema::create('email_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['smtp', 'imap', 'both'])->default('both');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_default')->default(false)->index();

            // SMTP Configuration
            $table->string('smtp_host')->nullable();
            $table->integer('smtp_port')->nullable();
            $table->string('smtp_encryption')->nullable(); // ssl, tls, or null
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('smtp_from_address')->nullable();
            $table->string('smtp_from_name')->nullable();

            // IMAP Configuration
            $table->string('imap_host')->nullable();
            $table->integer('imap_port')->nullable();
            $table->string('imap_encryption')->nullable(); // ssl, tls, or null
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();
            $table->string('imap_folder')->default('INBOX');

            // Additional Settings
            $table->string('department')->nullable();
            $table->boolean('auto_assign')->default(true);
            $table->string('default_priority')->default('P3');
            $table->json('settings')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('type');
            $table->index(['type', 'is_active', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_configurations');
    }
};
