<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 用户认证相关路由
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ExercisesV2Controller;
use App\Http\Controllers\AutoGenController;
use App\Http\Controllers\PerformanceController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API版本信息
Route::get('/', function () {
    return response()->json([
        'name' => 'BUILD_BODY API',
        'version' => '2.1.0',
        'description' => '智能健身应用 RESTful API',
        'framework' => 'Laravel 10.48.22',
        'php_version' => PHP_VERSION,
        'features' => [
            'AutoGen AI多Agent系统',
            '1603个MuscleWiki专业动作',
            'MCP协议集成',
            'JWT认证系统',
            '高性能缓存'
        ],
        'documentation' => '/api/docs',
        'health_check' => '/api/health',
        'timestamp' => now()->toISOString()
    ]);
});

// 健康检查端点
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'service' => 'BUILD_BODY API',
        'timestamp' => now()->toISOString(),
        'uptime' => now()->diffInMinutes(now()->startOfDay()) . ' minutes',
        'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
        'database' => 'connected' // 可以添加数据库连接检查
    ]);
});

// 公开路由（无需认证）
Route::group(['prefix' => 'auth'], function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

// 需要认证的路由
Route::middleware('auth:sanctum')->group(function () {
    
    // 用户认证相关
    Route::group(['prefix' => 'auth'], function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
    
    // 用户管理
    Route::apiResource('users', UserController::class);
    Route::group(['prefix' => 'user'], function () {
        Route::get('/profile', [UserController::class, 'profile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::get('/stats', [UserController::class, 'getStats']);
    });
});

// 健身动作相关路由（公开访问）
Route::group(['prefix' => 'exercises'], function () {
    Route::get('/', [ExercisesV2Controller::class, 'index']);
    Route::get('/search', [ExercisesV2Controller::class, 'search']);
    Route::get('/stats', [ExercisesV2Controller::class, 'getStats']);
    Route::get('/muscle-groups', [ExercisesV2Controller::class, 'getMuscleGroups']);
    Route::get('/equipment-types', [ExercisesV2Controller::class, 'getEquipmentTypes']);
    Route::get('/{id}', [ExercisesV2Controller::class, 'show']);
});

// AutoGen AI相关路由
Route::group(['prefix' => 'autogen'], function () {
    Route::get('/health', [AutoGenController::class, 'healthCheck']);
    Route::get('/status', [AutoGenController::class, 'getServiceStatus']);
    Route::post('/chat', [AutoGenController::class, 'processMessage']);
    Route::get('/sessions', [AutoGenController::class, 'getSessions']);
    Route::post('/sessions', [AutoGenController::class, 'createSession']);
    Route::delete('/sessions/{sessionId}', [AutoGenController::class, 'deleteSession']);
});

// 性能监控和统计
Route::group(['prefix' => 'performance'], function () {
    Route::get('/stats', [PerformanceController::class, 'getPerformanceStats']);
    Route::get('/health', [PerformanceController::class, 'healthCheck']);
    Route::get('/cache-status', [PerformanceController::class, 'getCacheStatus']);
    Route::post('/clear-cache', [PerformanceController::class, 'clearCache']);
});

// 开发和调试路由（仅在非生产环境下可用）
if (!app()->environment('production')) {
    Route::group(['prefix' => 'debug'], function () {
        Route::get('/routes', function () {
            return response()->json([
                'routes' => collect(Route::getRoutes())->map(function ($route) {
                    return [
                        'method' => implode('|', $route->methods()),
                        'uri' => $route->uri(),
                        'name' => $route->getName(),
                        'action' => $route->getActionName()
                    ];
                })
            ]);
        });
        
        Route::get('/config', function () {
            return response()->json([
                'app' => [
                    'name' => config('app.name'),
                    'env' => config('app.env'),
                    'debug' => config('app.debug'),
                    'url' => config('app.url')
                ],
                'database' => [
                    'default' => config('database.default'),
                    'connections' => array_keys(config('database.connections'))
                ],
                'cache' => [
                    'default' => config('cache.default'),
                    'stores' => array_keys(config('cache.stores'))
                ]
            ]);
        });
    });
}
