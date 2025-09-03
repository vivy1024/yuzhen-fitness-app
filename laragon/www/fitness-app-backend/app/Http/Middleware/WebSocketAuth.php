<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * WebSocket认证中间件
 * 处理WebSocket连接的认证和授权
 */
class WebSocketAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // 检查WebSocket认证令牌
            $token = $request->header('X-WebSocket-Token') 
                  ?? $request->input('token') 
                  ?? $request->query('token');

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => '缺少WebSocket认证令牌',
                    'error_code' => 'WEBSOCKET_TOKEN_MISSING'
                ], 401);
            }

            // 验证令牌格式和有效性
            $tokenData = $this->validateWebSocketToken($token);
            
            if (!$tokenData) {
                return response()->json([
                    'success' => false,
                    'message' => 'WebSocket认证令牌无效',
                    'error_code' => 'WEBSOCKET_TOKEN_INVALID'
                ], 401);
            }

            // 检查令牌是否过期
            if ($tokenData['expires_at'] < now()->timestamp) {
                return response()->json([
                    'success' => false,
                    'message' => 'WebSocket认证令牌已过期',
                    'error_code' => 'WEBSOCKET_TOKEN_EXPIRED'
                ], 401);
            }

            // 将用户信息添加到请求中
            $request->merge([
                'websocket_user_id' => $tokenData['user_id'],
                'websocket_token_data' => $tokenData
            ]);

            Log::info('WebSocket认证成功', [
                'user_id' => $tokenData['user_id'],
                'token_expires_at' => $tokenData['expires_at'],
                'request_path' => $request->path()
            ]);

            return $next($request);
            
        } catch (\Exception $e) {
            Log::error('WebSocket认证中间件错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_path' => $request->path()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'WebSocket认证处理失败',
                'error_code' => 'WEBSOCKET_AUTH_ERROR'
            ], 500);
        }
    }

    /**
     * 验证WebSocket令牌
     */
    protected function validateWebSocketToken(string $token): ?array
    {
        try {
            // 解码base64编码的令牌
            $decoded = base64_decode($token);
            
            if (!$decoded) {
                return null;
            }

            // 解析JSON数据
            $tokenData = json_decode($decoded, true);
            
            if (!$tokenData || json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            // 验证必需字段
            if (!isset($tokenData['user_id']) || 
                !isset($tokenData['timestamp']) || 
                !isset($tokenData['expires_at'])) {
                return null;
            }

            // 验证数据类型
            if (!is_numeric($tokenData['user_id']) || 
                !is_numeric($tokenData['timestamp']) || 
                !is_numeric($tokenData['expires_at'])) {
                return null;
            }

            return $tokenData;
            
        } catch (\Exception $e) {
            Log::warning('WebSocket令牌验证失败', [
                'error' => $e->getMessage(),
                'token_length' => strlen($token)
            ]);
            
            return null;
        }
    }

    /**
     * 生成WebSocket认证令牌
     */
    public static function generateToken(int $userId, int $expiresInHours = 24): string
    {
        $tokenData = [
            'user_id' => $userId,
            'timestamp' => now()->timestamp,
            'expires_at' => now()->addHours($expiresInHours)->timestamp,
            'version' => '1.0'
        ];

        return base64_encode(json_encode($tokenData));
    }

    /**
     * 验证令牌是否有效
     */
    public static function isTokenValid(string $token): bool
    {
        try {
            $decoded = base64_decode($token);
            $tokenData = json_decode($decoded, true);
            
            if (!$tokenData || 
                !isset($tokenData['expires_at']) || 
                $tokenData['expires_at'] < now()->timestamp) {
                return false;
            }
            
            return true;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 从令牌中提取用户ID
     */
    public static function getUserIdFromToken(string $token): ?int
    {
        try {
            $decoded = base64_decode($token);
            $tokenData = json_decode($decoded, true);
            
            if (!$tokenData || !isset($tokenData['user_id'])) {
                return null;
            }
            
            return (int) $tokenData['user_id'];
            
        } catch (\Exception $e) {
            return null;
        }
    }
}