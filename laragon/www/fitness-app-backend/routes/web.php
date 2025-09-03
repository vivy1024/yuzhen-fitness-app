<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 取消默认路由
// Route::get('/', function () {
//     return view('welcome');
// });

// API版本信息
Route::get('/api', function () {
    return response()->json([
        'application' => 'BUILD_BODY 智能健身应用',
        'version' => '2.1.0',
        'framework' => 'Laravel 10',
        'description' => '基于AutoGen+DeepSeek R1+MCP集成架构的智能健身指导系统',
        'features' => [
            'AI多Agent协作',
            '1603个MuscleWiki专业动作',
            'MCP协议集成',
            '跨平台支持'
        ],
        'timestamp' => now()->toISOString(),
        'environment' => app()->environment()
    ]);
});

// 前端路由回退 - 处理Vue Router的history模式
Route::get('/{any}', function () {
    return view('app'); // 返回Vue应用的入口文件
})->where('any', '.*');
