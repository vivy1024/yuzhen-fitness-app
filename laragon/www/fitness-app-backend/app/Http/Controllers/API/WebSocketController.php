<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Services\WebSocket\AutoGenBroadcaster;
use App\Models\AutoGenSession;
use App\Models\User;

/**
 * WebSocket控制器
 * 处理WebSocket相关的HTTP API请求
 */
class WebSocketController extends Controller
{
    protected $broadcaster;

    public function __construct(AutoGenBroadcaster $broadcaster)
    {
        $this->broadcaster = $broadcaster;
    }

    /**
     * 获取WebSocket连接信息
     */
    public function getConnectionInfo(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $connectionInfo = [
                'websocket_url' => $this->getWebSocketUrl(),
                'user_id' => $user->id,
                'auth_token' => $this->generateWebSocketToken($user),
                'heartbeat_interval' => 30000, // 30秒
                'reconnect_interval' => 5000,  // 5秒
                'max_reconnect_attempts' => 10
            ];

            return response()->json([
                'success' => true,
                'message' => '获取WebSocket连接信息成功',
                'data' => $connectionInfo
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取WebSocket连接信息失败', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取WebSocket连接信息失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 发送系统通知到指定会话
     */
    public function sendNotification(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
            'type' => 'required|string|in:info,warning,error,success',
            'message' => 'required|string|max:500',
            'data' => 'sometimes|array'
        ]);

        try {
            $sessionId = $request->input('session_id');
            $type = $request->input('type');
            $message = $request->input('message');
            $data = $request->input('data', []);

            // 验证会话权限
            $session = AutoGenSession::find($sessionId);
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => '会话不存在'
                ], 404);
            }

            if ($session->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问此会话'
                ], 403);
            }

            // 发送通知
            $this->broadcaster->broadcastSystemNotification($sessionId, $type, $message, $data);

            return response()->json([
                'success' => true,
                'message' => '通知发送成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('发送WebSocket通知失败', [
                'user_id' => $request->user()->id,
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '发送通知失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 广播Agent思考状态
     */
    public function broadcastAgentThinking(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
            'agent_id' => 'required|string',
            'agent_name' => 'required|string|max:100',
            'is_thinking' => 'required|boolean'
        ]);

        try {
            $sessionId = $request->input('session_id');
            $agentId = $request->input('agent_id');
            $agentName = $request->input('agent_name');
            $isThinking = $request->input('is_thinking');

            // 验证会话权限
            $session = AutoGenSession::find($sessionId);
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => '会话不存在'
                ], 404);
            }

            if ($session->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问此会话'
                ], 403);
            }

            // 广播Agent思考状态
            $this->broadcaster->broadcastAgentThinking($sessionId, $agentId, $agentName, $isThinking);

            return response()->json([
                'success' => true,
                'message' => 'Agent思考状态广播成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('广播Agent思考状态失败', [
                'user_id' => $request->user()->id,
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '广播Agent思考状态失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 广播性能指标
     */
    public function broadcastPerformanceMetrics(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'required|string',
            'metrics' => 'required|array',
            'metrics.response_time' => 'sometimes|numeric|min:0',
            'metrics.token_usage' => 'sometimes|integer|min:0',
            'metrics.cost' => 'sometimes|numeric|min:0',
            'metrics.memory_usage' => 'sometimes|numeric|min:0'
        ]);

        try {
            $sessionId = $request->input('session_id');
            $metrics = $request->input('metrics');

            // 验证会话权限
            $session = AutoGenSession::find($sessionId);
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => '会话不存在'
                ], 404);
            }

            if ($session->user_id !== $request->user()->id) {
                return response()->json([
                    'success' => false,
                    'message' => '无权限访问此会话'
                ], 403);
            }

            // 广播性能指标
            $this->broadcaster->broadcastPerformanceMetrics($sessionId, $metrics);

            return response()->json([
                'success' => true,
                'message' => '性能指标广播成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('广播性能指标失败', [
                'user_id' => $request->user()->id,
                'session_id' => $request->input('session_id'),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '广播性能指标失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取WebSocket服务器状态
     */
    public function getServerStatus(Request $request): JsonResponse
    {
        try {
            $status = [
                'server_running' => $this->broadcaster->isWebSocketServerAvailable(),
                'websocket_url' => $this->getWebSocketUrl(),
                'timestamp' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'message' => '获取服务器状态成功',
                'data' => $status
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取WebSocket服务器状态失败', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取服务器状态失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 测试WebSocket连接
     */
    public function testConnection(Request $request): JsonResponse
    {
        $request->validate([
            'session_id' => 'sometimes|string'
        ]);

        try {
            $user = $request->user();
            $sessionId = $request->input('session_id');

            // 如果提供了会话ID，验证权限
            if ($sessionId) {
                $session = AutoGenSession::find($sessionId);
                if (!$session) {
                    return response()->json([
                        'success' => false,
                        'message' => '会话不存在'
                    ], 404);
                }

                if ($session->user_id !== $user->id) {
                    return response()->json([
                        'success' => false,
                        'message' => '无权限访问此会话'
                    ], 403);
                }

                // 发送测试消息到指定会话
                $this->broadcaster->broadcastSystemNotification(
                    $sessionId,
                    'info',
                    'WebSocket连接测试消息',
                    ['test' => true, 'timestamp' => now()->toISOString()]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'WebSocket连接测试完成',
                'data' => [
                    'websocket_url' => $this->getWebSocketUrl(),
                    'server_available' => $this->broadcaster->isWebSocketServerAvailable(),
                    'test_sent' => $sessionId ? true : false
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('WebSocket连接测试失败', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'WebSocket连接测试失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取WebSocket URL
     */
    protected function getWebSocketUrl(): string
    {
        $host = config('broadcasting.connections.websocket.host', '127.0.0.1');
        $port = config('broadcasting.connections.websocket.port', 6001);
        $ssl = config('broadcasting.connections.websocket.ssl', false);
        
        $protocol = $ssl ? 'wss' : 'ws';
        
        return "{$protocol}://{$host}:{$port}";
    }

    /**
     * 生成WebSocket认证令牌
     */
    protected function generateWebSocketToken(User $user): string
    {
        // 这里应该生成一个临时的WebSocket认证令牌
        // 简化处理，实际应该使用JWT或其他安全的令牌机制
        return base64_encode(json_encode([
            'user_id' => $user->id,
            'timestamp' => now()->timestamp,
            'expires_at' => now()->addHours(24)->timestamp
        ]));
    }
}