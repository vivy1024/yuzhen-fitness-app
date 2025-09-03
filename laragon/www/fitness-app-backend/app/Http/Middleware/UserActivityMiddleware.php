<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class UserActivityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $this->updateUserActivity($user, $request);
        }

        return $next($request);
    }

    /**
     * 更新用户活跃度
     */
    private function updateUserActivity($user, Request $request): void
    {
        $now = now();
        $cacheKey = "user_activity_{$user->id}";
        
        // 获取用户活动数据
        $activity = Cache::get($cacheKey, [
            'last_active_at' => null,
            'daily_requests' => 0,
            'last_request_date' => null,
            'total_requests' => 0,
            'features_used' => [],
            'devices' => [],
        ]);

        // 更新最后活跃时间
        $activity['last_active_at'] = $now;
        $activity['total_requests']++;

        // 更新每日请求数
        $today = $now->toDateString();
        if ($activity['last_request_date'] !== $today) {
            $activity['daily_requests'] = 1;
            $activity['last_request_date'] = $today;
        } else {
            $activity['daily_requests']++;
        }

        // 记录使用的功能
        $feature = $this->getFeatureFromRequest($request);
        if ($feature && !in_array($feature, $activity['features_used'])) {
            $activity['features_used'][] = $feature;
        }

        // 记录设备信息
        $device = $this->getDeviceInfo($request);
        if ($device && !in_array($device, $activity['devices'])) {
            $activity['devices'][] = $device;
        }

        // 缓存活动数据（24小时）
        Cache::put($cacheKey, $activity, 86400);

        // 定期更新数据库（避免频繁写入）
        if ($activity['total_requests'] % 10 === 0) {
            $this->updateUserDatabase($user, $activity);
        }
    }

    /**
     * 从请求中获取功能名称
     */
    private function getFeatureFromRequest(Request $request): ?string
    {
        $path = $request->path();
        
        $featureMap = [
            'api/training-plans' => 'training_plans',
            'api/nutrition-plans' => 'nutrition_plans',
            'api/exercises' => 'exercises',
            'api/ai/chat' => 'ai_chat',
            'api/ai/generate-training-plan' => 'ai_training_plan',
            'api/ai/generate-nutrition-plan' => 'ai_nutrition_plan',
            'api/user/stats' => 'user_stats',
            'api/user/progress' => 'user_progress',
        ];

        foreach ($featureMap as $pattern => $feature) {
            if (str_contains($path, $pattern)) {
                return $feature;
            }
        }

        return null;
    }

    /**
     * 获取设备信息
     */
    private function getDeviceInfo(Request $request): ?string
    {
        $userAgent = $request->userAgent();
        
        if (!$userAgent) {
            return null;
        }

        // 简单的设备检测
        if (str_contains($userAgent, 'Mobile') || str_contains($userAgent, 'Android')) {
            return 'mobile';
        } elseif (str_contains($userAgent, 'iPad') || str_contains($userAgent, 'Tablet')) {
            return 'tablet';
        } else {
            return 'desktop';
        }
    }

    /**
     * 更新用户数据库记录
     */
    private function updateUserDatabase($user, array $activity): void
    {
        try {
            $user->update([
                'last_active_at' => $activity['last_active_at'],
                'total_api_requests' => $activity['total_requests'],
            ]);
        } catch (\Exception $e) {
            // 静默处理错误，避免影响正常请求
            \Log::error('更新用户活跃度失败', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 获取用户活跃度统计
     */
    public static function getUserActivityStats(int $userId): array
    {
        $cacheKey = "user_activity_{$userId}";
        $activity = Cache::get($cacheKey, []);

        return [
            'last_active_at' => $activity['last_active_at'] ?? null,
            'daily_requests' => $activity['daily_requests'] ?? 0,
            'total_requests' => $activity['total_requests'] ?? 0,
            'features_used' => $activity['features_used'] ?? [],
            'devices' => $activity['devices'] ?? [],
            'is_active_today' => isset($activity['last_request_date']) && 
                               $activity['last_request_date'] === now()->toDateString(),
        ];
    }

    /**
     * 获取系统活跃度统计
     */
    public static function getSystemActivityStats(): array
    {
        // 这里应该从缓存或数据库中获取系统级统计
        return [
            'active_users_today' => Cache::get('system_active_users_today', 0),
            'total_requests_today' => Cache::get('system_total_requests_today', 0),
            'peak_concurrent_users' => Cache::get('system_peak_concurrent_users', 0),
            'most_used_features' => Cache::get('system_most_used_features', []),
            'device_distribution' => Cache::get('system_device_distribution', []),
        ];
    }
}
