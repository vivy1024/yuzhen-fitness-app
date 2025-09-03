<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $ttl = 3600): Response
    {
        // 只缓存GET请求
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // 生成缓存键
        $cacheKey = $this->generateCacheKey($request);

        // 尝试从缓存获取响应
        $cachedResponse = Cache::get($cacheKey);
        if ($cachedResponse) {
            return response($cachedResponse['content'], $cachedResponse['status'])
                ->withHeaders($cachedResponse['headers'])
                ->header('X-Cache', 'HIT');
        }

        // 执行请求
        $response = $next($request);

        // 只缓存成功的响应
        if ($response->getStatusCode() === 200) {
            $cacheData = [
                'content' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $response->headers->all(),
            ];

            Cache::put($cacheKey, $cacheData, $ttl);
        }

        return $response->header('X-Cache', 'MISS');
    }

    /**
     * 生成缓存键
     */
    private function generateCacheKey(Request $request): string
    {
        $url = $request->url();
        $queryParams = $request->query();
        
        // 排序查询参数以确保一致性
        ksort($queryParams);
        
        $queryString = http_build_query($queryParams);
        
        return 'api_cache:' . md5($url . '?' . $queryString);
    }
}
