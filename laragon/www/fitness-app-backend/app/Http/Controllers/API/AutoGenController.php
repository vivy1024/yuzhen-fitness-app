<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Services\AutoGen\AutoGenCoordinatorService;
use App\Services\MCP\MCPClientService;
use App\Constants\ErrorCodes;
use App\Http\Requests\AutoGen\ProcessMessageRequest;
use App\Http\Requests\AutoGen\GetAdviceRequest;
use App\Http\Requests\AutoGen\SessionHistoryRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * AutoGen API控制器
 * 
 * 提供AutoGen多Agent协作系统的API接口
 * 支持智能健身建议、营养指导、康复建议等功能
 * 
 * 功能特性：
 * - 多Agent协作处理
 * - 智能意图识别
 * - 实时响应生成
 * - 会话状态管理
 * - 缓存优化
 * 
 * @author Backend Optimization Team
 * @version 1.0.0
 * @created 2025-01-27
 * @updated 2025-01-27
 */
class AutoGenController extends BaseController
{
    private AutoGenCoordinatorService $coordinatorService;
    private MCPClientService $mcpClientService;
    
    /**
     * 构造函数
     */
    public function __construct(
        AutoGenCoordinatorService $coordinatorService,
        MCPClientService $mcpClientService
    ) {
        $this->coordinatorService = $coordinatorService;
        $this->mcpClientService = $mcpClientService;
    }
    
    /**
     * 处理用户消息并生成AI响应
     * 
     * @param ProcessMessageRequest $request
     * @return JsonResponse
     */
    public function processMessage(ProcessMessageRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // 记录请求日志
            Log::info('AutoGen处理用户消息', [
                'user_id' => $validated['user_id'],
                'session_id' => $validated['session_id'] ?? null,
                'message_length' => strlen($validated['message'])
            ]);
            
            // 处理用户消息
            $response = $this->coordinatorService->processUserMessage(
                $validated['message'],
                $validated['user_id'],
                $validated['session_id'] ?? null,
                $validated['context'] ?? [],
                $validated['options'] ?? []
            );
            
            return $this->successResponse(
                $response,
                'AI响应生成成功'
            );
            
        } catch (Exception $e) {
            Log::error('AutoGen消息处理失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->input('user_id'),
                'message' => $request->input('message')
            ]);
            
            return $this->errorResponse(
                'AI响应生成失败: ' . $e->getMessage(),
                500,
                null,
                ErrorCodes::INTERNAL_ERROR
            );
        }
    }
    
    /**
     * 获取健身建议
     * 
     * @param GetAdviceRequest $request
     * @return JsonResponse
     */
    public function getFitnessAdvice(GetAdviceRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // 确保请求类型为健身
            if ($validated['request_type'] !== 'fitness') {
                return $this->errorResponse(
                    '请求类型错误，此接口仅支持健身建议',
                    400,
                    ['request_type' => $validated['request_type']],
                    ErrorCodes::VALIDATION_ERROR
                );
            }
            
            // 获取健身建议
            $advice = $this->mcpClientService->getFitnessAdvice(
                $validated['user_id'],
                $validated['goals'],
                $validated['fitness_level'],
                $validated['available_equipment'] ?? [],
                $validated['time_constraints'] ?? [],
                $validated['injuries_limitations'] ?? [],
                $validated['preferences'] ?? []
            );
            
            return $this->successResponse(
                $advice,
                '健身建议获取成功'
            );
            
        } catch (Exception $e) {
            Log::error('获取健身建议失败', [
                'error' => $e->getMessage(),
                'user_id' => $request->input('user_id')
            ]);
            
            return $this->errorResponse(
                '获取健身建议失败: ' . $e->getMessage(),
                500,
                null,
                ErrorCodes::INTERNAL_ERROR
            );
        }
    }
    
    /**
     * 获取营养建议
     * 
     * @param GetAdviceRequest $request
     * @return JsonResponse
     */
    public function getNutritionAdvice(GetAdviceRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // 确保请求类型为营养
            if ($validated['request_type'] !== 'nutrition') {
                return $this->errorResponse(
                    '请求类型错误，此接口仅支持营养建议',
                    400,
                    ['request_type' => $validated['request_type']],
                    ErrorCodes::VALIDATION_ERROR
                );
            }
            
            // 获取营养建议
            $advice = $this->mcpClientService->getNutritionAdvice(
                $validated['user_id'],
                $validated['goals'],
                $validated['activity_level'],
                $validated['dietary_restrictions'] ?? [],
                $validated['body_metrics'] ?? [],
                $validated['preferences'] ?? [],
                $validated['meal_preferences'] ?? []
            );
            
            return $this->successResponse(
                $advice,
                '营养建议获取成功'
            );
            
        } catch (Exception $e) {
            Log::error('获取营养建议失败', [
                'error' => $e->getMessage(),
                'user_id' => $request->input('user_id')
            ]);
            
            return $this->errorResponse(
                '获取营养建议失败: ' . $e->getMessage(),
                500,
                null,
                ErrorCodes::INTERNAL_ERROR
            );
        }
    }
    
    /**
     * 获取康复建议
     * 
     * @param GetAdviceRequest $request
     * @return JsonResponse
     */
    public function getRehabilitationAdvice(GetAdviceRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // 确保请求类型为康复
            if ($validated['request_type'] !== 'rehabilitation') {
                return $this->errorResponse(
                    '请求类型错误，此接口仅支持康复建议',
                    400,
                    ['request_type' => $validated['request_type']],
                    ErrorCodes::VALIDATION_ERROR
                );
            }
            
            // 获取康复建议
            $advice = $this->mcpClientService->getRehabilitationAdvice(
                $validated['user_id'],
                $validated['injury_type'],
                $validated['injury_location'],
                $validated['injury_severity'],
                $validated['injury_date'] ?? null,
                $validated['current_symptoms'] ?? [],
                $validated['activity_restrictions'] ?? [],
                $validated['previous_treatments'] ?? [],
                $validated['goals'] ?? []
            );
            
            return $this->successResponse(
                $advice,
                '康复建议获取成功'
            );
            
        } catch (Exception $e) {
            Log::error('获取康复建议失败', [
                'error' => $e->getMessage(),
                'user_id' => $request->input('user_id')
            ]);
            
            return $this->errorResponse(
                '获取康复建议失败: ' . $e->getMessage(),
                500,
                null,
                ErrorCodes::INTERNAL_ERROR
            );
        }
    }
    
    /**
     * 获取会话历史
     * 
     * @param SessionHistoryRequest $request
     * @return JsonResponse
     */
    public function getSessionHistory(SessionHistoryRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            // 获取会话历史
            $history = $this->coordinatorService->getSessionHistory(
                $validated['user_id'],
                $validated['session_id'] ?? null,
                $validated['limit'] ?? 20,
                $validated['offset'] ?? 0
            );
            
            return $this->successResponse(
                $history,
                '会话历史获取成功'
            );
            
        } catch (Exception $e) {
            Log::error('获取会话历史失败', [
                'error' => $e->getMessage(),
                'user_id' => $request->input('user_id')
            ]);
            
            return $this->errorResponse(
                '获取会话历史失败: ' . $e->getMessage(),
                500,
                null,
                ErrorCodes::INTERNAL_ERROR
            );
        }
    }
    
    /**
     * 获取服务状态
     * 
     * @return JsonResponse
     */
    public function getServiceStatus(): JsonResponse
    {
        try {
            // 获取协调员服务状态
            $coordinatorStatus = $this->coordinatorService->getServiceStatus();
            
            // 获取MCP客户端服务状态
            $mcpStatus = $this->mcpClientService->getServiceStatus();
            
            $status = [
                'coordinator_service' => $coordinatorStatus,
                'mcp_client_service' => $mcpStatus,
                'overall_status' => (
                    $coordinatorStatus['status'] === 'healthy' && 
                    $mcpStatus['status'] === 'healthy'
                ) ? 'healthy' : 'degraded',
                'timestamp' => now()->toISOString()
            ];
            
            return $this->successResponse(
                $status,
                'AutoGen服务状态获取成功'
            );
            
        } catch (Exception $e) {
            Log::error('获取AutoGen服务状态失败', [
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                '获取服务状态失败: ' . $e->getMessage(),
                500,
                null,
                ErrorCodes::INTERNAL_ERROR
            );
        }
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
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer',
                'fitness_goal' => 'required|string|in:lose_weight,gain_muscle,maintain_fitness,improve_strength,improve_endurance,rehabilitation',
                'fitness_level' => 'required|string|in:beginner,intermediate,advanced',
                'training_days' => 'required|integer|min:1|max:7',
                'available_time' => 'required|integer|min:15|max:180',
                'equipment' => 'array',
                'equipment.*' => 'string',
                'injuries_limitations' => 'array',
                'injuries_limitations.*' => 'string',
                'preferences' => 'array'
            ]);
            
            if ($validator->fails()) {
                return $this->errorResponse(
                    '请求参数验证失败',
                    400,
                    $validator->errors(),
                    ErrorCodes::VALIDATION_ERROR
                );
            }
            
            $validated = $validator->validated();
            
            Log::info('开始生成训练计划', [
                'user_id' => $validated['user_id'],
                'fitness_goal' => $validated['fitness_goal'],
                'fitness_level' => $validated['fitness_level']
            ]);
            
            // 通过协调员服务生成训练计划
            $trainingPlan = $this->coordinatorService->generateTrainingPlan(
                $validated['user_id'],
                [
                    'fitness_goal' => $validated['fitness_goal'],
                    'fitness_level' => $validated['fitness_level'],
                    'training_days' => $validated['training_days'],
                    'available_time' => $validated['available_time'],
                    'equipment' => $validated['equipment'] ?? [],
                    'injuries_limitations' => $validated['injuries_limitations'] ?? [],
                    'preferences' => $validated['preferences'] ?? []
                ]
            );
            
            return $this->successResponse(
                $trainingPlan,
                '训练计划生成成功'
            );
            
        } catch (Exception $e) {
            Log::error('训练计划生成失败', [
                'error' => $e->getMessage(),
                'user_id' => $request->input('user_id')
            ]);
            
            return $this->errorResponse(
                '训练计划生成失败: ' . $e->getMessage(),
                500,
                null,
                ErrorCodes::INTERNAL_ERROR
            );
        }
    }
    
    /**
     * 获取会话列表
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getSessions(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id');
            $limit = $request->input('limit', 20);
            $offset = $request->input('offset', 0);
            
            $query = \App\Models\AutoGenSession::query();
            
            if ($userId) {
                $query->where('user_id', $userId);
            }
            
            $sessions = $query->orderBy('last_activity_at', 'desc')
                            ->offset($offset)
                            ->limit($limit)
                            ->get();
            
            return $this->successResponse(
                $sessions,
                '获取会话列表成功'
            );
            
        } catch (Exception $e) {
            Log::error('获取会话列表失败', [
                'error' => $e->getMessage(),
                'user_id' => $request->input('user_id')
            ]);
            
            return $this->errorResponse(
                '获取会话列表失败: ' . $e->getMessage(),
                500,
                null,
                ErrorCodes::INTERNAL_ERROR
            );
        }
    }
    
    /**
     * 创建新会话
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function createSession(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer',
                'title' => 'nullable|string|max:255',
                'session_type' => 'nullable|string|max:50'
            ]);
            
            $session = \App\Models\AutoGenSession::create([
                'session_id' => \Illuminate\Support\Str::uuid(),
                'user_id' => $validated['user_id'],
                'title' => $validated['title'] ?? '新对话',
                'session_type' => $validated['session_type'] ?? 'chat',
                'status' => 'active',
                'started_at' => now(),
                'last_activity_at' => now()
            ]);
            
            return $this->successResponse(
                $session,
                '创建会话成功'
            );
            
        } catch (Exception $e) {
            Log::error('创建会话失败', [
                'error' => $e->getMessage(),
                'user_id' => $request->input('user_id')
            ]);
            
            return $this->errorResponse(
                '创建会话失败: ' . $e->getMessage(),
                500,
                null,
                ErrorCodes::INTERNAL_ERROR
            );
        }
    }
    
    /**
     * 删除会话
     * 
     * @param string $sessionId
     * @return JsonResponse
     */
    public function deleteSession(string $sessionId): JsonResponse
    {
        try {
            $session = \App\Models\AutoGenSession::where('session_id', $sessionId)->first();
            
            if (!$session) {
                return $this->errorResponse(
                    '会话不存在',
                    404,
                    null,
                    ErrorCodes::NOT_FOUND
                );
            }
            
            $session->delete();
            
            return $this->successResponse(
                null,
                '删除会话成功'
            );
            
        } catch (Exception $e) {
            Log::error('删除会话失败', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ]);
            
            return $this->errorResponse(
                '删除会话失败: ' . $e->getMessage(),
                500,
                null,
                ErrorCodes::INTERNAL_ERROR
            );
        }
    }
    
    /**
     * 健康检查接口
     * 
     * @return JsonResponse
     */
    public function healthCheck(): JsonResponse
    {
        try {
            // 执行基础健康检查
            $checks = [
                'coordinator_service' => $this->coordinatorService->healthCheck(),
                'mcp_client_service' => $this->mcpClientService->healthCheck(),
                'cache' => Cache::store()->getStore() !== null,
                'database' => true // 可以添加数据库连接检查
            ];
            
            $allHealthy = array_reduce($checks, function($carry, $check) {
                return $carry && $check;
            }, true);
            
            $status = [
                'status' => $allHealthy ? 'healthy' : 'unhealthy',
                'checks' => $checks,
                'timestamp' => now()->toISOString(),
                'version' => '1.0.0'
            ];
            
            return $this->successResponse(
                $status,
                'AutoGen健康检查完成',
                $allHealthy ? 200 : 503
            );
            
        } catch (Exception $e) {
            Log::error('AutoGen健康检查失败', [
                'error' => $e->getMessage()
            ]);
            
            return $this->errorResponse(
                '健康检查失败: ' . $e->getMessage(),
                503,
                null,
                ErrorCodes::SERVICE_UNAVAILABLE
            );
        }
    }
}