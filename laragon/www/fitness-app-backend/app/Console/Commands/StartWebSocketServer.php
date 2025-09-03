<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\Services\WebSocket\WebSocketServer;
use App\Services\WebSocket\AutoGenBroadcaster;
use Illuminate\Support\Facades\Log;

/**
 * WebSocket服务器启动命令
 */
class StartWebSocketServer extends Command
{
    /**
     * 命令签名
     */
    protected $signature = 'websocket:serve 
                            {--host=127.0.0.1 : WebSocket服务器主机地址}
                            {--port=6001 : WebSocket服务器端口}
                            {--ssl=false : 是否启用SSL}';

    /**
     * 命令描述
     */
    protected $description = '启动AutoGen WebSocket实时通信服务器';

    /**
     * WebSocket服务器实例
     */
    protected $webSocketServer;

    /**
     * AutoGen广播器实例
     */
    protected $broadcaster;

    /**
     * 执行命令
     */
    public function handle()
    {
        $host = $this->option('host') ?: config('broadcasting.connections.websocket.host', '127.0.0.1');
        $port = $this->option('port') ?: config('broadcasting.connections.websocket.port', 6001);
        $ssl = $this->option('ssl') ?: config('broadcasting.connections.websocket.ssl', false);

        $this->info('正在启动AutoGen WebSocket服务器...');
        $this->info("主机: {$host}");
        $this->info("端口: {$port}");
        $this->info("SSL: " . ($ssl ? '启用' : '禁用'));
        $this->newLine();

        try {
            // 创建WebSocket服务器实例
            $this->webSocketServer = new WebSocketServer();
            
            // 创建广播器实例并关联WebSocket服务器
            $this->broadcaster = new AutoGenBroadcaster($this->webSocketServer);
            
            // 将广播器实例绑定到容器中，供其他服务使用
            app()->singleton(AutoGenBroadcaster::class, function () {
                return $this->broadcaster;
            });

            // 创建Ratchet服务器
            $server = IoServer::factory(
                new HttpServer(
                    new WsServer($this->webSocketServer)
                ),
                $port,
                $host
            );

            // 设置信号处理
            $this->setupSignalHandlers($server);

            $this->info('✅ AutoGen WebSocket服务器启动成功!');
            $this->info("🌐 WebSocket地址: ws://{$host}:{$port}");
            $this->info('📊 服务器状态监控:');
            $this->info('   - 在线用户数: 0');
            $this->info('   - 活跃会话数: 0');
            $this->info('   - 总连接数: 0');
            $this->newLine();
            $this->info('按 Ctrl+C 停止服务器');
            $this->newLine();

            Log::info('WebSocket服务器启动', [
                'host' => $host,
                'port' => $port,
                'ssl' => $ssl
            ]);

            // 启动状态监控
            $this->startStatusMonitoring();

            // 运行服务器
            $server->run();
            
        } catch (\Exception $e) {
            $this->error('❌ WebSocket服务器启动失败: ' . $e->getMessage());
            Log::error('WebSocket服务器启动失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        return 0;
    }

    /**
     * 设置信号处理器
     */
    protected function setupSignalHandlers($server)
    {
        if (function_exists('pcntl_signal')) {
            // 处理SIGINT信号 (Ctrl+C)
            pcntl_signal(SIGINT, function () use ($server) {
                $this->info('\n正在关闭WebSocket服务器...');
                Log::info('WebSocket服务器正在关闭');
                exit(0);
            });

            // 处理SIGTERM信号
            pcntl_signal(SIGTERM, function () use ($server) {
                $this->info('\n收到终止信号，正在关闭WebSocket服务器...');
                Log::info('WebSocket服务器收到终止信号');
                exit(0);
            });
        }
    }

    /**
     * 启动状态监控
     */
    protected function startStatusMonitoring()
    {
        // 在实际应用中，这里可以启动一个定时器来定期输出服务器状态
        // 由于这是一个简化版本，我们只是记录启动信息
        
        register_shutdown_function(function () {
            Log::info('WebSocket服务器关闭');
        });
    }

    /**
     * 获取服务器状态信息
     */
    protected function getServerStatus(): array
    {
        if (!$this->webSocketServer) {
            return [
                'online_users' => 0,
                'active_sessions' => 0,
                'total_connections' => 0
            ];
        }

        return [
            'online_users' => $this->webSocketServer->getOnlineUsersCount(),
            'active_sessions' => $this->webSocketServer->getActiveSessionsCount(),
            'total_connections' => $this->webSocketServer->getTotalConnectionsCount()
        ];
    }

    /**
     * 显示帮助信息
     */
    protected function displayHelp()
    {
        $this->newLine();
        $this->info('📖 使用说明:');
        $this->info('   php artisan websocket:serve                    # 使用默认配置启动');
        $this->info('   php artisan websocket:serve --port=8080        # 指定端口');
        $this->info('   php artisan websocket:serve --host=0.0.0.0     # 指定主机');
        $this->info('   php artisan websocket:serve --ssl=true         # 启用SSL');
        $this->newLine();
        $this->info('🔧 配置文件: config/broadcasting.php');
        $this->info('📝 日志文件: storage/logs/laravel.log');
        $this->newLine();
    }

    /**
     * 验证配置
     */
    protected function validateConfiguration(): bool
    {
        // 检查必要的扩展
        $requiredExtensions = ['sockets', 'pcntl'];
        $missingExtensions = [];

        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $missingExtensions[] = $extension;
            }
        }

        if (!empty($missingExtensions)) {
            $this->error('❌ 缺少必要的PHP扩展: ' . implode(', ', $missingExtensions));
            $this->info('请安装缺少的扩展后重试。');
            return false;
        }

        return true;
    }

    /**
     * 检查端口是否可用
     */
    protected function isPortAvailable(string $host, int $port): bool
    {
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$socket) {
            return false;
        }

        $result = @socket_bind($socket, $host, $port);
        @socket_close($socket);

        return $result !== false;
    }

    /**
     * 显示启动横幅
     */
    protected function displayBanner()
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║                    AutoGen WebSocket 服务器                   ║');
        $this->info('║                      实时通信服务                            ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->newLine();
    }
}