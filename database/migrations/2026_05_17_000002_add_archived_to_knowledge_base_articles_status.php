<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drop the existing check constraint and replace with one that includes 'archived'
        DB::statement("ALTER TABLE knowledge_base_articles DROP CONSTRAINT IF EXISTS knowledge_base_articles_status_check");
        DB::statement("ALTER TABLE knowledge_base_articles ADD CONSTRAINT knowledge_base_articles_status_check CHECK (status IN ('draft', 'published', 'archived'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE knowledge_base_articles DROP CONSTRAINT IF EXISTS knowledge_base_articles_status_check");
        DB::statement("ALTER TABLE knowledge_base_articles ADD CONSTRAINT knowledge_base_articles_status_check CHECK (status IN ('draft', 'published'))");
    }
};
