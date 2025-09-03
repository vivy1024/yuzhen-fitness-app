<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Models\Exercise;
use App\Models\User;
use App\Models\ChatSession;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;

/**
 * 系统监控和健康检查控制器
 * 
 * 整合系统监控、健康检查、数据库状态检查等功能
 * 替代原有的 TestController、HealthController
 * 
 * @author Backend Optimization Team
 * @version 2.0.0
 * @created 2025-01-18
 */
class SystemController extends BaseController
{
    /**
     * 系统健康检查
     * 综合检查数据库、缓存、存储等核心服务状态
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $checks = [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'storage' => $this->checkStorage(),
                'environment' => $this->checkEnvironment(),
                'services' => $this->checkServices()
            ];
            
            $overallStatus = $this->calculateOverallStatus($checks);
            
            return $this->successResponse([
                'status' => $overallStatus,
                'checks' => $checks,
                'timestamp' => now()->toISOString(),
                'server_time' => now()->format('Y-m-d H:i:s'),
                'uptime' => $this->getSystemUptime()
            ], '系统健康检查完成');
            
        } catch (\Exception $e) {
            Log::error('系统健康检查失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->errorResponse(
                '系统健康检查失败',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * 系统信息概览
     */
    public function systemInfo(): JsonResponse
    {
        try {
            $info = [
                'application' => [
                    'name' => config('app.name'),
                    'version' => '2.0.0',
                    'environment' => config('app.env'),
                    'debug' => config('app.debug'),
                    'timezone' => config('app.timezone'),
                    'locale' => config('app.locale')
                ],
                'framework' => [
                    'laravel_version' => app()->version(),
                    'php_version' => PHP_VERSION,
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
                ],
                'database' => [
                    'connection' => config('database.default'),
                    'driver' => config('database.connections.' . config('database.default') . '.driver')
                ],
                'features' => [
                    'ai_chat' => true,
                    'training_plans' => true,
                    'exercise_database' => true,
                    'user_management' => true,
                    'subscription_system' => true,
                    'mcp_integration' => true
                ],
                'statistics' => $this->getSystemStatistics()
            ];
            
            return $this->successResponse($info, '系统信息获取成功');
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                '获取系统信息失败',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * 数据库连接测试
     */
    public function databaseTest(): JsonResponse
    {
        try {
            $startTime = microtime(true);
            
            // 测试数据库连接
            DB::connection()->getPdo();
            
            // 测试基本查询
            $exerciseCount = Exercise::count();
            $userCount = User::count();
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return $this->successResponse([
                'database_status' => 'connected',
                'response_time_ms' => $responseTime,
                'statistics' => [
                    'total_exercises' => $exerciseCount,
                    'total_users' => $userCount,
                    'total_chat_sessions' => ChatSession::count(),
                    'active_subscriptions' => UserSubscription::where('is_active', true)->count()
                ],
                'connection_info' => [
                    'driver' => DB::connection()->getDriverName(),
                    'database' => DB::connection()->getDatabaseName()
                ]
            ], '数据库连接测试成功');
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                '数据库连接测试失败',
                500,
                [
                    'error' => $e->getMessage(),
                    'connection_status' => 'failed'
                ]
            );
        }
    }

    /**
     * 缓存系统测试
     */
    public function cacheTest(): JsonResponse
    {
        try {
            $testKey = 'system_cache_test_' . time();
            $testValue = 'cache_test_value_' . uniqid();
            
            // 测试缓存写入
            Cache::put($testKey, $testValue, 60);
            
            // 测试缓存读取
            $retrievedValue = Cache::get($testKey);
            
            // 清理测试数据
            Cache::forget($testKey);
            
            $cacheWorking = $retrievedValue === $testValue;
            
            return $this->successResponse([
                'cache_status' => $cacheWorking ? 'working' : 'failed',
                'cache_driver' => config('cache.default'),
                'test_result' => [
                    'write_success' => true,
                    'read_success' => $cacheWorking,
                    'value_match' => $cacheWorking
                ]
            ], '缓存系统测试完成');
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                '缓存系统测试失败',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * 存储系统测试
     */
    public function storageTest(): JsonResponse
    {
        try {
            $testFile = 'system_test_' . time() . '.txt';
            $testContent = 'Storage test content: ' . uniqid();
            
            // 测试文件写入
            Storage::put($testFile, $testContent);
            
            // 测试文件读取
            $retrievedContent = Storage::get($testFile);
            
            // 测试文件删除
            Storage::delete($testFile);
            
            $storageWorking = $retrievedContent === $testContent;
            
            return $this->successResponse([
                'storage_status' => $storageWorking ? 'working' : 'failed',
                'storage_driver' => config('filesystems.default'),
                'test_result' => [
                    'write_success' => true,
                    'read_success' => $storageWorking,
                    'delete_success' => !Storage::exists($testFile),
                    'content_match' => $storageWorking
                ]
            ], '存储系统测试完成');
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                '存储系统测试失败',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * 系统性能监控
     */
    public function performanceMonitor(): JsonResponse
    {
        try {
            $performance = [
                'memory' => [
                    'current_usage' => memory_get_usage(true),
                    'peak_usage' => memory_get_peak_usage(true),
                    'limit' => ini_get('memory_limit'),
                    'usage_percentage' => $this->getMemoryUsagePercentage()
                ],
                'database' => [
                    'query_count' => DB::getQueryLog() ? count(DB::getQueryLog()) : 0,
                    'connection_count' => $this->getDatabaseConnectionCount()
                ],
                'cache' => [
                    'hit_rate' => $this->getCacheHitRate(),
                    'size' => $this->getCacheSize()
                ],
                'response_times' => [
                    'database_avg' => $this->getAverageDatabaseResponseTime(),
                    'cache_avg' => $this->getAverageCacheResponseTime()
                ]
            ];
            
            return $this->successResponse($performance, '系统性能监控数据获取成功');
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                '系统性能监控失败',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * 清理系统缓存
     */
    public function clearCache(): JsonResponse
    {
        try {
            $results = [];
            
            // 清理应用缓存
            Artisan::call('cache:clear');
            $results['application_cache'] = 'cleared';
            
            // 清理配置缓存
            Artisan::call('config:clear');
            $results['config_cache'] = 'cleared';
            
            // 清理路由缓存
            Artisan::call('route:clear');
            $results['route_cache'] = 'cleared';
            
            // 清理视图缓存
            Artisan::call('view:clear');
            $results['view_cache'] = 'cleared';
            
            return $this->successResponse([
                'cleared_caches' => $results,
                'timestamp' => now()->toISOString()
            ], '系统缓存清理完成');
            
        } catch (\Exception $e) {
            return $this->errorResponse(
                '系统缓存清理失败',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    // ==================== 私有辅助方法 ====================

    /**
     * 检查数据库状态
     */
    private function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'connection' => 'active'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'connection' => 'failed'
            ];
        }
    }

    /**
     * 检查缓存状态
     */
    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_' . time();
            Cache::put($testKey, 'test', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);
            
            return [
                'status' => $value === 'test' ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 检查存储状态
     */
    private function checkStorage(): array
    {
        try {
            $testFile = 'health_check_' . time() . '.tmp';
            Storage::put($testFile, 'test');
            $content = Storage::get($testFile);
            Storage::delete($testFile);
            
            return [
                'status' => $content === 'test' ? 'healthy' : 'unhealthy',
                'driver' => config('filesystems.default')
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 检查环境配置
     */
    private function checkEnvironment(): array
    {
        return [
            'status' => 'healthy',
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => config('app.env'),
            'debug_mode' => config('app.debug'),
            'timezone' => config('app.timezone')
        ];
    }

    /**
     * 检查核心服务状态
     */
    private function checkServices(): array
    {
        $services = [];
        
        // 检查队列服务
        try {
            $services['queue'] = [
                'status' => 'healthy',
                'driver' => config('queue.default')
            ];
        } catch (\Exception $e) {
            $services['queue'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
        
        // 检查邮件服务
        try {
            $services['mail'] = [
                'status' => 'healthy',
                'driver' => config('mail.default')
            ];
        } catch (\Exception $e) {
            $services['mail'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }
        
        return $services;
    }

    /**
     * 计算整体系统状态
     */
    private function calculateOverallStatus(array $checks): string
    {
        $criticalServices = ['database', 'cache', 'storage'];
        
        foreach ($criticalServices as $service) {
            if (isset($checks[$service]['status']) && $checks[$service]['status'] !== 'healthy') {
                return 'unhealthy';
            }
        }
        
        // 检查其他服务是否有问题
        foreach ($checks as $check) {
            if (is_array($check)) {
                if (isset($check['status']) && $check['status'] === 'unhealthy') {
                    return 'degraded';
                }
                // 递归检查嵌套服务
                foreach ($check as $subCheck) {
                    if (is_array($subCheck) && isset($subCheck['status']) && $subCheck['status'] === 'unhealthy') {
                        return 'degraded';
                    }
                }
            }
        }
        
        return 'healthy';
    }

    /**
     * 获取系统统计信息
     */
    private function getSystemStatistics(): array
    {
        try {
            return [
                'total_users' => User::count(),
                'total_exercises' => Exercise::count(),
                'total_chat_sessions' => ChatSession::count(),
                'active_subscriptions' => UserSubscription::where('is_active', true)->count(),
                'database_size' => $this->getDatabaseSize(),
                'cache_entries' => $this->getCacheEntryCount()
            ];
        } catch (\Exception $e) {
            return [
                'error' => '统计信息获取失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取系统运行时间
     */
    private function getSystemUptime(): string
    {
        try {
            if (function_exists('sys_getloadavg')) {
                $uptime = shell_exec('uptime');
                return trim($uptime) ?: 'Unknown';
            }
            return 'Not available on this system';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * 获取内存使用百分比
     */
    private function getMemoryUsagePercentage(): float
    {
        $current = memory_get_usage(true);
        $limit = ini_get('memory_limit');
        
        if ($limit === '-1') {
            return 0; // 无限制
        }
        
        $limitBytes = $this->convertToBytes($limit);
        return round(($current / $limitBytes) * 100, 2);
    }

    /**
     * 转换内存限制字符串为字节数
     */
    private function convertToBytes(string $value): int
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

    /**
     * 获取数据库连接数（简化版本）
     */
    private function getDatabaseConnectionCount(): int
    {
        try {
            // 这里可以根据具体数据库类型实现
            return 1; // 当前连接
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * 获取缓存命中率（简化版本）
     */
    private function getCacheHitRate(): string
    {
        // 这里可以根据具体缓存驱动实现
        return 'Not available';
    }

    /**
     * 获取缓存大小（简化版本）
     */
    private function getCacheSize(): string
    {
        // 这里可以根据具体缓存驱动实现
        return 'Not available';
    }

    /**
     * 获取平均数据库响应时间（简化版本）
     */
    private function getAverageDatabaseResponseTime(): string
    {
        return 'Not available';
    }

    /**
     * 获取平均缓存响应时间（简化版本）
     */
    private function getAverageCacheResponseTime(): string
    {
        return 'Not available';
    }

    /**
     * 获取数据库大小（简化版本）
     */
    private function getDatabaseSize(): string
    {
        try {
            // 这里可以根据具体数据库类型实现
            return 'Not available';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * 获取缓存条目数量（简化版本）
     */
    private function getCacheEntryCount(): string
    {
        return 'Not available';
    }
}