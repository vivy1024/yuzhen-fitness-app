<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $key = 'api', int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $identifier = $this->resolveRequestSignature($request, $key);

        if (RateLimiter::tooManyAttempts($identifier, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($identifier);
            
            return response()->json([
                'success' => false,
                'message' => '请求过于频繁，请稍后再试',
                'retry_after' => $retryAfter,
            ], 429)->header('Retry-After', $retryAfter);
        }

        RateLimiter::hit($identifier, $decayMinutes * 60);

        $response = $next($request);

        // 添加速率限制头信息
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => RateLimiter::remaining($identifier, $maxAttempts),
            'X-RateLimit-Reset' => RateLimiter::availableIn($identifier) + time(),
        ]);

        return $response;
    }

    /**
     * Resolve the request signature for rate limiting.
     */
    protected function resolveRequestSignature(Request $request, string $key): string
    {
        $user = $request->user();
        
        if ($user) {
            return $key . ':user:' . $user->id;
        }

        return $key . ':ip:' . $request->ip();
    }
}
