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
        Schema::create('superset_dashboards', function (Blueprint $table) {
            $table->id();
            $table->string('superset_id')->unique()->comment('Superset dashboard UUID or ID');
            $table->string('slug')->unique()->comment('URL-friendly identifier');

            // Bilingual fields - English
            $table->string('title_en')->comment('Dashboard title in English');
            $table->text('description_en')->nullable()->comment('Dashboard description in English');

            // Bilingual fields - Malay
            $table->string('title_ms')->comment('Dashboard title in Malay');
            $table->text('description_ms')->nullable()->comment('Dashboard description in Malay');

            // Dashboard metadata
            $table->string('category')->nullable()->comment('Dashboard category (e.g., Sales, Operations, Analytics)');
            $table->string('icon')->nullable()->comment('Icon class or name for UI display');
            $table->integer('order')->default(0)->comment('Display order');
            $table->boolean('is_active')->default(true)->comment('Whether dashboard is active and visible');
            $table->boolean('is_public')->default(false)->comment('Whether dashboard is accessible without authentication');

            // Embedding configuration (stored as JSON)
            $table->json('ui_config')->nullable()->comment('Dashboard UI configuration (hideTitle, hideTab, etc.)');
            $table->json('default_filters')->nullable()->comment('Default filter values');
            $table->json('rls_config')->nullable()->comment('Row Level Security configuration template');

            // Metadata
            $table->text('tags')->nullable()->comment('Comma-separated tags for search/filtering');
            $table->string('thumbnail_url')->nullable()->comment('URL to dashboard thumbnail/preview image');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('category');
            $table->index('is_active');
            $table->index('order');
        });

        // Pivot table for role-dashboard relationship
        Schema::create('role_superset_dashboard', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('superset_dashboard_id')->constrained()->onDelete('cascade');

            // Permissions
            $table->boolean('can_view')->default(true);
            $table->boolean('can_export')->default(false);
            $table->boolean('can_share')->default(false);

            // Custom RLS for this role (optional override)
            $table->json('custom_rls')->nullable()->comment('Custom RLS rules for this role');

            $table->timestamps();

            // Ensure unique role-dashboard combinations
            $table->unique(['role_id', 'superset_dashboard_id'], 'role_dashboard_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_superset_dashboard');
        Schema::dropIfExists('superset_dashboards');
    }
};
