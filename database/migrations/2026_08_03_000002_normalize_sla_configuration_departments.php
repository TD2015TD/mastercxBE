<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tickets store the department lookup *code* ("SALES TAX"), but the SLA Management screen
     * used to save the display *name* ("Sales Tax"). The two never matched, so no ticket ever
     * picked up an SLA policy. Rewrite existing rows to the code form.
     */
    public function up(): void
    {
        $lookups = DB::table('lookups')
            ->where('type', 'department')
            ->whereNull('deleted_at')
            ->get(['code', 'name_en', 'name_ms']);

        foreach (DB::table('sla_configurations')->get(['id', 'department']) as $config) {
            $code = $this->resolveCode($lookups, $config->department);

            if ($code !== null && $code !== $config->department) {
                DB::table('sla_configurations')
                    ->where('id', $config->id)
                    ->update(['department' => $code]);
            }
        }
    }

    /**
     * Not reversible — the previous value cannot be distinguished from a legitimately
     * code-shaped department, and the code form is the correct one either way.
     */
    public function down(): void
    {
        //
    }

    private function resolveCode($lookups, ?string $department): ?string
    {
        if ($department === null || trim($department) === '') {
            return null;
        }

        $needle = trim($department);

        foreach ($lookups as $lookup) {
            foreach ([$lookup->code, $lookup->name_en, $lookup->name_ms] as $candidate) {
                if ($candidate !== null && strcasecmp(trim($candidate), $needle) === 0) {
                    return $lookup->code;
                }
            }
        }

        return null;
    }
};
