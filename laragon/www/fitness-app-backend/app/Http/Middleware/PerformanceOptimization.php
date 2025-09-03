<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\AutoGenCacheService;
use App\Services\Performance\AutoGenPerformanceMonitor;
use Carbon\Carbon;

/**
 * 性能优化中间件
 * 监控API请求性能，自动应用优化策略
 */
class PerformanceOptimization
{
    protected AutoGenCacheService $cacheService;
    protected AutoGenPerformanceMonitor $performanceMonitor;
    
    // 性能阈值配置
    const SLOW_REQUEST_THRESHOLD = 1000; // 1秒
    const MEMORY_WARNING_THRESHOLD = 70; // 70%
    const CACHE_HIT_RATE_THRESHOLD = 80; // 80%
    const MAX_RESPONSE_SIZE = 1024 * 1024; // 1MB

    public function __construct(
        AutoGenCacheService $cacheService,
        AutoGenPerformanceMonitor $performanceMonitor
    ) {
        $this->cacheService = $cacheService;
        $this->performanceMonitor = $performanceMonitor;
    }

    /**
     * 处理传入的请求
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $requestId = $this->generateRequestId();
        
        // 记录请求开始
        $this->logRequestStart($request, $requestId);
        
        // 检查是否可以使用缓存响应
        if ($this->shouldUseCache($request)) {
            $cachedResponse = $this->getCachedResponse($request);
            if ($cachedResponse) {
                $this->logCacheHit($request, $requestId);
                return $cachedResponse;
            }
        }
        
        // 应用请求前优化
        $this->applyPreRequestOptimizations($request);
        
        // 执行请求
        $response = $next($request);
        
        // 计算性能指标
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);
        $memoryUsed = $endMemory - $startMemory;
        
        // 应用响应后优化
        $optimizedResponse = $this->applyPostRequestOptimizations(
            $request, 
            $response, 
            $responseTime, 
            $memoryUsed
        );
        
        // 记录性能指标
        $this->recordPerformanceMetrics($request, $responseTime, $memoryUsed, $requestId);
        
        // 缓存响应（如果适用）
        if ($this->shouldCacheResponse($request, $optimizedResponse)) {
            $this->cacheResponse($request, $optimizedResponse);
        }
        
        // 触发性能分析（异步）
        if ($this->shouldTriggerPerformanceAnalysis($responseTime)) {
            $this->triggerPerformanceAnalysis($request, $responseTime, $memoryUsed);
        }
        
        return $optimizedResponse;
    }

    /**
     * 生成请求ID
     */
    protected function generateRequestId(): string
    {
        return uniqid('req_', true);
    }

    /**
     * 记录请求开始
     */
    protected function logRequestStart(Request $request, string $requestId): void
    {
        Log::info('API请求开始', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * 检查是否应该使用缓存
     */
    protected function shouldUseCache(Request $request): bool
    {
        // GET请求且不包含敏感参数
        if ($request->method() !== 'GET') {
            return false;
        }
        
        // 排除实时数据接口
        $excludePaths = [
            '/api/autogen/sessions/*/messages/stream',
            '/api/websocket/*',
            '/api/autogen/status',
            '/api/performance/*'
        ];
        
        foreach ($excludePaths as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * 获取缓存的响应
     */
    protected function getCachedResponse(Request $request): ?Response
    {
        try {
            $cacheKey = $this->generateCacheKey($request);
            $cached = Cache::get($cacheKey);
            
            if ($cached) {
                return new Response(
                    $cached['content'],
                    $cached['status'],
                    array_merge($cached['headers'], [
                        'X-Cache-Status' => 'HIT',
                        'X-Cache-Key' => $cacheKey
                    ])
                );
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::warning('获取缓存响应失败', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl()
            ]);
            
            return null;
        }
    }

    /**
     * 生成缓存键
     */
    protected function generateCacheKey(Request $request): string
    {
        $key = 'api_response:' . md5(
            $request->fullUrl() . 
            serialize($request->query()) . 
            ($request->user()?->id ?? 'guest')
        );
        
        return $key;
    }

    /**
     * 记录缓存命中
     */
    protected function logCacheHit(Request $request, string $requestId): void
    {
        Log::info('缓存命中', [
            'request_id' => $requestId,
            'url' => $request->fullUrl(),
            'cache_key' => $this->generateCacheKey($request)
        ]);
    }

    /**
     * 应用请求前优化
     */
    protected function applyPreRequestOptimizations(Request $request): void
    {
        try {
            // 检查内存使用情况
            $memoryUsage = $this->getMemoryUsagePercent();
            if ($memoryUsage > self::MEMORY_WARNING_THRESHOLD) {
                // 触发内存清理
                $this->triggerMemoryCleanup();
            }
            
            // 预热相关缓存
            if ($this->shouldPrewarmCache($request)) {
                $this->prewarmCache($request);
            }
            
        } catch (\Exception $e) {
            Log::warning('请求前优化失败', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl()
            ]);
        }
    }

    /**
     * 应用响应后优化
     */
    protected function applyPostRequestOptimizations(
        Request $request,
        Response $response,
        float $responseTime,
        int $memoryUsed
    ): Response {
        try {
            // 压缩响应内容
            if ($this->shouldCompressResponse($response)) {
                $response = $this->compressResponse($response);
            }
            
            // 添加性能头信息
            $response->headers->set('X-Response-Time', $responseTime . 'ms');
            $response->headers->set('X-Memory-Used', $this->formatBytes($memoryUsed));
            $response->headers->set('X-Cache-Status', 'MISS');
            
            // 添加优化建议头
            if ($responseTime > self::SLOW_REQUEST_THRESHOLD) {
                $response->headers->set('X-Performance-Warning', 'Slow response detected');
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::warning('响应后优化失败', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl()
            ]);
            
            return $response;
        }
    }

    /**
     * 获取内存使用百分比
     */
    protected function getMemoryUsagePercent(): float
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        
        if ($memoryLimit <= 0) {
            return 0;
        }
        
        return round(($memoryUsage / $memoryLimit) * 100, 2);
    }

    /**
     * 获取内存限制
     */
    protected function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return PHP_INT_MAX;
        }
        
        return $this->convertToBytes($memoryLimit);
    }

    /**
     * 转换内存大小为字节
     */
    protected function convertToBytes(string $value): int
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
     * 触发内存清理
     */
    protected function triggerMemoryCleanup(): void
    {
        try {
            // 清理过期缓存
            $this->cacheService->cleanupExpiredCache();
            
            // 强制垃圾回收
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            Log::info('内存清理完成', [
                'memory_usage_after' => $this->getMemoryUsagePercent() . '%'
            ]);
            
        } catch (\Exception $e) {
            Log::error('内存清理失败', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 检查是否应该预热缓存
     */
    protected function shouldPrewarmCache(Request $request): bool
    {
        // 对于AutoGen相关的请求，预热相关缓存
        return $request->is('api/autogen/*');
    }

    /**
     * 预热缓存
     */
    protected function prewarmCache(Request $request): void
    {
        try {
            if ($request->is('api/autogen/sessions/*')) {
                // 预热会话相关缓存
                $sessionId = $request->route('session');
                if ($sessionId) {
                    $this->cacheService->preloadSessionData($sessionId);
                }
            }
            
        } catch (\Exception $e) {
            Log::warning('缓存预热失败', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl()
            ]);
        }
    }

    /**
     * 检查是否应该压缩响应
     */
    protected function shouldCompressResponse(Response $response): bool
    {
        $contentLength = strlen($response->getContent());
        $contentType = $response->headers->get('Content-Type', '');
        
        // 只压缩大于1KB的JSON响应
        return $contentLength > 1024 && 
               str_contains($contentType, 'application/json');
    }

    /**
     * 压缩响应内容
     */
    protected function compressResponse(Response $response): Response
    {
        try {
            $content = $response->getContent();
            
            if (function_exists('gzencode')) {
                $compressed = gzencode($content, 6);
                if ($compressed !== false && strlen($compressed) < strlen($content)) {
                    $response->setContent($compressed);
                    $response->headers->set('Content-Encoding', 'gzip');
                    $response->headers->set('Content-Length', strlen($compressed));
                }
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::warning('响应压缩失败', [
                'error' => $e->getMessage()
            ]);
            
            return $response;
        }
    }

    /**
     * 格式化字节数
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 记录性能指标
     */
    protected function recordPerformanceMetrics(
        Request $request,
        float $responseTime,
        int $memoryUsed,
        string $requestId
    ): void {
        try {
            $metrics = [
                'request_id' => $requestId,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route' => $request->route()?->getName(),
                'user_id' => $request->user()?->id,
                'response_time_ms' => $responseTime,
                'memory_used_bytes' => $memoryUsed,
                'memory_usage_percent' => $this->getMemoryUsagePercent(),
                'timestamp' => now()->toISOString()
            ];
            
            // 缓存性能指标
            $this->cacheService->cacheApiMetrics($metrics);
            
            Log::info('API请求完成', $metrics);
            
        } catch (\Exception $e) {
            Log::error('记录性能指标失败', [
                'error' => $e->getMessage(),
                'request_id' => $requestId
            ]);
        }
    }

    /**
     * 检查是否应该缓存响应
     */
    protected function shouldCacheResponse(Request $request, Response $response): bool
    {
        // 只缓存成功的GET请求
        if ($request->method() !== 'GET' || $response->getStatusCode() !== 200) {
            return false;
        }
        
        // 检查响应大小
        if (strlen($response->getContent()) > self::MAX_RESPONSE_SIZE) {
            return false;
        }
        
        // 检查内容类型
        $contentType = $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'application/json')) {
            return false;
        }
        
        return true;
    }

    /**
     * 缓存响应
     */
    protected function cacheResponse(Request $request, Response $response): void
    {
        try {
            $cacheKey = $this->generateCacheKey($request);
            $cacheData = [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $response->headers->all()
            ];
            
            // 根据路由设置不同的缓存时间
            $ttl = $this->getCacheTtl($request);
            
            Cache::put($cacheKey, $cacheData, $ttl);
            
            Log::debug('响应已缓存', [
                'cache_key' => $cacheKey,
                'ttl' => $ttl,
                'url' => $request->fullUrl()
            ]);
            
        } catch (\Exception $e) {
            Log::warning('缓存响应失败', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl()
            ]);
        }
    }

    /**
     * 获取缓存TTL
     */
    protected function getCacheTtl(Request $request): int
    {
        // 根据不同的API路径设置不同的缓存时间
        if ($request->is('api/autogen/agents')) {
            return 300; // 5分钟
        }
        
        if ($request->is('api/autogen/sessions/*/messages')) {
            return 60; // 1分钟
        }
        
        if ($request->is('api/exercises/*')) {
            return 3600; // 1小时
        }
        
        return 180; // 默认3分钟
    }

    /**
     * 检查是否应该触发性能分析
     */
    protected function shouldTriggerPerformanceAnalysis(float $responseTime): bool
    {
        return $responseTime > self::SLOW_REQUEST_THRESHOLD;
    }

    /**
     * 触发性能分析
     */
    protected function triggerPerformanceAnalysis(
        Request $request,
        float $responseTime,
        int $memoryUsed
    ): void {
        try {
            // 异步触发性能分析
            \App\Jobs\AutoGenPerformanceJob::dispatch('analyze_performance', [
                'trigger' => 'slow_request',
                'request_url' => $request->fullUrl(),
                'response_time' => $responseTime,
                'memory_used' => $memoryUsed
            ])->onQueue('performance');
            
        } catch (\Exception $e) {
            Log::error('触发性能分析失败', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl()
            ]);
        }
    }
}