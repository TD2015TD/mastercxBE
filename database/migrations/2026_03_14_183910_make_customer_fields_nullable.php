<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Raw SQL to avoid "cannot alter column used by a view" error in PostgreSQL
        DB::statement('ALTER TABLE customers ALTER COLUMN id_type DROP NOT NULL');
        DB::statement('ALTER TABLE customers ALTER COLUMN id_no DROP NOT NULL');
        DB::statement('ALTER TABLE customers ALTER COLUMN email DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE customers ALTER COLUMN id_type SET NOT NULL');
        DB::statement('ALTER TABLE customers ALTER COLUMN id_no SET NOT NULL');
        DB::statement('ALTER TABLE customers ALTER COLUMN email SET NOT NULL');
    }
};
