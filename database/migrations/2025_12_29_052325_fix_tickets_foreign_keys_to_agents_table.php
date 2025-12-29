<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Drop existing foreign key constraints
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['created_by']);
        });

        // Clean up invalid data before adding new constraints
        // Set assigned_to to NULL where the ID doesn't exist in agents table
        DB::statement('
            UPDATE tickets
            SET assigned_to = NULL
            WHERE assigned_to IS NOT NULL
            AND assigned_to NOT IN (SELECT id FROM agents)
        ');

        // Set created_by to NULL where the ID doesn't exist in agents table
        DB::statement('
            UPDATE tickets
            SET created_by = NULL
            WHERE created_by IS NOT NULL
            AND created_by NOT IN (SELECT id FROM agents)
        ');

        Schema::table('tickets', function (Blueprint $table) {
            // Re-add foreign keys pointing to agents table
            $table->foreign('assigned_to')->references('id')->on('agents')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('agents')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Drop foreign keys pointing to agents
            $table->dropForeign(['assigned_to']);
            $table->dropForeign(['created_by']);

            // Re-add foreign keys pointing to users table (original state)
            $table->foreign('assigned_to')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }
};
