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
        // ANI (Automatic Number Identification) Configuration
        Schema::create('mitel_config_anis', function (Blueprint $table) {
            $table->uuid('pkey')->primary();
            $table->string('name', 100);
            $table->string('ani', 50); // The actual phone number
            $table->integer('digits_to_follow')->default(0);
            $table->boolean('auto_programmed')->default(false);
            $table->timestamp('last_modified')->nullable();
            $table->uuid('fk_site')->nullable();
            
            // Audit fields
            $table->timestamp('created_on')->useCurrent();
            $table->string('created_by', 255)->nullable();
            $table->timestamp('modified_on')->useCurrent();
            $table->string('modified_by', 255)->nullable();
            $table->uuid('created_by_client_id')->nullable();
            $table->uuid('modified_by_client_id')->nullable();
            
            // Sync tracking
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            
            $table->index(['created_on', 'created_by_client_id']);
            $table->index('ani');
            $table->index('synced_at');
        });

        // DNIS (Dialed Number Identification Service) Configuration
        Schema::create('mitel_config_dnis', function (Blueprint $table) {
            $table->uuid('pkey')->primary();
            $table->string('name', 50);
            $table->string('phone_number', 50); // The actual phone number
            $table->string('reporting', 50);
            $table->integer('service_level')->default(0);
            $table->integer('short_abd')->default(0);
            $table->boolean('auto_programmed')->default(false);
            $table->boolean('use_as_default')->default(false);
            $table->timestamp('last_modified')->nullable();
            $table->uuid('fk_node');
            
            // Audit fields
            $table->timestamp('created_on')->useCurrent();
            $table->string('created_by', 255)->nullable();
            $table->timestamp('modified_on')->useCurrent();
            $table->string('modified_by', 255)->nullable();
            $table->uuid('created_by_client_id')->nullable();
            $table->uuid('modified_by_client_id')->nullable();
            
            // Sync tracking
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            
            $table->unique(['reporting', 'fk_node']);
            $table->index(['created_on', 'created_by_client_id']);
            $table->index('phone_number');
            $table->index('reporting');
            $table->index('synced_at');
        });

        // Phone Number Group Configuration
        Schema::create('mitel_config_phone_number_groups', function (Blueprint $table) {
            $table->uuid('pkey')->primary();
            $table->string('reporting', 50)->unique();
            $table->string('name', 250)->nullable();
            
            // Audit fields
            $table->timestamp('created_on')->useCurrent();
            $table->string('created_by', 255)->nullable();
            $table->timestamp('modified_on')->useCurrent();
            $table->string('modified_by', 255)->nullable();
            $table->uuid('created_by_client_id')->nullable();
            $table->uuid('modified_by_client_id')->nullable();
            
            // Sync tracking
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            
            $table->index(['created_on', 'created_by_client_id']);
            $table->index('synced_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mitel_config_phone_number_groups');
        Schema::dropIfExists('mitel_config_dnis');
        Schema::dropIfExists('mitel_config_anis');
    }
};
