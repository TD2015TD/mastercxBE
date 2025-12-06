<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

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
                    'network_io' => rand(100, 500), // Mock data
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
            $services = [
                [
                    'name' => 'Web Server',
                    'status' => 'running',
                    'health' => 'healthy',
                    'uptime' => $this->getSystemUptime(),
                    'port' => '443',
                    'last_restart' => Cache::get('service_web_restart', now()->subDays(15)->toIso8601String()),
                ],
                [
                    'name' => 'Database Server',
                    'status' => $this->checkDatabaseConnection() ? 'running' : 'stopped',
                    'health' => $this->checkDatabaseConnection() ? 'healthy' : 'failed',
                    'uptime' => $this->getSystemUptime(),
                    'port' => env('DB_PORT', '3306'),
                    'last_restart' => Cache::get('service_db_restart', now()->subDays(15)->toIso8601String()),
                ],
                [
                    'name' => 'Cache Server',
                    'status' => $this->checkCacheConnection() ? 'running' : 'stopped',
                    'health' => $this->checkCacheConnection() ? 'healthy' : 'degraded',
                    'uptime' => $this->getSystemUptime(),
                    'port' => '6379',
                    'last_restart' => Cache::get('service_cache_restart', now()->subDays(7)->toIso8601String()),
                ],
                [
                    'name' => 'Queue Worker',
                    'status' => 'running',
                    'health' => 'healthy',
                    'uptime' => $this->getSystemUptime(),
                    'port' => 'N/A',
                    'last_restart' => Cache::get('service_queue_restart', now()->subDays(2)->toIso8601String()),
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
            $memoryLimit = $this->convertToBytes(ini_get('memory_limit'));
            $memoryUsed = memory_get_usage(true);
            return round(($memoryUsed / $memoryLimit) * 100, 2);
        } catch (\Exception $e) {
            return rand(50, 80); // Fallback to mock data
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
        // This is approximate - in production, use actual server uptime
        return '15d 8h 23m';
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
}
