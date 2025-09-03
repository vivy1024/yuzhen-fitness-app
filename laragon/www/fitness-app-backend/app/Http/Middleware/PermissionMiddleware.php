<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户未认证'
            ], 401);
        }

        if (!$this->hasPermission($user, $permission)) {
            return response()->json([
                'success' => false,
                'message' => '权限不足'
            ], 403);
        }

        return $next($request);
    }

    /**
     * 检查用户权限
     */
    private function hasPermission($user, string $permission): bool
    {
        // 超级管理员拥有所有权限
        if ($user->is_admin) {
            return true;
        }

        // 检查用户角色权限
        $userPermissions = $this->getUserPermissions($user);
        
        return in_array($permission, $userPermissions);
    }

    /**
     * 获取用户权限列表
     */
    private function getUserPermissions($user): array
    {
        // 基础用户权限
        $permissions = [
            'user.profile.view',
            'user.profile.update',
            'training.plan.view',
            'training.plan.create',
            'training.plan.update',
            'training.plan.delete',
            'nutrition.plan.view',
            'nutrition.plan.create',
            'nutrition.plan.update',
            'nutrition.plan.delete',
            'exercise.view',
            'ai.chat',
            'ai.generate.plan',
        ];

        // 高级用户权限
        if ($user->is_premium) {
            $permissions = array_merge($permissions, [
                'exercise.premium.view',
                'ai.advanced.features',
                'data.export',
                'analytics.advanced',
            ]);
        }

        // 教练权限
        if ($user->role === 'coach') {
            $permissions = array_merge($permissions, [
                'user.list.view',
                'training.plan.public.create',
                'nutrition.plan.public.create',
                'knowledge.create',
                'knowledge.update',
            ]);
        }

        // 管理员权限
        if ($user->is_admin) {
            $permissions = array_merge($permissions, [
                'admin.dashboard',
                'admin.users.manage',
                'admin.content.manage',
                'admin.system.manage',
                'admin.ai.manage',
                'admin.analytics.view',
            ]);
        }

        return $permissions;
    }
}
