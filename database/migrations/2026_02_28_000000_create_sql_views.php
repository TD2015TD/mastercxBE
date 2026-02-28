<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class CreateSqlViews extends Migration
{
    /**
     * Run the migrations.
     * Execute all .sql files in crm-reporting/sql-views.
     */
    public function up()
    {
        $path = base_path('crm-reporting/sql-views');

        if (! File::exists($path)) {
            return;
        }

        $files = File::files($path);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'sql') {
                continue;
            }

            $sql = File::get($file->getPathname());
            $sql = trim($sql);
            if ($sql === '') {
                continue;
            }

            // Some SQL files may contain DELIMITER statements or comments; execute as-is.
            DB::unprepared($sql);
        }
    }

    /**
     * Reverse the migrations.
     * Attempts to parse the view name from each SQL file and drop it.
     */
    public function down()
    {
        $path = base_path('crm-reporting/sql-views');

        if (! File::exists($path)) {
            return;
        }

        $files = File::files($path);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'sql') {
                continue;
            }

            $sql = File::get($file->getPathname());

            // Try to extract view name from CREATE VIEW statement
            if (preg_match('/CREATE\s+VIEW\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:`|\")?([\w\.]+)(?:`|\")?/i', $sql, $m)) {
                $view = $m[1];

                try {
                    DB::statement("DROP VIEW IF EXISTS {$view}");
                } catch (\Exception $e) {
                    // ignore errors during rollback
                }
            }
        }
    }
}
