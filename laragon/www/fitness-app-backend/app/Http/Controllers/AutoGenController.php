<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\AutoGenSession;
use App\Models\AutoGenMessage;
use App\Services\AutoGenService;
use Exception;

/**
 * AutoGen AI代理控制器
 * 处理AutoGen相关的API请求，包括消息处理、训练计划生成、专业建议等
 */
class AutoGenController extends Controller
{
    protected $autoGenService;

    public function __construct(AutoGenService $autoGenService)
    {
        $this->autoGenService = $autoGenService;
    }

    /**
     * 健康检查
     */
    public function healthCheck(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'status' => 'healthy',
                'service' => 'AutoGen AI代理服务',
                'timestamp' => now()->toISOString(),
                'version' => '1.0.0'
            ]);
        } catch (Exception $e) {
            Log::error('AutoGen健康检查失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取服务状态
     */
    public function getServiceStatus(): JsonResponse
    {
        try {
            $status = [
                'service' => 'AutoGen AI代理服务',
                'status' => 'running',
                'agents' => [
                    'fitness_coach' => 'active',
                    'nutrition_expert' => 'active',
                    'rehabilitation_specialist' => 'active'
                ],
                'sessions_count' => AutoGenSession::count(),
                'messages_count' => AutoGenMessage::count(),
                'uptime' => now()->diffInMinutes(now()->startOfDay()) . ' minutes',
                'timestamp' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $status
            ]);
        } catch (Exception $e) {
            Log::error('获取AutoGen服务状态失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取服务状态失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 处理消息
     */
    public function processMessage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'message' => 'required|string|max:2000',
                'session_id' => 'nullable|string',
                'user_id' => 'nullable|integer'
            ]);

            $message = $request->input('message');
            $sessionId = $request->input('session_id');
            $userId = $request->input('user_id', 1);

            // 调用AutoGen服务处理消息
            $response = $this->autoGenService->processMessage([
                'message' => $message,
                'session_id' => $sessionId,
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => true,
                'data' => $response,
                'message' => 'AI响应成功'
            ]);

        } catch (Exception $e) {
            Log::error('AutoGen消息处理失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '消息处理失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取会话列表
     */
    public function getSessions(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id ?? 1;
            
            $sessions = AutoGenSession::where('user_id', $userId)
                ->with(['messages' => function ($query) {
                    $query->latest()->limit(1);
                }])
                ->latest()
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $sessions
            ]);
        } catch (Exception $e) {
            Log::error('获取AutoGen会话列表失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取会话列表失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
