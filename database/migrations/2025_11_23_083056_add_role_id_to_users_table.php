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
        Schema::table('users', function (Blueprint $table) {
            // Drop the old enum role column if it exists
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
            
            // Add role_id foreign key
            $table->foreignId('role_id')->nullable()->after('email')->constrained('roles')->onDelete('set null');
            
            // Add status column
            $table->string('status')->default('active')->after('role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['role_id', 'status']);
            
            // Restore the old enum role column
            $table->enum('role', ['agent', 'supervisor', 'admin'])->default('agent')->after('email');
        });
    }
};
