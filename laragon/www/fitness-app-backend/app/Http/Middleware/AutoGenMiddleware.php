<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use App\Constants\ErrorCodes;

/**
 * AutoGen中间件
 * 
 * 处理AutoGen API请求的通用逻辑
 * 包括请求验证、速率限制、日志记录等
 * 
 * @author Backend Optimization Team
 * @version 1.0.0
 * @created 2025-01-27
 * @updated 2025-01-27
 */
class AutoGenMiddleware
{
    /**
     * 处理传入的请求
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. 记录请求开始时间
        $startTime = microtime(true);
        $requestId = uniqid('autogen_', true);
        
        // 2. 添加请求ID到请求头
        $request->headers->set('X-Request-ID', $requestId);
        
        // 3. 记录请求日志
        Log::info('AutoGen API请求开始', [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->input('user_id'),
            'session_id' => $request->input('session_id')
        ]);
        
        // 4. 速率限制检查
        $rateLimitResult = $this->checkRateLimit($request);
        if ($rateLimitResult !== null) {
            return $rateLimitResult;
        }
        
        // 5. 请求大小检查
        $sizeCheckResult = $this->checkRequestSize($request);
        if ($sizeCheckResult !== null) {
            return $sizeCheckResult;
        }
        
        // 6. 处理请求
        $response = $next($request);
        
        // 7. 计算处理时间
        $processingTime = round((microtime(true) - $startTime) * 1000, 2);
        
        // 8. 添加响应头
        if ($response instanceof JsonResponse) {
            $response->headers->set('X-Request-ID', $requestId);
            $response->headers->set('X-Processing-Time', $processingTime . 'ms');
            $response->headers->set('X-Service', 'AutoGen');
        }
        
        // 9. 记录响应日志
        Log::info('AutoGen API请求完成', [
            'request_id' => $requestId,
            'status_code' => $response->getStatusCode(),
            'processing_time_ms' => $processingTime,
            'response_size' => strlen($response->getContent())
        ]);
        
        return $response;
    }
    
    /**
     * 检查速率限制
     * 
     * @param Request $request
     * @return JsonResponse|null
     */
    private function checkRateLimit(Request $request): ?JsonResponse
    {
        $key = 'autogen_api:' . $request->ip();
        $maxAttempts = 60; // 每分钟最多60次请求
        $decayMinutes = 1;
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            
            Log::warning('AutoGen API速率限制触发', [
                'ip' => $request->ip(),
                'retry_after' => $seconds
            ]);
            
            return response()->json([
                'success' => false,
                'code' => ErrorCodes::RATE_LIMIT_EXCEEDED,
                'message' => '请求过于频繁，请稍后再试',
                'meta' => [
                    'retry_after' => $seconds,
                    'limit' => $maxAttempts,
                    'window' => $decayMinutes . ' minute(s)'
                ]
            ], 429);
        }
        
        RateLimiter::hit($key, $decayMinutes * 60);
        
        return null;
    }
    
    /**
     * 检查请求大小
     * 
     * @param Request $request
     * @return JsonResponse|null
     */
    private function checkRequestSize(Request $request): ?JsonResponse
    {
        $maxSize = 1024 * 1024; // 1MB
        $contentLength = $request->header('Content-Length', 0);
        
        if ($contentLength > $maxSize) {
            Log::warning('AutoGen API请求过大', [
                'ip' => $request->ip(),
                'content_length' => $contentLength,
                'max_size' => $maxSize
            ]);
            
            return response()->json([
                'success' => false,
                'code' => ErrorCodes::VALIDATION_ERROR,
                'message' => '请求数据过大',
                'meta' => [
                    'max_size' => $maxSize,
                    'current_size' => $contentLength
                ]
            ], 413);
        }
        
        return null;
    }
}