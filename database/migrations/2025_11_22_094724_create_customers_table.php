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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            
            // Basic Information
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone_number', 20);
            
            // Identity Verification
            $table->enum('id_type', ['MyKad', 'MyTentera', 'MyPolis', 'MyPR', 'Passport']);
            $table->string('id_no')->unique();
            
            // Company Information (optional for business customers)
            $table->string('company_name')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('country', 100)->default('Malaysia');
            
            // Customer Classification
            $table->enum('customer_type', ['individual', 'business'])->default('individual');
            $table->string('business_registration_no', 50)->nullable();
            
            // Preferences
            $table->enum('preferred_language', ['en', 'ms', 'zh', 'ta'])->default('en');
            
            // Status
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('active');
            
            // Metrics
            $table->integer('total_tickets')->default(0);
            $table->integer('resolved_tickets')->default(0);
            $table->decimal('satisfaction_rating', 3, 2)->nullable();
            
            // Tracking
            $table->timestamp('last_contact_at')->nullable();
            
            // Additional Information
            $table->json('tags')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('status');
            $table->index('customer_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
