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
        Schema::create('mitel_agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('agent_id')->unique()->comment('Pkey from tblConfig_Agent');
            $table->string('name');
            $table->string('reporting_id')->nullable();
            $table->uuid('agent_type_id')->nullable();
            $table->string('dialable')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            
            $table->index('agent_id');
            $table->index('reporting_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mitel_agents');
    }
};
