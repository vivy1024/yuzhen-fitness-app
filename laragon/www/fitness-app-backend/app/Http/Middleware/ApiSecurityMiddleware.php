<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ApiSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 记录API访问日志
        $this->logApiAccess($request);

        // 检查请求头安全性
        if (!$this->validateSecurityHeaders($request)) {
            return response()->json([
                'success' => false,
                'message' => '请求头验证失败'
            ], 400);
        }

        // 检查请求大小
        if (!$this->validateRequestSize($request)) {
            return response()->json([
                'success' => false,
                'message' => '请求数据过大'
            ], 413);
        }

        // 检查可疑活动
        if ($this->detectSuspiciousActivity($request)) {
            Log::warning('检测到可疑API请求', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);
        }

        $response = $next($request);

        // 添加安全响应头
        return $this->addSecurityHeaders($response);
    }

    /**
     * 记录API访问日志
     */
    private function logApiAccess(Request $request): void
    {
        Log::info('API访问', [
            'ip' => $request->ip(),
            'method' => $request->method(),
            'path' => $request->path(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
            'timestamp' => now(),
        ]);
    }

    /**
     * 验证安全请求头
     */
    private function validateSecurityHeaders(Request $request): bool
    {
        // 检查Content-Type
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $contentType = $request->header('Content-Type');
            if (!$contentType || !str_contains($contentType, 'application/json')) {
                return false;
            }
        }

        // 检查User-Agent
        $userAgent = $request->userAgent();
        if (!$userAgent || strlen($userAgent) < 10) {
            return false;
        }

        return true;
    }

    /**
     * 验证请求大小
     */
    private function validateRequestSize(Request $request): bool
    {
        $maxSize = 10 * 1024 * 1024; // 10MB
        $contentLength = $request->header('Content-Length');
        
        if ($contentLength && $contentLength > $maxSize) {
            return false;
        }

        return true;
    }

    /**
     * 检测可疑活动
     */
    private function detectSuspiciousActivity(Request $request): bool
    {
        $suspicious = false;

        // 检查SQL注入模式
        $input = json_encode($request->all());
        $sqlPatterns = [
            '/union\s+select/i',
            '/drop\s+table/i',
            '/insert\s+into/i',
            '/delete\s+from/i',
            '/update\s+set/i',
            '/exec\s*\(/i',
        ];

        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $suspicious = true;
                break;
            }
        }

        // 检查XSS模式
        $xssPatterns = [
            '/<script/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
        ];

        foreach ($xssPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                $suspicious = true;
                break;
            }
        }

        // 检查路径遍历
        if (str_contains($input, '../') || str_contains($input, '..\\')) {
            $suspicious = true;
        }

        return $suspicious;
    }

    /**
     * 添加安全响应头
     */
    private function addSecurityHeaders(Response $response): Response
    {
        $response->headers->add([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'",
        ]);

        return $response;
    }
}
