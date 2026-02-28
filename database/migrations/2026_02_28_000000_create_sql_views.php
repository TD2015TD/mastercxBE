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

        // Sort files alphabetically so numbered views execute in the correct order
        usort($files, fn($a, $b) => strcmp($a->getFilename(), $b->getFilename()));

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
            try {
                DB::unprepared($sql);
            } catch (\Exception $e) {
                // Re-throw with filename context but without full SQL in message
                $shortMsg = explode("\n", $e->getMessage())[0];
                throw new \RuntimeException(
                    "SQL view migration failed on file [{$file->getFilename()}]: {$shortMsg}",
                    0,
                    $e
                );
            }
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
