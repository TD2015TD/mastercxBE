<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use App\Models\AuditLog;
use App\Services\MitelService;

class SystemController extends Controller
{
    /**
     * Get system health metrics
     */
    public function getSystemHealth()
    {
        try {
            // Get server metrics
            $cpuUsage = $this->getCpuUsage();
            $memoryUsage = $this->getMemoryUsage();
            $diskUsage = $this->getDiskUsage();
            $uptime = $this->getSystemUptime();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'cpu_usage' => $cpuUsage,
                    'memory_usage' => $memoryUsage,
                    'disk_usage' => $diskUsage,
                    'network_io' => $this->getNetworkIO(),
                    'uptime' => $uptime,
                    'last_updated' => now()->toIso8601String(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch system health',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get service status
     */
    public function getServiceStatus()
    {
        try {
            $dbConnected = $this->checkDatabaseConnection();
            $mitelConnected = $this->checkMitelConnection();
            $supersetConnected = $this->checkSupersetConnection();

            $services = [
                [
                    'name' => 'Database Server',
                    'status' => $dbConnected ? 'running' : 'stopped',
                    'health' => $dbConnected ? 'healthy' : 'failed',
                    'uptime' => $this->getSystemUptime(),
                    'port' => env('DB_PORT', '5432'),
                    'last_restart' => Cache::get('service_db_restart', now()->subDays(15)->toIso8601String()),
                ],
                [
                    'name' => 'Mitel Server',
                    'status' => $mitelConnected ? 'running' : 'stopped',
                    'health' => $mitelConnected ? 'healthy' : 'failed',
                    'uptime' => $this->getSystemUptime(),
                    'port' => env('MITEL_PORT', 'N/A'),
                    'last_restart' => Cache::get('service_mitel_restart', now()->subDays(15)->toIso8601String()),
                ],
                [
                    'name' => 'Reporting Server',
                    'status' => $supersetConnected ? 'running' : 'stopped',
                    'health' => $supersetConnected ? 'healthy' : 'failed',
                    'uptime' => $this->getSystemUptime(),
                    'port' => env('SUPERSET_PORT', '8088'),
                    'last_restart' => Cache::get('service_reporting_restart', now()->subDays(7)->toIso8601String()),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $services
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch service status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system alerts
     */
    public function getSystemAlerts(Request $request)
    {
        try {
            // Mock system alerts - in production, fetch from database
            $alerts = collect([
                [
                    'id' => 1,
                    'type' => 'warning',
                    'message' => 'Disk usage approaching threshold on primary server',
                    'severity' => 'medium',
                    'timestamp' => now()->subHours(2)->toIso8601String(),
                    'acknowledged' => false,
                    'module' => 'System Monitor',
                ],
                [
                    'id' => 2,
                    'type' => 'info',
                    'message' => 'Scheduled maintenance completed successfully',
                    'severity' => 'low',
                    'timestamp' => now()->subHours(8)->toIso8601String(),
                    'acknowledged' => true,
                    'module' => 'Maintenance',
                ],
            ]);

            // Filter by acknowledged status
            if ($request->has('acknowledged')) {
                $acknowledged = filter_var($request->acknowledged, FILTER_VALIDATE_BOOLEAN);
                $alerts = $alerts->where('acknowledged', $acknowledged);
            }

            // Filter by severity
            if ($request->has('severity')) {
                $alerts = $alerts->where('severity', $request->severity);
            }

            return response()->json([
                'success' => true,
                'data' => $alerts->values()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch system alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Acknowledge a system alert
     */
    public function acknowledgeAlert($id)
    {
        try {
            // In production, update the alert in database
            Log::info('System alert acknowledged', ['alert_id' => $id, 'user' => auth()->id()]);

            return response()->json([
                'success' => true,
                'message' => 'Alert acknowledged successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to acknowledge alert',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Perform system health check
     */
    public function performHealthCheck()
    {
        try {
            $checks = [
                'database' => $this->checkDatabaseConnection(),
                'cache' => $this->checkCacheConnection(),
                'storage' => $this->checkStorageWritable(),
                'queue' => true, // Check queue connection if applicable
            ];

            $allHealthy = !in_array(false, $checks, true);

            return response()->json([
                'success' => true,
                'healthy' => $allHealthy,
                'checks' => $checks,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Health check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restart a service
     */
    public function restartService($serviceName)
    {
        try {
            Log::info('Service restart requested', [
                'service' => $serviceName,
                'user' => auth()->id()
            ]);

            // In production, implement actual service restart logic
            // For now, just update the restart timestamp
            Cache::put("service_{$serviceName}_restart", now()->toIso8601String(), now()->addDays(30));

            // Clear application cache
            if ($serviceName === 'cache') {
                Artisan::call('cache:clear');
            }

            return response()->json([
                'success' => true,
                'message' => "Service {$serviceName} restart initiated"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restart service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test connectivity for a specific service
     */
    public function testConnectivity($service)
    {
        $startTime = microtime(true);

        switch ($service) {
            case 'database':
                $connected = $this->checkDatabaseConnection();
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                return response()->json([
                    'success' => true,
                    'service' => 'Database',
                    'connected' => $connected,
                    'response_time_ms' => $responseTime,
                    'host' => env('DB_HOST'),
                    'port' => (string) env('DB_PORT', '5432'),
                    'database' => env('DB_DATABASE'),
                ]);

            case 'mitel':
                // Use MitelService to test authentication via OAuth2 client_credentials
                try {
                    $mitelService = app(MitelService::class);
                    $token = $mitelService->authenticate();
                    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                    return response()->json([
                        'success' => true,
                        'service' => 'Mitel',
                        'connected' => !empty($token),
                        'response_time_ms' => $responseTime,
                        'host' => config('services.mitel.base_url'),
                    ]);
                } catch (\Exception $e) {
                    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                    return response()->json([
                        'success' => true,
                        'service' => 'Mitel',
                        'connected' => false,
                        'response_time_ms' => $responseTime,
                        'host' => config('services.mitel.base_url'),
                        'message' => $e->getMessage(),
                    ]);
                }

            case 'superset':
                // Use the same login flow as SupersetController::getSupersetTokens()
                try {
                    $supersetUrl = rtrim(env('SUPERSET_URL', 'http://localhost:8088'), '/');
                    $loginResponse = Http::timeout(5)->post("{$supersetUrl}/api/v1/security/login", [
                        'username' => env('SUPERSET_USERNAME', 'admin'),
                        'password' => env('SUPERSET_PASSWORD', 'admin'),
                        'provider' => 'db',
                        'refresh' => true,
                    ]);
                    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                    $connected = $loginResponse->successful() && !empty($loginResponse->json()['access_token']);
                    return response()->json([
                        'success' => true,
                        'service' => 'Superset',
                        'connected' => $connected,
                        'response_time_ms' => $responseTime,
                        'host' => $supersetUrl,
                        'message' => $connected ? null : 'Login failed: ' . $loginResponse->body(),
                    ]);
                } catch (\Exception $e) {
                    $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                    return response()->json([
                        'success' => true,
                        'service' => 'Superset',
                        'connected' => false,
                        'response_time_ms' => $responseTime,
                        'host' => rtrim(env('SUPERSET_URL', 'http://localhost:8088'), '/'),
                        'message' => $e->getMessage(),
                    ]);
                }

            default:
                return response()->json(['success' => false, 'message' => 'Unknown service'], 400);
        }
    }

    /**
     * Private helper methods
     */

    private function getCpuUsage()
    {
        // Simple CPU usage check (Linux/Unix)
        if (stristr(PHP_OS, 'win')) {
            return rand(30, 70); // Mock data for Windows
        }

        try {
            $load = sys_getloadavg();
            return round(($load[0] / 4) * 100, 2); // Assuming 4 cores
        } catch (\Exception $e) {
            return rand(30, 70); // Fallback to mock data
        }
    }

    private function getMemoryUsage()
    {
        try {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
            if (!empty($total[1]) && !empty($available[1])) {
                $used = (int)$total[1] - (int)$available[1];
                return round(($used / (int)$total[1]) * 100, 2);
            }
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getDiskUsage()
    {
        try {
            $diskTotal = disk_total_space('/');
            $diskFree = disk_free_space('/');
            $diskUsed = $diskTotal - $diskFree;
            return round(($diskUsed / $diskTotal) * 100, 2);
        } catch (\Exception $e) {
            return rand(60, 85); // Fallback to mock data
        }
    }

    private function getSystemUptime()
    {
        try {
            $secs    = (int) explode(' ', file_get_contents('/proc/uptime'))[0];
            $days    = (int) floor($secs / 86400);
            $hours   = (int) floor(($secs % 86400) / 3600);
            $minutes = (int) floor(($secs % 3600) / 60);
            return "{$days}d {$hours}h {$minutes}m";
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    private function getNetworkIO()
    {
        try {
            $netDev = file_get_contents('/proc/net/dev');
            $totalBytes = 0;

            foreach (explode("\n", $netDev) as $line) {
                $line = trim($line);
                if (!$line || str_starts_with($line, 'Inter') || str_starts_with($line, 'face')) continue;
                $parts = preg_split('/\s+/', $line);
                $iface = rtrim($parts[0] ?? '', ':');
                if ($iface === 'lo' || $iface === '') continue;
                $totalBytes += (int)($parts[1] ?? 0) + (int)($parts[9] ?? 0);
            }

            $now  = microtime(true);
            $prev = Cache::get('network_io_prev');
            Cache::put('network_io_prev', ['bytes' => $totalBytes, 'time' => $now], 120);

            if ($prev && isset($prev['bytes'], $prev['time'])) {
                $timeDiff  = $now - $prev['time'];
                $bytesDiff = $totalBytes - $prev['bytes'];
                if ($timeDiff > 0 && $bytesDiff >= 0) {
                    return round($bytesDiff * 8 / $timeDiff / 1_000_000, 2); // Mbps
                }
            }

            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function checkDatabaseConnection()
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkMitelConnection()
    {
        try {
            $mitelService = app(MitelService::class);
            $token = $mitelService->authenticate();
            return !empty($token);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkSupersetConnection()
    {
        try {
            $supersetUrl = rtrim(env('SUPERSET_URL', 'http://localhost:8088'), '/');
            $response = Http::timeout(5)->post("{$supersetUrl}/api/v1/security/login", [
                'username' => env('SUPERSET_USERNAME', 'admin'),
                'password' => env('SUPERSET_PASSWORD', 'admin'),
                'provider' => 'db',
                'refresh' => true,
            ]);
            return $response->successful() && !empty($response->json()['access_token']);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkCacheConnection()
    {
        try {
            Cache::has('health_check');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkStorageWritable()
    {
        try {
            return is_writable(storage_path());
        } catch (\Exception $e) {
            return false;
        }
    }

    private function convertToBytes($value)
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    // ─── Audit Logs ───────────────────────────────────────────────────────────

    public function getAuditLogs(Request $request)
    {
        try {
            $query = AuditLog::query()->orderBy('created_at', 'desc');

            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('action', 'ilike', "%{$search}%")
                      ->orWhere('details', 'ilike', "%{$search}%")
                      ->orWhere('user_name', 'ilike', "%{$search}%");
                });
            }

            if ($category = $request->get('category')) {
                $query->where('category', $category);
            }

            if ($userId = $request->get('user_id')) {
                $query->where('user_id', $userId);
            }

            if ($severity = $request->get('severity')) {
                $query->where('severity', $severity);
            }

            if ($dateFrom = $request->get('date_from')) {
                $query->whereDate('created_at', '>=', $dateFrom);
            }

            if ($dateTo = $request->get('date_to')) {
                $query->whereDate('created_at', '<=', $dateTo);
            }

            $perPage = min((int) $request->get('per_page', 20), 100);
            $logs    = $query->paginate($perPage);

            // Summary counts (respecting same filters except pagination)
            $baseQuery = AuditLog::query();
            if ($dateFrom) $baseQuery->whereDate('created_at', '>=', $dateFrom);
            if ($dateTo)   $baseQuery->whereDate('created_at', '<=', $dateTo);

            $summary = [
                'info'     => (clone $baseQuery)->where('severity', 'info')->count(),
                'warning'  => (clone $baseQuery)->where('severity', 'warning')->count(),
                'critical' => (clone $baseQuery)->where('severity', 'critical')->count(),
            ];

            return response()->json([
                'success' => true,
                'data'    => $logs->items(),
                'meta'    => [
                    'current_page' => $logs->currentPage(),
                    'last_page'    => $logs->lastPage(),
                    'per_page'     => $logs->perPage(),
                    'total'        => $logs->total(),
                ],
                'summary' => $summary,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function exportAuditLogs(Request $request)
    {
        try {
            $query = AuditLog::query()->orderBy('created_at', 'desc');

            if ($search   = $request->get('search'))    $query->where('action', 'ilike', "%{$search}%");
            if ($category = $request->get('category'))  $query->where('category', $category);
            if ($userId   = $request->get('user_id'))   $query->where('user_id', $userId);
            if ($severity = $request->get('severity'))  $query->where('severity', $severity);
            if ($dateFrom = $request->get('date_from')) $query->whereDate('created_at', '>=', $dateFrom);
            if ($dateTo   = $request->get('date_to'))   $query->whereDate('created_at', '<=', $dateTo);

            $logs = $query->limit(10000)->get();

            $csv  = "Timestamp,User,Role,Action,Category,Module,Details,Severity,IP Address\n";
            foreach ($logs as $log) {
                $csv .= implode(',', [
                    '"' . $log->created_at->format('Y-m-d H:i:s') . '"',
                    '"' . str_replace('"', '""', $log->user_name ?? '') . '"',
                    '"' . ($log->user_role ?? '') . '"',
                    '"' . str_replace('"', '""', $log->action) . '"',
                    '"' . $log->category . '"',
                    '"' . $log->module . '"',
                    '"' . str_replace('"', '""', $log->details) . '"',
                    '"' . $log->severity . '"',
                    '"' . ($log->ip_address ?? '') . '"',
                ]) . "\n";
            }

            return response($csv, 200, [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="audit-logs-' . now()->format('Y-m-d') . '.csv"',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
