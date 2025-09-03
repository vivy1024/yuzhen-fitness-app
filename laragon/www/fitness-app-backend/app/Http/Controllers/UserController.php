<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * 获取用户信息
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->toArray(),
                'stats' => $user->getStats(),
                'profile_complete' => $user->isProfileComplete(),
            ],
            'message' => '用户信息获取成功'
        ]);
    }

    /**
     * 更新用户信息
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'phone' => 'sometimes|string|max:20',
            'avatar' => 'sometimes|url',
            'gender' => 'sometimes|in:male,female,other',
            'age' => 'sometimes|integer|min:13|max:120',
            'height' => 'sometimes|numeric|min:100|max:250',
            'weight' => 'sometimes|numeric|min:30|max:300',
            'fitness_level' => 'sometimes|in:beginner,intermediate,advanced',
            'fitness_goal' => 'sometimes|in:lose_weight,gain_muscle,maintain_fitness,improve_strength,improve_endurance,rehabilitation',
            'available_time' => 'sometimes|integer|min:15|max:180',
            'training_days' => 'sometimes|integer|min:1|max:7',
            'equipment' => 'sometimes|array',
            'dietary_restrictions' => 'sometimes|array',
            'activity_level' => 'sometimes|in:sedentary,light,moderate,active,very_active',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '数据验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->update($validator->validated());

        // 检查档案是否完整
        if ($user->isProfileComplete() && !$user->profile_completed_at) {
            $user->update(['profile_completed_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->fresh()->toArray(),
                'profile_complete' => $user->isProfileComplete(),
            ],
            'message' => '用户信息更新成功'
        ]);
    }

    /**
     * 更新密码
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '数据验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => '当前密码不正确'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => '密码更新成功'
        ]);
    }

    /**
     * 获取用户统计数据
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        $period = $request->query('period', 'month'); // week, month, year

        $stats = $user->getStats();
        
        // 添加时间段相关统计
        if ($period === 'week') {
            $stats['period_workouts'] = $user->workoutRecords()
                ->where('workout_date', '>=', now()->startOfWeek())
                ->where('completed', true)
                ->count();
        } elseif ($period === 'month') {
            $stats['period_workouts'] = $user->workoutRecords()
                ->where('workout_date', '>=', now()->startOfMonth())
                ->where('completed', true)
                ->count();
        } elseif ($period === 'year') {
            $stats['period_workouts'] = $user->workoutRecords()
                ->where('workout_date', '>=', now()->startOfYear())
                ->where('completed', true)
                ->count();
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => '统计数据获取成功'
        ]);
    }

    /**
     * 获取用户进度数据
     */
    public function progress(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = $request->query('days', 30);

        // 获取训练进度
        $workoutProgress = $user->workoutRecords()
            ->where('workout_date', '>=', now()->subDays($days))
            ->where('completed', true)
            ->selectRaw('DATE(workout_date) as date, COUNT(*) as workouts, SUM(calories_burned) as calories')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 获取营养记录进度
        $nutritionProgress = $user->nutritionLogs()
            ->where('date', '>=', now()->subDays($days))
            ->selectRaw('date, SUM(calories) as calories, SUM(protein) as protein')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'workout_progress' => $workoutProgress,
                'nutrition_progress' => $nutritionProgress,
                'period_days' => $days,
            ],
            'message' => '进度数据获取成功'
        ]);
    }

    /**
     * 获取用户成就
     */
    public function achievements(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $achievements = [
            'workout_streak' => [
                'name' => '连续训练',
                'current' => $user->calculateCurrentStreak(),
                'milestones' => [7, 14, 30, 60, 100],
            ],
            'total_workouts' => [
                'name' => '总训练次数',
                'current' => $user->workoutRecords()->where('completed', true)->count(),
                'milestones' => [10, 25, 50, 100, 200],
            ],
            'calories_burned' => [
                'name' => '总消耗卡路里',
                'current' => $user->workoutRecords()->sum('calories_burned'),
                'milestones' => [1000, 5000, 10000, 25000, 50000],
            ],
            'training_plans_completed' => [
                'name' => '完成的训练计划',
                'current' => $user->trainingPlans()->where('completion_rate', '>=', 80)->count(),
                'milestones' => [1, 3, 5, 10, 20],
            ],
        ];

        // 计算已达成的成就
        foreach ($achievements as &$achievement) {
            $achievement['achieved'] = [];
            foreach ($achievement['milestones'] as $milestone) {
                if ($achievement['current'] >= $milestone) {
                    $achievement['achieved'][] = $milestone;
                }
            }
            $achievement['next_milestone'] = null;
            foreach ($achievement['milestones'] as $milestone) {
                if ($achievement['current'] < $milestone) {
                    $achievement['next_milestone'] = $milestone;
                    break;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $achievements,
            'message' => '成就数据获取成功'
        ]);
    }

    /**
     * 删除用户账户
     */
    public function destroy(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
            'confirmation' => 'required|in:DELETE_MY_ACCOUNT',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '数据验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => '密码不正确'
            ], 422);
        }

        // 软删除用户账户
        $user->update(['is_active' => false]);
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => '账户已删除'
        ]);
    }

    /**
     * 导出用户数据
     */
    public function exportData(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'user_info' => $user->toArray(),
            'training_plans' => $user->trainingPlans()->get()->toArray(),
            'nutrition_plans' => $user->nutritionPlans()->get()->toArray(),
            'workout_records' => $user->workoutRecords()->get()->toArray(),
            'nutrition_logs' => $user->nutritionLogs()->get()->toArray(),
            'chat_sessions' => $user->chatSessions()->get()->map(function ($session) {
                return $session->exportConversation();
            })->toArray(),
            'exported_at' => now()->toISOString(),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => '数据导出成功'
        ]);
    }

    /**
     * 获取用户偏好设置
     */
    public function preferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $preferences = [
            'fitness_level' => $user->fitness_level,
            'fitness_goal' => $user->fitness_goal,
            'available_time' => $user->available_time,
            'training_days' => $user->training_days,
            'equipment' => $user->equipment,
            'dietary_restrictions' => $user->dietary_restrictions,
            'activity_level' => $user->activity_level,
        ];

        return response()->json([
            'success' => true,
            'data' => $preferences,
            'message' => '偏好设置获取成功'
        ]);
    }

    /**
     * 更新用户偏好设置
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fitness_level' => 'sometimes|in:beginner,intermediate,advanced',
            'fitness_goal' => 'sometimes|in:lose_weight,gain_muscle,maintain_fitness,improve_strength,improve_endurance,rehabilitation',
            'available_time' => 'sometimes|integer|min:15|max:180',
            'training_days' => 'sometimes|integer|min:1|max:7',
            'equipment' => 'sometimes|array',
            'dietary_restrictions' => 'sometimes|array',
            'activity_level' => 'sometimes|in:sedentary,light,moderate,active,very_active',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '数据验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $user->update($validator->validated());

        return response()->json([
            'success' => true,
            'data' => $validator->validated(),
            'message' => '偏好设置更新成功'
        ]);
    }
}
