<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 基础控制器类
 * 
 * 提供统一的API响应格式和错误处理机制
 * 所有API控制器都应继承此基类
 * 
 * @author Backend Optimization Team
 * @version 2.0.0
 * @created 2025-01-18
 * @updated 2025-01-18
 */
abstract class BaseController extends Controller
{
    use AuthorizesRequests, ValidatesRequests;
    
    // 响应格式常量
    protected const SUCCESS_CODE = 'SUCCESS';
    protected const ERROR_CODE = 'ERROR';
    protected const API_VERSION = 'v2.0';
    
    /**
     * 统一成功响应格式
     * 
     * @param mixed $data 响应数据
     * @param string $message 响应消息
     * @param int $httpCode HTTP状态码
     * @param array $meta 额外元数据
     * @return JsonResponse
     */
    protected function successResponse(
        $data = null, 
        string $message = '操作成功', 
        int $httpCode = 200,
        array $meta = []
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'code' => self::SUCCESS_CODE,
            'message' => $message,
            'data' => $data,
            'meta' => $this->buildMeta($meta)
        ], $httpCode);
    }
    
    /**
     * 统一错误响应格式
     * 
     * @param string $message 错误消息
     * @param int $httpCode HTTP状态码
     * @param mixed $data 错误详情数据
     * @param string $errorCode 错误代码
     * @return JsonResponse
     */
    protected function errorResponse(
        string $message = '操作失败',
        int $httpCode = 400,
        $data = null,
        string $errorCode = null
    ): JsonResponse {
        $response = [
            'success' => false,
            'code' => $errorCode ?? self::ERROR_CODE,
            'message' => $message,
            'meta' => $this->buildMeta()
        ];
        
        if ($data !== null) {
            $response['error_data'] = $data;
        }
        
        // 记录错误日志（非4xx客户端错误）
        if ($httpCode >= 500) {
            Log::error('API Error Response', [
                'message' => $message,
                'http_code' => $httpCode,
                'data' => $data,
                'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
            ]);
        }
        
        return response()->json($response, $httpCode);
    }
    
    /**
     * 统一分页响应格式
     * 
     * @param mixed $paginatedData 分页数据对象或数组
     * @param string $message 响应消息
     * @return JsonResponse
     */
    protected function paginatedResponse(
        $paginatedData,
        string $message = '获取数据成功'
    ): JsonResponse {
        // 检查是否是分页对象
        if (method_exists($paginatedData, 'items')) {
            // Laravel分页对象
            return response()->json([
                'success' => true,
                'code' => self::SUCCESS_CODE,
                'message' => $message,
                'data' => $paginatedData->items(),
                'pagination' => [
                    'current_page' => $paginatedData->currentPage(),
                    'per_page' => $paginatedData->perPage(),
                    'total' => $paginatedData->total(),
                    'last_page' => $paginatedData->lastPage(),
                    'from' => $paginatedData->firstItem(),
                    'to' => $paginatedData->lastItem(),
                    'has_more' => $paginatedData->hasMorePages(),
                    'links' => [
                        'first' => $paginatedData->url(1),
                        'last' => $paginatedData->url($paginatedData->lastPage()),
                        'prev' => $paginatedData->previousPageUrl(),
                        'next' => $paginatedData->nextPageUrl()
                    ]
                ],
                'meta' => $this->buildMeta()
            ]);
        } else {
            // 普通Collection或数组
            $data = is_array($paginatedData) ? $paginatedData : $paginatedData->toArray();
            return response()->json([
                'success' => true,
                'code' => self::SUCCESS_CODE,
                'message' => $message,
                'data' => $data,
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => count($data),
                    'total' => count($data),
                    'last_page' => 1,
                    'from' => 1,
                    'to' => count($data),
                    'has_more' => false,
                    'links' => [
                        'first' => null,
                        'last' => null,
                        'prev' => null,
                        'next' => null
                    ]
                ],
                'meta' => $this->buildMeta()
            ]);
        }
    }
    
    /**
     * 构建响应元数据
     * 
     * @param array $additional 额外的元数据
     * @return array
     */
    private function buildMeta(array $additional = []): array
    {
        return array_merge([
            'timestamp' => now()->toISOString(),
            'request_id' => request()->header('X-Request-ID', uniqid('req_')),
            'version' => self::API_VERSION,
            'server_time' => microtime(true),
            'execution_time' => $this->getExecutionTime(),
            'memory_usage' => $this->getMemoryUsage()
        ], $additional);
    }
    
    /**
     * 统一异常处理
     * 
     * @param \Exception $e 异常对象
     * @param string $operation 操作名称
     * @return JsonResponse
     */
    protected function handleException(\Exception $e, string $operation = 'unknown'): JsonResponse
    {
        // 记录详细错误日志
        Log::error("操作失败: {$operation}", [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'request_id' => request()->header('X-Request-ID'),
            'user_id' => auth()->id(),
            'request_data' => request()->except(['password', 'password_confirmation']),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip()
        ]);
        
        // 根据异常类型返回不同响应
        return match(true) {
            $e instanceof \Illuminate\Validation\ValidationException => 
                $this->errorResponse(
                    '参数验证失败', 
                    422, 
                    $e->errors(), 
                    'VALIDATION_ERROR'
                ),
            
            $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 
                $this->errorResponse(
                    '请求的资源不存在', 
                    404, 
                    null, 
                    'NOT_FOUND'
                ),
            
            $e instanceof \Illuminate\Auth\AuthenticationException => 
                $this->errorResponse(
                    '身份验证失败，请重新登录', 
                    401, 
                    null, 
                    'UNAUTHORIZED'
                ),
            
            $e instanceof \Illuminate\Auth\Access\AuthorizationException => 
                $this->errorResponse(
                    '权限不足，无法执行此操作', 
                    403, 
                    null, 
                    'FORBIDDEN'
                ),
            
            $e instanceof \Illuminate\Database\QueryException => 
                $this->errorResponse(
                    app()->environment('production') ? '数据操作失败' : $e->getMessage(),
                    500,
                    null,
                    'DATABASE_ERROR'
                ),
            
            $e instanceof \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException => 
                $this->errorResponse(
                    '请求过于频繁，请稍后重试',
                    429,
                    null,
                    'RATE_LIMIT_EXCEEDED'
                ),
            
            default => $this->errorResponse(
                app()->environment('production') 
                    ? '服务暂时不可用，请稍后重试' 
                    : $e->getMessage(),
                500,
                app()->environment('production') ? null : [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ],
                'SERVER_ERROR'
            )
        };
    }
    
    /**
     * 获取请求执行时间
     * 
     * @return float
     */
    private function getExecutionTime(): float
    {
        if (defined('LARAVEL_START')) {
            return round((microtime(true) - LARAVEL_START) * 1000, 2); // 毫秒
        }
        
        return 0.0;
    }
    
    /**
     * 获取内存使用量
     * 
     * @return array
     */
    private function getMemoryUsage(): array
    {
        return [
            'current' => round(memory_get_usage() / 1024 / 1024, 2), // MB
            'peak' => round(memory_get_peak_usage() / 1024 / 1024, 2) // MB
        ];
    }
    
    /**
     * 验证请求参数并返回验证后的数据
     * 
     * @param Request $request
     * @param array $rules
     * @param array $messages
     * @return array
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateRequest(Request $request, array $rules, array $messages = []): array
    {
        return $request->validate($rules, $messages);
    }
    
    /**
     * 记录API调用日志
     * 
     * @param string $action 操作名称
     * @param array $data 相关数据
     * @return void
     */
    protected function logApiCall(string $action, array $data = []): void
    {
        Log::info("API调用: {$action}", array_merge([
            'request_id' => request()->header('X-Request-ID'),
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'method' => request()->method(),
            'url' => request()->fullUrl()
        ], $data));
    }
}
