<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * 性能监控中间件
 * 
 * 监控API请求的性能指标，包括：
 * - 响应时间
 * - 内存使用
 * - 数据库查询次数
 * - 缓存命中率
 * 
 * @author Augment Agent
 * @version 2.0.0
 * @created 2025-08-18 07:00:00
 */
class PerformanceMonitoringMiddleware
{
    // 性能阈值配置
    private const SLOW_REQUEST_THRESHOLD = 1000; // 1秒
    private const HIGH_MEMORY_THRESHOLD = 50 * 1024 * 1024; // 50MB
    private const HIGH_QUERY_COUNT_THRESHOLD = 20; // 20个查询
    
    // 统计数据缓存键
    private const STATS_CACHE_KEY = 'performance_stats';
    private const STATS_TTL = 3600; // 1小时

    /**
     * 处理传入的请求
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 记录开始时间和内存
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        $startQueries = $this->getQueryCount();
        
        // 生成请求ID
        $requestId = uniqid('req_');
        $request->headers->set('X-Request-ID', $requestId);
        
        // 执行请求
        $response = $next($request);
        
        // 计算性能指标
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        $endQueries = $this->getQueryCount();
        
        $metrics = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName() ?? 'unknown',
            'status_code' => $response->getStatusCode(),
            'execution_time' => round(($endTime - $startTime) * 1000, 2), // 毫秒
            'memory_usage' => $endMemory - $startMemory,
            'peak_memory' => memory_get_peak_usage(),
            'query_count' => $endQueries - $startQueries,
            'response_size' => strlen($response->getContent()),
            'timestamp' => now()->toISOString(),
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ];
        
        // 记录性能日志
        $this->logPerformanceMetrics($metrics);
        
        // 更新统计数据
        $this->updatePerformanceStats($metrics);
        
        // 检查性能阈值
        $this->checkPerformanceThresholds($metrics);
        
        // 添加性能头信息
        $this->addPerformanceHeaders($response, $metrics);
        
        return $response;
    }

    /**
     * 记录性能指标日志
     */
    private function logPerformanceMetrics(array $metrics): void
    {
        $logLevel = 'info';
        
        // 根据性能指标调整日志级别
        if ($metrics['execution_time'] > self::SLOW_REQUEST_THRESHOLD) {
            $logLevel = 'warning';
        }
        
        if ($metrics['memory_usage'] > self::HIGH_MEMORY_THRESHOLD) {
            $logLevel = 'warning';
        }
        
        if ($metrics['query_count'] > self::HIGH_QUERY_COUNT_THRESHOLD) {
            $logLevel = 'warning';
        }
        
        Log::$logLevel('API性能监控', $metrics);
    }

    /**
     * 更新性能统计数据
     */
    private function updatePerformanceStats(array $metrics): void
    {
        try {
            $stats = Cache::get(self::STATS_CACHE_KEY, [
                'total_requests' => 0,
                'total_execution_time' => 0,
                'total_memory_usage' => 0,
                'total_queries' => 0,
                'slow_requests' => 0,
                'error_requests' => 0,
                'routes' => [],
                'hourly_stats' => [],
                'last_updated' => now()->toISOString()
            ]);
            
            // 更新总体统计
            $stats['total_requests']++;
            $stats['total_execution_time'] += $metrics['execution_time'];
            $stats['total_memory_usage'] += $metrics['memory_usage'];
            $stats['total_queries'] += $metrics['query_count'];
            
            // 慢请求统计
            if ($metrics['execution_time'] > self::SLOW_REQUEST_THRESHOLD) {
                $stats['slow_requests']++;
            }
            
            // 错误请求统计
            if ($metrics['status_code'] >= 400) {
                $stats['error_requests']++;
            }
            
            // 路由统计
            $route = $metrics['route'];
            if (!isset($stats['routes'][$route])) {
                $stats['routes'][$route] = [
                    'count' => 0,
                    'total_time' => 0,
                    'avg_time' => 0,
                    'max_time' => 0,
                    'min_time' => PHP_FLOAT_MAX
                ];
            }
            
            $routeStats = &$stats['routes'][$route];
            $routeStats['count']++;
            $routeStats['total_time'] += $metrics['execution_time'];
            $routeStats['avg_time'] = $routeStats['total_time'] / $routeStats['count'];
            $routeStats['max_time'] = max($routeStats['max_time'], $metrics['execution_time']);
            $routeStats['min_time'] = min($routeStats['min_time'], $metrics['execution_time']);
            
            // 小时统计
            $hour = now()->format('Y-m-d H:00:00');
            if (!isset($stats['hourly_stats'][$hour])) {
                $stats['hourly_stats'][$hour] = [
                    'requests' => 0,
                    'avg_response_time' => 0,
                    'total_response_time' => 0
                ];
            }
            
            $hourlyStats = &$stats['hourly_stats'][$hour];
            $hourlyStats['requests']++;
            $hourlyStats['total_response_time'] += $metrics['execution_time'];
            $hourlyStats['avg_response_time'] = $hourlyStats['total_response_time'] / $hourlyStats['requests'];
            
            // 只保留最近24小时的数据
            $cutoffTime = now()->subHours(24)->format('Y-m-d H:00:00');
            $stats['hourly_stats'] = array_filter(
                $stats['hourly_stats'],
                fn($hour) => $hour >= $cutoffTime,
                ARRAY_FILTER_USE_KEY
            );
            
            $stats['last_updated'] = now()->toISOString();
            
            // 缓存统计数据
            Cache::put(self::STATS_CACHE_KEY, $stats, self::STATS_TTL);
            
        } catch (\Exception $e) {
            Log::error('更新性能统计失败', [
                'error' => $e->getMessage(),
                'metrics' => $metrics
            ]);
        }
    }

    /**
     * 检查性能阈值并发送告警
     */
    private function checkPerformanceThresholds(array $metrics): void
    {
        $alerts = [];
        
        // 检查响应时间
        if ($metrics['execution_time'] > self::SLOW_REQUEST_THRESHOLD) {
            $alerts[] = [
                'type' => 'slow_request',
                'message' => "慢请求告警: {$metrics['url']} 响应时间 {$metrics['execution_time']}ms",
                'severity' => 'warning'
            ];
        }
        
        // 检查内存使用
        if ($metrics['memory_usage'] > self::HIGH_MEMORY_THRESHOLD) {
            $alerts[] = [
                'type' => 'high_memory',
                'message' => "高内存使用告警: {$metrics['url']} 内存使用 " . round($metrics['memory_usage'] / 1024 / 1024, 2) . "MB",
                'severity' => 'warning'
            ];
        }
        
        // 检查查询次数
        if ($metrics['query_count'] > self::HIGH_QUERY_COUNT_THRESHOLD) {
            $alerts[] = [
                'type' => 'high_query_count',
                'message' => "高查询次数告警: {$metrics['url']} 查询次数 {$metrics['query_count']}",
                'severity' => 'warning'
            ];
        }
        
        // 发送告警
        foreach ($alerts as $alert) {
            $this->sendAlert($alert, $metrics);
        }
    }

    /**
     * 发送性能告警
     */
    private function sendAlert(array $alert, array $metrics): void
    {
        Log::warning('性能告警', [
            'alert' => $alert,
            'metrics' => $metrics
        ]);
        
        // 这里可以集成其他告警渠道，如：
        // - 邮件通知
        // - Slack通知
        // - 短信告警
        // - 第三方监控服务
    }

    /**
     * 添加性能响应头
     */
    private function addPerformanceHeaders(Response $response, array $metrics): void
    {
        $response->headers->set('X-Request-ID', $metrics['request_id']);
        $response->headers->set('X-Response-Time', $metrics['execution_time'] . 'ms');
        $response->headers->set('X-Memory-Usage', round($metrics['memory_usage'] / 1024, 2) . 'KB');
        $response->headers->set('X-Query-Count', $metrics['query_count']);
        
        // 在开发环境添加更多调试信息
        if (app()->environment('local', 'development')) {
            $response->headers->set('X-Peak-Memory', round($metrics['peak_memory'] / 1024 / 1024, 2) . 'MB');
            $response->headers->set('X-Response-Size', round($metrics['response_size'] / 1024, 2) . 'KB');
        }
    }

    /**
     * 获取数据库查询次数
     */
    private function getQueryCount(): int
    {
        return count(\DB::getQueryLog());
    }

    /**
     * 获取性能统计数据
     */
    public static function getPerformanceStats(): array
    {
        return Cache::get(self::STATS_CACHE_KEY, []);
    }

    /**
     * 清除性能统计数据
     */
    public static function clearPerformanceStats(): bool
    {
        return Cache::forget(self::STATS_CACHE_KEY);
    }

    /**
     * 获取性能报告
     */
    public static function getPerformanceReport(): array
    {
        $stats = self::getPerformanceStats();
        
        if (empty($stats) || $stats['total_requests'] === 0) {
            return [
                'message' => '暂无性能数据',
                'stats' => []
            ];
        }
        
        return [
            'summary' => [
                'total_requests' => $stats['total_requests'],
                'avg_response_time' => round($stats['total_execution_time'] / $stats['total_requests'], 2),
                'avg_memory_usage' => round($stats['total_memory_usage'] / $stats['total_requests'] / 1024, 2), // KB
                'avg_query_count' => round($stats['total_queries'] / $stats['total_requests'], 2),
                'slow_request_rate' => round(($stats['slow_requests'] / $stats['total_requests']) * 100, 2),
                'error_rate' => round(($stats['error_requests'] / $stats['total_requests']) * 100, 2)
            ],
            'routes' => array_slice(
                array_map(function ($route, $data) {
                    return array_merge(['route' => $route], $data);
                }, array_keys($stats['routes']), $stats['routes']),
                0,
                10 // 只返回前10个路由
            ),
            'hourly_trends' => $stats['hourly_stats'],
            'last_updated' => $stats['last_updated']
        ];
    }
}
