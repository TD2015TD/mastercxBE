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
        Schema::create('mitel_agent_outbound_call_details', function (Blueprint $table) {
            // Agent Information
            $table->uuid('agent_id')->nullable()->comment('Agent unique identifier');
            $table->string('agent_reporting', 100)->nullable()->comment('Agent reporting code');
            $table->string('agent_first_name', 100)->nullable()->comment('Agent first name');
            $table->string('agent_last_name', 100)->nullable()->comment('Agent last name');
            
            // Extension Information (Primary Key for call uniqueness)
            $table->uuid('extension_id')->primary()->comment('Extension unique identifier - unique per call');
            $table->string('extension_reporting', 100)->nullable()->comment('Extension reporting code');
            $table->string('extension_name', 255)->nullable()->comment('Extension name');
            
            // Trunk Information
            $table->uuid('trunk_id')->nullable()->comment('Trunk unique identifier');
            $table->string('trunk_reporting', 100)->nullable()->comment('Trunk reporting code');
            $table->string('trunk_name', 255)->nullable()->comment('Trunk name');
            
            // Account Code Information
            $table->uuid('account_code_id')->nullable()->comment('Account code unique identifier');
            $table->string('account_code_reporting', 100)->nullable()->comment('Account code reporting value');
            $table->string('account_code_name', 255)->nullable()->comment('Account code name');
            
            // Call Timestamps
            $table->timestamp('midnight_start_datetime')->nullable()->comment('Call start time (midnight-based)');
            $table->date('date_of_call')->nullable()->index()->comment('Date of the call');
            $table->time('call_start_time')->nullable()->comment('Call start time');
            
            // Call Details
            $table->string('call_type', 100)->nullable()->comment('Type of call (e.g., InternalNonACDAnswered)');
            $table->integer('call_type_number')->nullable()->comment('Numeric call type code');
            $table->integer('duration')->default(0)->comment('Call duration in seconds');
            $table->string('phone_number', 50)->nullable()->comment('Phone number dialed');
            
            // Sync Metadata
            $table->timestamp('synced_at')->nullable()->comment('When this record was synced from Mitel');
            $table->timestamp('updated_at')->nullable()->comment('Last update timestamp');
            
            // Indexes for performance
            $table->index('agent_id', 'idx_agent_outbound_agent_id');
            $table->index('date_of_call', 'idx_agent_outbound_date_of_call');
            $table->index(['agent_id', 'date_of_call'], 'idx_agent_outbound_agent_date');
            $table->index('phone_number', 'idx_agent_outbound_phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mitel_agent_outbound_call_details');
    }
};
