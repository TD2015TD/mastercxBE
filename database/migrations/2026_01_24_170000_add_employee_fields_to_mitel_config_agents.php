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
        Schema::table('mitel_config_agents', function (Blueprint $table) {
            // Employee Fields (from tblConfig_Employee via JOIN)
            $table->string('employee_reporting_id', 50)->nullable()->after('fk_fail_over')->comment('Employee.Reporting');
            $table->string('employee_first_name', 50)->nullable()->after('employee_reporting_id');
            $table->string('employee_last_name', 50)->nullable()->after('employee_first_name');
            $table->string('middle_name', 50)->nullable()->after('employee_last_name');
            $table->string('suffix', 15)->nullable()->after('middle_name');
            $table->string('nick_name', 50)->nullable()->after('suffix');
            $table->string('email_address', 320)->nullable()->after('nick_name')->comment('Primary email');
            $table->string('phone', 50)->nullable()->after('email_address');
            $table->string('business_phone', 50)->nullable()->after('phone');
            $table->string('fax', 50)->nullable()->after('business_phone');
            $table->string('pager', 50)->nullable()->after('fax');
            $table->string('street_address', 512)->nullable()->after('pager');
            $table->string('city', 50)->nullable()->after('street_address');
            $table->string('state_province', 50)->nullable()->after('city');
            $table->string('zip_postal', 10)->nullable()->after('state_province');
            $table->string('country_id', 36)->nullable()->after('zip_postal');
            $table->dateTime('employee_active_date')->nullable()->after('country_id');
            $table->dateTime('birth_date')->nullable()->after('employee_active_date');
            $table->boolean('employee_is_active')->default(false)->after('birth_date');
            $table->boolean('is_scheduled')->default(false)->after('employee_is_active');
            $table->boolean('is_supervisor')->default(false)->after('is_scheduled');
            $table->string('supervisor_id', 36)->nullable()->after('is_supervisor');
            $table->string('emergency_contact_name', 50)->nullable()->after('supervisor_id');
            $table->string('emergency_contact_relationship', 50)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone', 50)->nullable()->after('emergency_contact_relationship');
            $table->string('associated_identity', 255)->nullable()->after('emergency_contact_phone')->comment('AD/SSO identity');
            $table->boolean('exists_in_ad')->default(false)->after('associated_identity');
            $table->integer('telephony_endpoint_type')->nullable()->after('exists_in_ad');
            $table->integer('supervisor_license_type')->nullable()->after('telephony_endpoint_type');
            $table->boolean('has_license_for_mcc')->default(false)->after('supervisor_license_type');
            $table->boolean('has_license_for_voice')->default(false)->after('has_license_for_mcc');
            $table->boolean('has_license_for_screen_pop')->default(false)->after('has_license_for_voice');
            $table->string('photo_url', 512)->nullable()->after('has_license_for_screen_pop');
            $table->string('sip_address', 250)->nullable()->after('photo_url');
            $table->string('line_uri', 250)->nullable()->after('sip_address');
            $table->string('cloudlink_id', 320)->nullable()->after('line_uri');
            $table->boolean('cloudlink_chat_enabled')->default(false)->after('cloudlink_id');
            $table->boolean('is_may_see_all_queues_enabled')->default(false)->after('cloudlink_chat_enabled');
            
            // Add indexes for employee fields
            $table->index('employee_reporting_id');
            $table->index('email_address');
            $table->index('employee_is_active');
            $table->index('supervisor_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('mitel_config_agents', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['employee_reporting_id']);
            $table->dropIndex(['email_address']);
            $table->dropIndex(['employee_is_active']);
            $table->dropIndex(['supervisor_id']);
            
            // Drop columns
            $table->dropColumn([
                'employee_reporting_id',
                'employee_first_name',
                'employee_last_name',
                'middle_name',
                'suffix',
                'nick_name',
                'email_address',
                'phone',
                'business_phone',
                'fax',
                'pager',
                'street_address',
                'city',
                'state_province',
                'zip_postal',
                'country_id',
                'employee_active_date',
                'birth_date',
                'employee_is_active',
                'is_scheduled',
                'is_supervisor',
                'supervisor_id',
                'emergency_contact_name',
                'emergency_contact_relationship',
                'emergency_contact_phone',
                'associated_identity',
                'exists_in_ad',
                'telephony_endpoint_type',
                'supervisor_license_type',
                'has_license_for_mcc',
                'has_license_for_voice',
                'has_license_for_screen_pop',
                'photo_url',
                'sip_address',
                'line_uri',
                'cloudlink_id',
                'cloudlink_chat_enabled',
                'is_may_see_all_queues_enabled',
            ]);
        });
    }
};
