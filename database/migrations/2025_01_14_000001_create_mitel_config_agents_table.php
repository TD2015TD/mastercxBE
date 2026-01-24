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
        // Agent Configuration
        if (!Schema::hasTable('mitel_config_agents')) {
            Schema::create('mitel_config_agents', function (Blueprint $table) {
                // Agent Core Fields
                $table->string('pkey', 36)->primary()->comment('tblConfig_Agent.Pkey');
                $table->string('fk_employee', 36)->nullable()->comment('FK to tblConfig_Employee.Pkey');
                $table->string('fk_master_agent', 36)->nullable();
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->string('reporting', 50)->nullable()->comment('Agent reporting ID');
                $table->boolean('auto_programmed')->default(false);
                $table->boolean('is_active')->default(true);
                $table->boolean('exists_on_pbx')->default(true);
                $table->timestamp('last_modified')->nullable();
                $table->string('fk_site', 36)->nullable();
                $table->string('fk_node', 36)->nullable();
                $table->string('fk_fail_over', 36)->nullable();
                
                // Employee Fields (from tblConfig_Employee via JOIN)
                $table->string('employee_reporting_id', 50)->nullable()->comment('Employee.Reporting');
                $table->string('employee_first_name', 50)->nullable();
                $table->string('employee_last_name', 50)->nullable();
                $table->string('middle_name', 50)->nullable();
                $table->string('suffix', 15)->nullable();
                $table->string('nick_name', 50)->nullable();
                $table->string('email_address', 320)->nullable()->comment('Primary email');
                $table->string('phone', 50)->nullable();
                $table->string('business_phone', 50)->nullable();
                $table->string('fax', 50)->nullable();
                $table->string('pager', 50)->nullable();
                $table->string('street_address', 512)->nullable();
                $table->string('city', 50)->nullable();
                $table->string('state_province', 50)->nullable();
                $table->string('zip_postal', 10)->nullable();
                $table->string('country_id', 36)->nullable();
                $table->dateTime('employee_active_date')->nullable();
                $table->dateTime('birth_date')->nullable();
                $table->boolean('employee_is_active')->default(false);
                $table->boolean('is_scheduled')->default(false);
                $table->boolean('is_supervisor')->default(false);
                $table->string('supervisor_id', 36)->nullable();
                $table->string('emergency_contact_name', 50)->nullable();
                $table->string('emergency_contact_relationship', 50)->nullable();
                $table->string('emergency_contact_phone', 50)->nullable();
                $table->string('associated_identity', 255)->nullable()->comment('AD/SSO identity');
                $table->boolean('exists_in_ad')->default(false);
                $table->integer('telephony_endpoint_type')->nullable();
                $table->integer('supervisor_license_type')->nullable();
                $table->boolean('has_license_for_mcc')->default(false);
                $table->boolean('has_license_for_voice')->default(false);
                $table->boolean('has_license_for_screen_pop')->default(false);
                $table->string('photo_url', 512)->nullable();
                $table->string('sip_address', 250)->nullable();
                $table->string('line_uri', 250)->nullable();
                $table->string('cloudlink_id', 320)->nullable();
                $table->boolean('cloudlink_chat_enabled')->default(false);
                $table->boolean('is_may_see_all_queues_enabled')->default(false);
                
                // Audit fields
                $table->timestamp('created_on')->useCurrent();
                $table->string('created_by', 255)->nullable();
                $table->timestamp('modified_on')->useCurrent();
                $table->string('modified_by', 255)->nullable();
                $table->string('created_by_client_id', 36)->nullable();
                $table->string('modified_by_client_id', 36)->nullable();
                
                // Sync tracking
                $table->timestamp('synced_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                
                // Indexes for performance
                $table->index('fk_employee'); // For joining with CRM agents
                $table->index('reporting');
                $table->index('employee_reporting_id');
                $table->index('email_address');
                $table->index('is_active');
                $table->index('employee_is_active');
                $table->index('supervisor_id');
                $table->index(['created_on', 'created_by_client_id']);
                $table->index('synced_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mitel_config_agents');
    }
};
