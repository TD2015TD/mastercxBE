<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public static function log(
        string $action,
        string $category,
        string $module,
        string $details,
        string $severity = 'info',
        ?array $metadata = null,
        ?Request $request = null
    ): AuditLog {
        $user     = Auth::user();
        $ip       = $request ? $request->ip() : request()->ip();

        return AuditLog::create([
            'user_id'   => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'user_role' => $user?->role?->slug ?? 'system',
            'action'    => $action,
            'category'  => $category,
            'module'    => $module,
            'details'   => $details,
            'severity'  => $severity,
            'ip_address' => $ip,
            'metadata'  => $metadata,
        ]);
    }

    public static function info(string $action, string $category, string $module, string $details, ?array $metadata = null): AuditLog
    {
        return self::log($action, $category, $module, $details, 'info', $metadata);
    }

    public static function warning(string $action, string $category, string $module, string $details, ?array $metadata = null): AuditLog
    {
        return self::log($action, $category, $module, $details, 'warning', $metadata);
    }

    public static function critical(string $action, string $category, string $module, string $details, ?array $metadata = null): AuditLog
    {
        return self::log($action, $category, $module, $details, 'critical', $metadata);
    }
}
