<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\Performance\AutoGenPerformanceMonitor;
use App\Services\Cache\AutoGenCacheService;
use App\Jobs\AutoGenPerformanceJob;
use Carbon\Carbon;

/**
 * 性能监控控制器
 * 提供性能指标查询、分析和优化管理的API接口
 */
class PerformanceController extends Controller
{
    protected AutoGenPerformanceMonitor $performanceMonitor;
    protected AutoGenCacheService $cacheService;

    public function __construct(
        AutoGenPerformanceMonitor $performanceMonitor,
        AutoGenCacheService $cacheService
    ) {
        $this->performanceMonitor = $performanceMonitor;
        $this->cacheService = $cacheService;
    }

    /**
     * 获取实时性能指标
     */
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $metrics = $this->performanceMonitor->collectMetrics();
            
            return response()->json([
                'success' => true,
                'message' => '性能指标获取成功',
                'data' => [
                    'metrics' => $metrics,
                    'collected_at' => now()->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取性能指标失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取性能指标失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取性能分析报告
     */
    public function getAnalysis(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'period' => 'sometimes|string|in:1h,6h,24h,7d,30d',
                'include_recommendations' => 'sometimes|boolean'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 400);
            }
            
            $period = $request->get('period', '1h');
            $includeRecommendations = $request->get('include_recommendations', true);
            
            // 获取当前性能指标
            $metrics = $this->performanceMonitor->collectMetrics();
            
            // 分析性能
            $analysis = $this->performanceMonitor->analyzePerformance($metrics);
            
            // 获取历史趋势数据
            $trends = $this->getPerformanceTrends($period);
            
            $response = [
                'current_metrics' => $metrics,
                'analysis' => $analysis,
                'trends' => $trends,
                'period' => $period,
                'analyzed_at' => now()->toISOString()
            ];
            
            if (!$includeRecommendations) {
                unset($response['analysis']['recommendations']);
            }
            
            return response()->json([
                'success' => true,
                'message' => '性能分析获取成功',
                'data' => $response
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取性能分析失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取性能分析失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 生成性能报告
     */
    public function generateReport(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'format' => 'sometimes|string|in:json,pdf,excel',
                'period' => 'sometimes|string|in:1h,6h,24h,7d,30d',
                'include_details' => 'sometimes|boolean'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 400);
            }
            
            $format = $request->get('format', 'json');
            $period = $request->get('period', '24h');
            $includeDetails = $request->get('include_details', true);
            
            // 生成报告
            $report = $this->performanceMonitor->generateReport();
            
            // 添加额外信息
            $report['generation_params'] = [
                'format' => $format,
                'period' => $period,
                'include_details' => $includeDetails
            ];
            
            return response()->json([
                'success' => true,
                'message' => '性能报告生成成功',
                'data' => $report
            ]);
            
        } catch (\Exception $e) {
            Log::error('生成性能报告失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '生成性能报告失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 触发性能优化
     */
    public function optimize(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'sometimes|string|in:memory,cache,database,all',
                'force' => 'sometimes|boolean'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 400);
            }
            
            $optimizationType = $request->get('type', 'all');
            $force = $request->get('force', false);
            
            // 异步执行优化任务
            $job = AutoGenPerformanceJob::dispatch('optimize_performance', [
                'optimization_type' => $optimizationType,
                'force' => $force,
                'triggered_by' => $request->user()?->id ?? 'system',
                'triggered_at' => now()->toISOString()
            ])->onQueue('performance');
            
            return response()->json([
                'success' => true,
                'message' => '性能优化任务已启动',
                'data' => [
                    'job_id' => $job->getJobId(),
                    'optimization_type' => $optimizationType,
                    'estimated_duration' => '1-3分钟'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('触发性能优化失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '触发性能优化失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取缓存统计信息
     */
    public function getCacheStats(Request $request): JsonResponse
    {
        try {
            $stats = $this->cacheService->getCacheStats();
            
            return response()->json([
                'success' => true,
                'message' => '缓存统计获取成功',
                'data' => [
                    'stats' => $stats,
                    'collected_at' => now()->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取缓存统计失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取缓存统计失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 清理缓存
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|string|in:expired,sessions,messages,agents,all',
                'confirm' => 'required|boolean|accepted'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 400);
            }
            
            $cacheType = $request->get('type');
            
            // 异步执行缓存清理任务
            $job = AutoGenPerformanceJob::dispatch('cleanup_cache', [
                'cleanup_type' => $cacheType,
                'triggered_by' => $request->user()?->id ?? 'system',
                'triggered_at' => now()->toISOString()
            ])->onQueue('performance');
            
            return response()->json([
                'success' => true,
                'message' => '缓存清理任务已启动',
                'data' => [
                    'job_id' => $job->getJobId(),
                    'cleanup_type' => $cacheType,
                    'estimated_duration' => '30秒-2分钟'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('清理缓存失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '清理缓存失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取系统健康状态
     */
    public function getHealthStatus(Request $request): JsonResponse
    {
        try {
            // 异步执行健康检查
            $job = AutoGenPerformanceJob::dispatch('health_check', [
                'triggered_by' => $request->user()?->id ?? 'system',
                'triggered_at' => now()->toISOString()
            ])->onQueue('performance');
            
            // 获取基本健康指标
            $basicHealth = $this->getBasicHealthMetrics();
            
            return response()->json([
                'success' => true,
                'message' => '健康检查已启动',
                'data' => [
                    'job_id' => $job->getJobId(),
                    'basic_health' => $basicHealth,
                    'detailed_check_duration' => '30-60秒'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取健康状态失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取健康状态失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取性能趋势数据
     */
    public function getTrends(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'period' => 'sometimes|string|in:1h,6h,24h,7d,30d',
                'metrics' => 'sometimes|array',
                'metrics.*' => 'string|in:response_time,memory_usage,error_rate,active_sessions'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 400);
            }
            
            $period = $request->get('period', '24h');
            $metrics = $request->get('metrics', ['response_time', 'memory_usage', 'error_rate']);
            
            $trends = $this->getPerformanceTrends($period, $metrics);
            
            return response()->json([
                'success' => true,
                'message' => '性能趋势获取成功',
                'data' => [
                    'trends' => $trends,
                    'period' => $period,
                    'metrics' => $metrics,
                    'generated_at' => now()->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取性能趋势失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取性能趋势失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取性能趋势数据（内部方法）
     */
    protected function getPerformanceTrends(string $period, array $metrics = []): array
    {
        try {
            // 根据时间段计算时间范围
            $endTime = now();
            $startTime = match ($period) {
                '1h' => $endTime->copy()->subHour(),
                '6h' => $endTime->copy()->subHours(6),
                '24h' => $endTime->copy()->subDay(),
                '7d' => $endTime->copy()->subWeek(),
                '30d' => $endTime->copy()->subMonth(),
                default => $endTime->copy()->subDay()
            };
            
            // 从缓存中获取历史数据
            $historicalData = $this->cacheService->getPerformanceHistory($startTime, $endTime);
            
            // 处理趋势数据
            $trends = [
                'period' => $period,
                'start_time' => $startTime->toISOString(),
                'end_time' => $endTime->toISOString(),
                'data_points' => count($historicalData),
                'metrics' => []
            ];
            
            // 计算各项指标的趋势
            foreach ($metrics as $metric) {
                $trends['metrics'][$metric] = $this->calculateMetricTrend($historicalData, $metric);
            }
            
            return $trends;
            
        } catch (\Exception $e) {
            Log::error('计算性能趋势失败', [
                'period' => $period,
                'metrics' => $metrics,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * 计算单个指标的趋势
     */
    protected function calculateMetricTrend(array $historicalData, string $metric): array
    {
        try {
            $values = [];
            $timestamps = [];
            
            foreach ($historicalData as $dataPoint) {
                if (isset($dataPoint[$metric])) {
                    $values[] = $dataPoint[$metric];
                    $timestamps[] = $dataPoint['timestamp'];
                }
            }
            
            if (empty($values)) {
                return [
                    'current' => 0,
                    'average' => 0,
                    'min' => 0,
                    'max' => 0,
                    'trend' => 'stable',
                    'change_percent' => 0,
                    'data_points' => []
                ];
            }
            
            $current = end($values);
            $average = array_sum($values) / count($values);
            $min = min($values);
            $max = max($values);
            
            // 计算趋势方向
            $trend = 'stable';
            $changePercent = 0;
            
            if (count($values) >= 2) {
                $first = reset($values);
                $last = end($values);
                
                if ($first > 0) {
                    $changePercent = (($last - $first) / $first) * 100;
                    
                    if ($changePercent > 5) {
                        $trend = 'increasing';
                    } elseif ($changePercent < -5) {
                        $trend = 'decreasing';
                    }
                }
            }
            
            return [
                'current' => round($current, 2),
                'average' => round($average, 2),
                'min' => round($min, 2),
                'max' => round($max, 2),
                'trend' => $trend,
                'change_percent' => round($changePercent, 2),
                'data_points' => array_map(function($value, $timestamp) {
                    return [
                        'value' => round($value, 2),
                        'timestamp' => $timestamp
                    ];
                }, $values, $timestamps)
            ];
            
        } catch (\Exception $e) {
            Log::error('计算指标趋势失败', [
                'metric' => $metric,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * 获取基本健康指标
     */
    protected function getBasicHealthMetrics(): array
    {
        try {
            return [
                'timestamp' => now()->toISOString(),
                'memory_usage' => round((memory_get_usage(true) / (1024 ** 2)), 2) . ' MB',
                'memory_peak' => round((memory_get_peak_usage(true) / (1024 ** 2)), 2) . ' MB',
                'uptime' => $this->getSystemUptime(),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version()
            ];
            
        } catch (\Exception $e) {
            return [
                'error' => '无法获取基本健康指标',
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * 获取系统运行时间
     */
    protected function getSystemUptime(): string
    {
        try {
            if (file_exists('/proc/uptime')) {
                $uptime = file_get_contents('/proc/uptime');
                $seconds = (int) floatval($uptime);
                
                $days = floor($seconds / 86400);
                $hours = floor(($seconds % 86400) / 3600);
                $minutes = floor(($seconds % 3600) / 60);
                
                return "{$days}天 {$hours}小时 {$minutes}分钟";
            }
            
            return '未知';
            
        } catch (\Exception $e) {
            return '获取失败';
        }
    }
}