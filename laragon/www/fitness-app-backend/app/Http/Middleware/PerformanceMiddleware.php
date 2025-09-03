<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // 检查是否可以使用缓存响应
        if ($this->shouldUseCache($request)) {
            $cachedResponse = $this->getCachedResponse($request);
            if ($cachedResponse) {
                return $this->addPerformanceHeaders($cachedResponse, $startTime, $startMemory, true);
            }
        }

        // 处理请求
        $response = $next($request);

        // 缓存响应（如果适用）
        if ($this->shouldCacheResponse($request, $response)) {
            $this->cacheResponse($request, $response);
        }

        // 记录性能指标
        $this->recordPerformanceMetrics($request, $startTime, $startMemory);

        // 添加性能头信息
        return $this->addPerformanceHeaders($response, $startTime, $startMemory, false);
    }

    /**
     * 检查是否应该使用缓存
     */
    private function shouldUseCache(Request $request): bool
    {
        // 只对GET请求使用缓存
        if (!$request->isMethod('GET')) {
            return false;
        }

        // 排除需要实时数据的端点
        $excludePaths = [
            'api/user/stats',
            'api/ai/chat',
            'api/user/progress',
        ];

        foreach ($excludePaths as $path) {
            if (str_contains($request->path(), $path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取缓存的响应
     */
    private function getCachedResponse(Request $request): ?Response
    {
        $cacheKey = $this->generateCacheKey($request);
        $cached = Cache::get($cacheKey);

        if ($cached) {
            return response($cached['content'], $cached['status'])
                ->withHeaders($cached['headers']);
        }

        return null;
    }

    /**
     * 检查是否应该缓存响应
     */
    private function shouldCacheResponse(Request $request, Response $response): bool
    {
        // 只缓存成功的GET请求
        if (!$request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return false;
        }

        // 检查响应大小（不缓存过大的响应）
        $contentLength = strlen($response->getContent());
        if ($contentLength > 1024 * 1024) { // 1MB
            return false;
        }

        // 可缓存的路径
        $cacheablePaths = [
            'api/exercises',
            'api/training-plans/public',
            'api/nutrition-plans/public',
            'api/knowledge',
        ];

        foreach ($cacheablePaths as $path) {
            if (str_contains($request->path(), $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 缓存响应
     */
    private function cacheResponse(Request $request, Response $response): void
    {
        $cacheKey = $this->generateCacheKey($request);
        $ttl = $this->getCacheTTL($request);

        $cacheData = [
            'content' => $response->getContent(),
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'cached_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $cacheData, $ttl);
    }

    /**
     * 生成缓存键
     */
    private function generateCacheKey(Request $request): string
    {
        $key = 'api_response_' . md5($request->fullUrl());
        
        // 如果有用户认证，包含用户ID
        if ($user = $request->user()) {
            $key .= '_user_' . $user->id;
        }

        return $key;
    }

    /**
     * 获取缓存TTL
     */
    private function getCacheTTL(Request $request): int
    {
        $path = $request->path();

        // 根据路径设置不同的缓存时间
        if (str_contains($path, 'exercises')) {
            return 3600; // 1小时
        }

        if (str_contains($path, 'public')) {
            return 1800; // 30分钟
        }

        if (str_contains($path, 'knowledge')) {
            return 7200; // 2小时
        }

        return 900; // 默认15分钟
    }

    /**
     * 记录性能指标
     */
    private function recordPerformanceMetrics(Request $request, float $startTime, int $startMemory): void
    {
        $executionTime = (microtime(true) - $startTime) * 1000; // 毫秒
        $memoryUsage = memory_get_usage(true) - $startMemory;
        $peakMemory = memory_get_peak_usage(true);

        // 记录慢查询
        if ($executionTime > 1000) { // 超过1秒
            Log::warning('慢请求检测', [
                'path' => $request->path(),
                'method' => $request->method(),
                'execution_time' => $executionTime,
                'memory_usage' => $memoryUsage,
                'user_id' => $request->user()?->id,
            ]);
        }

        // 记录高内存使用
        if ($memoryUsage > 50 * 1024 * 1024) { // 超过50MB
            Log::warning('高内存使用检测', [
                'path' => $request->path(),
                'method' => $request->method(),
                'memory_usage' => $memoryUsage,
                'peak_memory' => $peakMemory,
                'user_id' => $request->user()?->id,
            ]);
        }

        // 更新性能统计
        $this->updatePerformanceStats($request->path(), $executionTime, $memoryUsage);
    }

    /**
     * 添加性能头信息
     */
    private function addPerformanceHeaders(Response $response, float $startTime, int $startMemory, bool $fromCache): Response
    {
        $executionTime = (microtime(true) - $startTime) * 1000;
        $memoryUsage = memory_get_usage(true) - $startMemory;

        $response->headers->add([
            'X-Response-Time' => round($executionTime, 2) . 'ms',
            'X-Memory-Usage' => $this->formatBytes($memoryUsage),
            'X-From-Cache' => $fromCache ? 'true' : 'false',
            'X-Server-Time' => now()->toISOString(),
        ]);

        return $response;
    }

    /**
     * 更新性能统计
     */
    private function updatePerformanceStats(string $path, float $executionTime, int $memoryUsage): void
    {
        $statsKey = 'performance_stats_' . md5($path);
        $stats = Cache::get($statsKey, [
            'count' => 0,
            'total_time' => 0,
            'total_memory' => 0,
            'max_time' => 0,
            'max_memory' => 0,
        ]);

        $stats['count']++;
        $stats['total_time'] += $executionTime;
        $stats['total_memory'] += $memoryUsage;
        $stats['max_time'] = max($stats['max_time'], $executionTime);
        $stats['max_memory'] = max($stats['max_memory'], $memoryUsage);

        Cache::put($statsKey, $stats, 3600);
    }

    /**
     * 格式化字节数
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 获取性能统计
     */
    public static function getPerformanceStats(): array
    {
        $allStats = [];
        $keys = Cache::get('performance_stats_keys', []);

        foreach ($keys as $key) {
            $stats = Cache::get($key);
            if ($stats) {
                $allStats[str_replace('performance_stats_', '', $key)] = [
                    'count' => $stats['count'],
                    'avg_time' => $stats['count'] > 0 ? $stats['total_time'] / $stats['count'] : 0,
                    'avg_memory' => $stats['count'] > 0 ? $stats['total_memory'] / $stats['count'] : 0,
                    'max_time' => $stats['max_time'],
                    'max_memory' => $stats['max_memory'],
                ];
            }
        }

        return $allStats;
    }

    /**
     * 清理性能统计
     */
    public static function clearPerformanceStats(): void
    {
        $keys = Cache::get('performance_stats_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget('performance_stats_keys');
    }
}
