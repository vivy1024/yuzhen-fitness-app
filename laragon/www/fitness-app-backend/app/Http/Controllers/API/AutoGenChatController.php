<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Services\AutoGenCoordinatorService;
use App\Services\MCP\MCPClientService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

/**
 * AutoGen聊天控制器
 * 
 * 专门处理AutoGen聊天相关的API请求
 * 提供训练计划生成、聊天历史管理等功能
 */
class AutoGenChatController extends BaseController
{
    protected AutoGenCoordinatorService $coordinatorService;
    protected MCPClientService $mcpClientService;

    public function __construct(
        AutoGenCoordinatorService $coordinatorService,
        MCPClientService $mcpClientService
    ) {
        $this->coordinatorService = $coordinatorService;
        $this->mcpClientService = $mcpClientService;
    }

    /**
     * 生成训练计划
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function generateTrainingPlan(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer',
                'goals' => 'required|array',
                'preferences' => 'sometimes|array',
                'constraints' => 'sometimes|array',
                'duration_weeks' => 'sometimes|integer|min:1|max:52',
                'sessions_per_week' => 'sometimes|integer|min:1|max:7'
            ]);

            if ($validator->fails()) {
                return $this->sendError('参数验证失败', $validator->errors()->toArray(), 422);
            }

            $validatedData = $validator->validated();
            
            Log::info('开始生成训练计划', [
                'user_id' => $validatedData['user_id'],
                'goals' => $validatedData['goals'],
                'request_id' => $request->header('X-Request-ID')
            ]);

            // 调用协调员服务生成训练计划
            $result = $this->coordinatorService->generateTrainingPlan(
                $validatedData['user_id'],
                $validatedData['goals'],
                $validatedData['preferences'] ?? [],
                $validatedData['constraints'] ?? [],
                $validatedData['duration_weeks'] ?? 12,
                $validatedData['sessions_per_week'] ?? 3
            );

            Log::info('训练计划生成成功', [
                'user_id' => $validatedData['user_id'],
                'plan_id' => $result['plan_id'] ?? null
            ]);

            return $this->sendResponse($result, '训练计划生成成功');

        } catch (Exception $e) {
            Log::error('训练计划生成失败', [
                'user_id' => $request->input('user_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->sendError(
                '训练计划生成失败: ' . $e->getMessage(),
                [],
                500
            );
        }
    }

    /**
     * 获取聊天历史
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getChatHistory(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer',
                'conversation_id' => 'sometimes|string',
                'limit' => 'sometimes|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return $this->sendError('参数验证失败', $validator->errors()->toArray(), 422);
            }

            $validatedData = $validator->validated();
            
            // 获取聊天历史
            $history = $this->coordinatorService->getSessionHistory(
                $validatedData['user_id'],
                $validatedData['conversation_id'] ?? null,
                $validatedData['limit'] ?? 50
            );

            return $this->sendResponse($history, '聊天历史获取成功');

        } catch (Exception $e) {
            Log::error('获取聊天历史失败', [
                'user_id' => $request->input('user_id'),
                'error' => $e->getMessage()
            ]);

            return $this->sendError(
                '获取聊天历史失败: ' . $e->getMessage(),
                [],
                500
            );
        }
    }

    /**
     * 清除聊天历史
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function clearChatHistory(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer',
                'conversation_id' => 'sometimes|string'
            ]);

            if ($validator->fails()) {
                return $this->sendError('参数验证失败', $validator->errors()->toArray(), 422);
            }

            $validatedData = $validator->validated();
            
            // 清除聊天历史
            $result = $this->coordinatorService->clearSessionHistory(
                $validatedData['user_id'],
                $validatedData['conversation_id'] ?? null
            );

            return $this->sendResponse($result, '聊天历史清除成功');

        } catch (Exception $e) {
            Log::error('清除聊天历史失败', [
                'user_id' => $request->input('user_id'),
                'error' => $e->getMessage()
            ]);

            return $this->sendError(
                '清除聊天历史失败: ' . $e->getMessage(),
                [],
                500
            );
        }
    }

    /**
     * 保存对话
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function saveConversation(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer',
                'conversation_id' => 'required|string',
                'title' => 'sometimes|string|max:255',
                'tags' => 'sometimes|array'
            ]);

            if ($validator->fails()) {
                return $this->sendError('参数验证失败', $validator->errors()->toArray(), 422);
            }

            $validatedData = $validator->validated();
            
            // 保存对话
            $result = $this->coordinatorService->saveConversation(
                $validatedData['user_id'],
                $validatedData['conversation_id'],
                $validatedData['title'] ?? null,
                $validatedData['tags'] ?? []
            );

            return $this->sendResponse($result, '对话保存成功');

        } catch (Exception $e) {
            Log::error('保存对话失败', [
                'user_id' => $request->input('user_id'),
                'conversation_id' => $request->input('conversation_id'),
                'error' => $e->getMessage()
            ]);

            return $this->sendError(
                '保存对话失败: ' . $e->getMessage(),
                [],
                500
            );
        }
    }

    /**
     * 健康检查
     * 
     * @return JsonResponse
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $status = [
                'service' => 'AutoGenChat',
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'coordinator_service' => $this->coordinatorService->getServiceStatus(),
                'mcp_client_service' => $this->mcpClientService->getServiceStatus()
            ];

            return $this->sendResponse($status, 'AutoGen聊天服务运行正常');

        } catch (Exception $e) {
            Log::error('AutoGen聊天服务健康检查失败', [
                'error' => $e->getMessage()
            ]);

            return $this->sendError(
                'AutoGen聊天服务健康检查失败: ' . $e->getMessage(),
                [],
                500
            );
        }
    }
}
