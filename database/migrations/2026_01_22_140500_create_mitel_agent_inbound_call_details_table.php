<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mitel_agent_inbound_call_details', function (Blueprint $table) {
            // Agent Information
            $table->uuid('agent_id')->nullable()->index();
            $table->string('agent_reporting', 50)->nullable()->index();
            $table->string('agent_first_name')->nullable();
            $table->string('agent_last_name')->nullable();
            
            // Extension Information
            $table->uuid('extension_id')->primary();
            $table->string('extension_reporting', 50)->nullable()->index();
            $table->string('extension_name')->nullable();
            
            // Trunk Information
            $table->uuid('trunk_id')->nullable();
            $table->string('trunk_reporting', 50)->nullable();
            $table->string('trunk_name')->nullable();
            
            // Queue Information
            $table->uuid('queue_id')->nullable()->index();
            $table->string('queue_reporting', 50)->nullable()->index();
            $table->string('queue_name')->nullable()->index();
            
            // Agent Group Information
            $table->uuid('agent_group_id')->nullable();
            $table->string('agent_group_reporting', 50)->nullable();
            $table->string('agent_group_name')->nullable();
            
            // DNIS Information
            $table->uuid('dnis_id')->nullable();
            $table->string('dnis_reporting', 50)->nullable();
            $table->string('dnis_name')->nullable();
            
            // Account Code Information
            $table->uuid('account_code_id')->nullable();
            $table->string('account_code_reporting', 50)->nullable();
            $table->string('account_code_name')->nullable();
            
            // ANI Information
            $table->uuid('ani_id')->nullable();
            $table->string('ani_reporting', 50)->nullable();
            $table->string('ani_name')->nullable();
            
            // Call Timing
            $table->timestamp('midnight_start_datetime')->nullable()->index();
            $table->date('date_of_call')->nullable()->index();
            $table->time('call_start_time')->nullable();
            
            // Call Details
            $table->string('call_type', 100)->nullable()->index();
            $table->integer('call_type_number')->nullable();
            $table->integer('duration')->default(0)->index(); // in seconds
            $table->integer('time_to_answer')->default(0); // in seconds
            $table->string('digits_dialed')->nullable();
            $table->integer('transfer_conference_number')->default(0);
            $table->string('transfer_conference', 50)->nullable();
            
            // Sync metadata
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            
            // Composite indexes for common queries
            $table->index(['date_of_call', 'call_type']);
            $table->index(['agent_reporting', 'date_of_call']);
            $table->index(['queue_name', 'date_of_call']);
            $table->index(['midnight_start_datetime', 'queue_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mitel_agent_inbound_call_details');
    }
};
