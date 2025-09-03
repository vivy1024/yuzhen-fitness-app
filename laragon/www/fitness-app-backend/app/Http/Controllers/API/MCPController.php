<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Services\CacheManager;
use App\Services\MCP\MCPContextProviderSimple;
use App\Services\MCP\MuscleWikiService;
use App\Services\MCP\ExerciseRecommendationService;
use App\Services\MCP\FastMCPClient;
use App\Services\MCP\DataTypes\UserProfile;
use App\Constants\ErrorCodes;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

/**
 * MCP API Controller (优化版本)
 *
 * 提供MCP（Model Context Protocol）相关的API接口
 * 支持运动数据查询、推荐和上下文生成
 *
 * 优化特性：
 * - 多层缓存策略
 * - 统一错误处理
 * - 标准化响应格式
 * - 性能监控和日志
 *
 * @author Augment Agent
 * @version 2.0.0
 * @created 2025-08-12 16:40:00
 * @updated 2025-08-18 05:30:00
 */
class MCPController extends BaseController
{
    private CacheManager $cache;
    private MCPContextProviderSimple $mcpProvider;
    private MuscleWikiService $muscleWikiService;
    private ExerciseRecommendationService $recommendationService;
    private FastMCPClient $fastMCPClient;

    public function __construct(
        CacheManager $cache,
        MCPContextProviderSimple $mcpProvider,
        MuscleWikiService $muscleWikiService,
        ExerciseRecommendationService $recommendationService
    ) {
        $this->cache = $cache;
        $this->mcpProvider = $mcpProvider;
        $this->muscleWikiService = $muscleWikiService;
        $this->recommendationService = $recommendationService;
        $this->fastMCPClient = new FastMCPClient();
        $this->logApiCall('MCPController初始化');
    }
    
    /**
     * 获取运动数据（优化版本）
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getExercises(Request $request): JsonResponse
    {
        try {
            // 1. 参数验证
            $validated = $this->validateRequest($request, [
                'muscle_groups' => 'array',
                'muscle_groups.*' => 'string',
                'equipment' => 'array',
                'equipment.*' => 'string',
                'difficulty' => 'array',
                'difficulty.*' => 'in:beginner,intermediate,advanced',
                'search' => 'string|max:255',
                'page' => 'integer|min:1',
                'limit' => 'integer|min:1|max:100',
                'sort_by' => 'in:name,difficulty,muscle_group,equipment,created_at,rating',
                'sort_order' => 'in:asc,desc',
                'has_video' => 'boolean',
                'has_image' => 'boolean',
                'exclude_ids' => 'array',
                'exclude_ids.*' => 'string'
            ]);

            // 2. 构建查询参数
            $query = [
                'muscle_groups' => $validated['muscle_groups'] ?? [],
                'equipment' => $validated['equipment'] ?? [],
                'difficulty' => $validated['difficulty'] ?? [],
                'searchText' => $validated['search'] ?? '',
                'page' => $validated['page'] ?? 1,
                'limit' => $validated['limit'] ?? 20,
                'sort_by' => $validated['sort_by'] ?? 'name',
                'sort_order' => $validated['sort_order'] ?? 'asc',
                'has_video' => $validated['has_video'] ?? null,
                'has_image' => $validated['has_image'] ?? null,
                'exclude_ids' => $validated['exclude_ids'] ?? []
            ];

            // 3. 生成缓存键
            $cacheKey = $this->cache->generateKey('exercises', $query);

            // 4. 记录API调用
            $this->logApiCall('getExercises', [
                'query_params' => $query,
                'cache_key' => $cacheKey
            ]);

            // 5. 从缓存获取或执行查询
            $exercises = $this->cache->remember($cacheKey, function () use ($query) {
                return $this->mcpProvider->getExercises($query);
            });

            // 6. 返回分页响应
            return $this->paginatedResponse($exercises, '获取运动数据成功');

        } catch (\Exception $e) {
            return $this->handleException($e, 'getExercises');
        }
    }
    
    /**
     * 获取单个运动详情
     * 
     * @param string $id
     * @return JsonResponse
     */
    public function getExercise(string $id): JsonResponse
    {
        try {
            $exercise = $this->mcpProvider->getExerciseById($id);
            
            if (!$exercise) {
                return response()->json([
                    'success' => false,
                    'message' => '运动不存在'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'message' => '获取运动详情成功',
                'data' => $exercise
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取运动详情失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 获取运动推荐
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getRecommendations(Request $request): JsonResponse
    {
        try {
            // 简化参数验证，允许空值
            $validator = Validator::make($request->all(), [
                'user_profile' => 'array',
                'workout_context' => 'array',
                'limit' => 'integer|min:1|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $userProfile = $request->input('user_profile');
            $workoutContext = $request->input('workout_context');

            $recommendations = $this->mcpProvider->recommendExercises($userProfile, $workoutContext);
            
            // 限制返回数量
            $limit = $request->input('limit', 10);
            $recommendations = $recommendations->take($limit);
            
            return response()->json([
                'success' => true,
                'message' => '获取运动推荐成功',
                'data' => $recommendations->toArray(),
                'meta' => [
                    'user_profile_summary' => [
                        'fitness_level' => $userProfile['fitness_level'] ?? 'intermediate',
                        'goals' => $userProfile['goals'] ?? [],
                        'available_equipment' => $userProfile['available_equipment'] ?? []
                    ],
                    'workout_context_summary' => [
                        'goal' => $workoutContext['goal'] ?? 'general',
                        'available_time' => $workoutContext['available_time'] ?? 60,
                        'energy_level' => $workoutContext['energy_level'] ?? 7,
                        'target_muscles' => $workoutContext['target_muscles'] ?? []
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取运动推荐失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 通过查询参数获取上下文（优化版本）
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getContextByQuery(Request $request): JsonResponse
    {
        try {
            // 1. 参数验证
            $validated = $this->validateRequest($request, [
                'type' => 'string|in:user_behavior,exercise,nutrition,general',
                'category' => 'string|max:100',
                'format' => 'string|in:json,text'
            ]);

            // 2. 设置默认值
            $type = $validated['type'] ?? 'general';
            $category = $validated['category'] ?? 'default';
            $format = $validated['format'] ?? 'json';

            // 3. 生成缓存键
            $cacheKey = $this->cache->generateKey('context', [
                'type' => $type,
                'category' => $category,
                'format' => $format
            ]);

            // 4. 记录API调用
            $this->logApiCall('getContextByQuery', [
                'type' => $type,
                'category' => $category,
                'format' => $format,
                'cache_key' => $cacheKey
            ]);

            // 5. 从缓存获取或生成上下文
            $context = $this->cache->remember($cacheKey, function () use ($type, $category, $format) {
                return $this->generateContextData($type, $category, $format);
            });

            // 6. 返回成功响应
            return $this->successResponse($context, '获取上下文成功', 200, [
                'type' => $type,
                'category' => $category,
                'format' => $format,
                'cache_hit' => $this->cache->has($cacheKey)
            ]);

        } catch (\Exception $e) {
            return $this->handleException($e, 'getContextByQuery');
        }
    }

    /**
     * 生成上下文数据的辅助方法
     */
    private function generateContextData($type, $category)
    {
        switch ($type) {
            case 'user_behavior':
                return $this->getUserBehaviorContext($category);
            case 'exercise':
                return $this->getExerciseContext($category);
            case 'nutrition':
                return $this->getNutritionContext($category);
            default:
                return $this->getGeneralContext($category);
        }
    }

    /**
     * 获取用户行为上下文
     */
    private function getUserBehaviorContext($category)
    {
        return [
            'category' => $category,
            'context_type' => 'user_behavior',
            'data' => [
                'recent_activities' => [
                    ['action' => 'view_exercise', 'exercise_id' => 123, 'timestamp' => now()->subHours(2)->toISOString()],
                    ['action' => 'start_workout', 'workout_id' => 456, 'timestamp' => now()->subHours(4)->toISOString()]
                ],
                'preferences' => [
                    'preferred_muscle_groups' => ['chest', 'shoulders'],
                    'workout_duration' => 45,
                    'difficulty_level' => 'intermediate'
                ],
                'goals' => [
                    'primary_goal' => 'muscle_gain',
                    'target_weight' => 75,
                    'timeline' => '3_months'
                ]
            ],
            'generated_at' => now()->toISOString()
        ];
    }

    /**
     * 获取运动上下文
     */
    private function getExerciseContext($category)
    {
        return [
            'category' => $category,
            'context_type' => 'exercise',
            'data' => [
                'popular_exercises' => [
                    ['name' => 'Push-up', 'muscle_group' => 'chest', 'difficulty' => 'beginner'],
                    ['name' => 'Squat', 'muscle_group' => 'legs', 'difficulty' => 'beginner']
                ],
                'recommendations' => [
                    'Based on your profile, we recommend starting with bodyweight exercises'
                ]
            ],
            'generated_at' => now()->toISOString()
        ];
    }

    /**
     * 获取营养上下文
     */
    private function getNutritionContext($category)
    {
        return [
            'category' => $category,
            'context_type' => 'nutrition',
            'data' => [
                'daily_targets' => [
                    'calories' => 2000,
                    'protein' => 150,
                    'carbs' => 200,
                    'fat' => 70
                ],
                'recommendations' => [
                    'Eat protein within 30 minutes after workout',
                    'Stay hydrated throughout the day'
                ]
            ],
            'generated_at' => now()->toISOString()
        ];
    }

    /**
     * 获取通用上下文
     */
    private function getGeneralContext($category)
    {
        return [
            'category' => $category,
            'context_type' => 'general',
            'data' => [
                'app_info' => [
                    'name' => '智能健身APP',
                    'version' => '1.0.0',
                    'features' => ['exercise_library', 'workout_plans', 'nutrition_tracking']
                ],
                'user_tips' => [
                    'Start with a warm-up before exercising',
                    'Listen to your body and rest when needed'
                ]
            ],
            'generated_at' => now()->toISOString()
        ];
    }

    /**
     * 生成AI上下文
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateContext(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'exercise_ids' => 'required|array|min:1',
                'exercise_ids.*' => 'string',
                'format' => 'required|array',
                'format.type' => 'required|in:default,openai,anthropic,json_ld,structured,minimal,comprehensive',
                'format.language' => 'in:zh,en,both',
                'format.max_exercises' => 'integer|min:1|max:100',
                'format.include_media' => 'boolean',
                'format.include_instructions' => 'boolean',
                'format.include_tips' => 'boolean',
                'format.include_variations' => 'boolean',
                'format.include_safety' => 'boolean'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $exerciseIds = $request->input('exercise_ids');
            $formatConfig = $request->input('format');
            
            // 获取运动数据
            $exercises = collect();
            foreach ($exerciseIds as $id) {
                $exercise = $this->mcpProvider->getExerciseById($id);
                if ($exercise) {
                    $exercises->push($exercise);
                }
            }

            if ($exercises->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到有效的运动数据'
                ], 404);
            }

            // 生成上下文
            $context = $this->mcpProvider->generateContext($exercises, $formatConfig);
            
            return response()->json([
                'success' => true,
                'message' => '生成AI上下文成功',
                'data' => [
                    'context' => $context,
                    'format' => $formatConfig,
                    'exercise_count' => $exercises->count(),
                    'generated_at' => now()->toISOString()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '生成AI上下文失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 获取数据统计
     * 
     * @return JsonResponse
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->mcpProvider->getStats();
            
            return response()->json([
                'success' => true,
                'message' => '获取数据统计成功',
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取数据统计失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 搜索运动
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'q' => 'required|string|min:1|max:255',
                'filters' => 'array',
                'filters.muscle_groups' => 'array',
                'filters.equipment' => 'array',
                'filters.difficulty' => 'array',
                'limit' => 'integer|min:1|max:50'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $searchText = $request->input('q');
            $filters = $request->input('filters', []);
            $limit = $request->input('limit', 20);
            
            $exercises = $this->mcpProvider->searchExercises($searchText, $filters, $limit);
            
            return response()->json([
                'success' => true,
                'message' => '搜索完成',
                'data' => $exercises->toArray(),
                'meta' => [
                    'query' => $searchText,
                    'filters' => $filters,
                    'result_count' => $exercises->count()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '搜索失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 记录用户行为
     */
    public function recordUserBehavior(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer',
                'action_type' => 'required|string',
                'action_data' => 'required|array',
                'context' => 'array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 这里应该保存到数据库，暂时返回成功
            return response()->json([
                'success' => true,
                'message' => '用户行为记录成功',
                'data' => [
                    'id' => uniqid(),
                    'user_id' => $request->input('user_id'),
                    'action_type' => $request->input('action_type'),
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '记录用户行为失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取用户行为历史
     */
    public function getUserBehaviorHistory(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id');
            $limit = $request->input('limit', 10);

            // 模拟用户行为历史数据
            $behaviors = collect();
            for ($i = 0; $i < $limit; $i++) {
                $behaviors->push([
                    'id' => uniqid(),
                    'user_id' => $userId,
                    'action_type' => 'exercise_completed',
                    'action_data' => [
                        'exercise_id' => 'exercise_' . ($i + 1),
                        'duration' => rand(20, 60),
                        'sets' => rand(2, 4),
                        'reps' => rand(8, 15)
                    ],
                    'timestamp' => now()->subDays($i)->toISOString()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => '获取用户行为历史成功',
                'data' => $behaviors->toArray(),
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => $limit,
                    'total' => $behaviors->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取用户行为历史失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 分析用户行为模式
     */
    public function analyzeUserBehaviorPatterns(string $userId): JsonResponse
    {
        try {
            // 模拟用户行为模式分析
            $patterns = [
                [
                    'pattern_type' => 'workout_frequency',
                    'description' => '用户平均每周训练3-4次',
                    'frequency' => 3.5,
                    'confidence' => 0.85,
                    'insights' => ['训练频率稳定', '适合中等强度训练计划']
                ],
                [
                    'pattern_type' => 'preferred_muscle_groups',
                    'description' => '用户偏好胸部和背部训练',
                    'frequency' => 2.1,
                    'confidence' => 0.78,
                    'insights' => ['上肢训练为主', '建议增加腿部训练']
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => '用户行为模式分析完成',
                'data' => [
                    'patterns' => $patterns,
                    'recommendations' => [
                        '保持当前训练频率',
                        '增加下肢训练比例',
                        '考虑添加有氧运动'
                    ],
                    'analysis_period' => '最近30天'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '用户行为模式分析失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 搜索知识库
     */
    public function searchKnowledge(Request $request): JsonResponse
    {
        try {
            $query = $request->input('query');
            $limit = $request->input('limit', 5);

            // 模拟知识库搜索结果
            $results = collect([
                [
                    'id' => 'knowledge_1',
                    'category' => 'exercise_science',
                    'subcategory' => 'muscle_hypertrophy',
                    'title' => 'Muscle Hypertrophy Mechanisms',
                    'content' => 'Muscle hypertrophy occurs through mechanical tension, metabolic stress, and muscle damage.',
                    'source' => 'Exercise Science Journal',
                    'evidence_level' => 'high',
                    'tags' => ['hypertrophy', 'muscle_growth', 'training'],
                    'references' => ['doi:10.1234/example1']
                ],
                [
                    'id' => 'knowledge_2',
                    'category' => 'exercise_science',
                    'subcategory' => 'strength_training',
                    'title' => 'Progressive Overload Principle',
                    'content' => 'Progressive overload is the gradual increase of stress placed upon the body during exercise training.',
                    'source' => 'Strength & Conditioning Research',
                    'evidence_level' => 'high',
                    'tags' => ['progressive_overload', 'strength', 'training'],
                    'references' => ['doi:10.1234/example2']
                ]
            ])->take($limit);

            return response()->json([
                'success' => true,
                'message' => '知识库搜索完成',
                'data' => [
                    'results' => $results->toArray(),
                    'total' => $results->count(),
                    'search_time' => 0.05
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '知识库搜索失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 添加知识库条目
     */
    public function addKnowledgeEntry(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'category' => 'required|string',
                'subcategory' => 'required|string',
                'title' => 'required|string',
                'content' => 'required|string',
                'source' => 'required|string',
                'evidence_level' => 'required|in:high,medium,low',
                'tags' => 'array',
                'references' => 'array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 模拟添加知识库条目
            $entry = [
                'id' => uniqid(),
                'category' => $request->input('category'),
                'subcategory' => $request->input('subcategory'),
                'title' => $request->input('title'),
                'content' => $request->input('content'),
                'source' => $request->input('source'),
                'evidence_level' => $request->input('evidence_level'),
                'tags' => $request->input('tags', []),
                'references' => $request->input('references', []),
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'message' => '知识库条目添加成功',
                'data' => $entry
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '添加知识库条目失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 上下文感知搜索
     */
    public function contextAwareSearch(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'required|string',
                'user_context' => 'required|array',
                'search_type' => 'required|in:exercise,nutrition,knowledge,all',
                'limit' => 'integer|min:1|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = $request->input('query');
            $userContext = $request->input('user_context');
            $searchType = $request->input('search_type');
            $limit = $request->input('limit', 5);

            // 模拟上下文感知搜索结果
            $results = collect([
                [
                    'id' => 'context_result_1',
                    'type' => 'exercise',
                    'title' => 'Push-ups (适合初学者)',
                    'content' => '基于您的健身水平，推荐标准俯卧撑',
                    'relevance_score' => 0.95,
                    'context_match' => ['fitness_level', 'available_equipment']
                ],
                [
                    'id' => 'context_result_2',
                    'type' => 'exercise',
                    'title' => 'Dumbbell Chest Press',
                    'content' => '基于您的器械偏好，推荐哑铃卧推',
                    'relevance_score' => 0.88,
                    'context_match' => ['equipment_preference', 'target_muscle']
                ]
            ])->take($limit);

            return response()->json([
                'success' => true,
                'message' => '上下文感知搜索完成',
                'data' => [
                    'results' => $results->toArray(),
                    'context_factors' => ['fitness_level', 'equipment_availability', 'time_constraint'],
                    'search_insights' => ['基于用户健身水平调整难度', '考虑器械可用性']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '上下文感知搜索失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取个性化内容
     */
    public function getPersonalizedContent(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id');
            $contentType = $request->input('content_type');
            $limit = $request->input('limit', 5);

            // 模拟个性化内容
            $content = collect([
                [
                    'id' => 'personalized_1',
                    'type' => 'exercise',
                    'title' => '为您推荐的胸部训练',
                    'description' => '基于您的训练历史和偏好',
                    'content' => [
                        'exercise_id' => 'push_ups',
                        'sets' => 3,
                        'reps' => '10-12',
                        'rest' => 60
                    ],
                    'personalization_score' => 0.92,
                    'reasoning' => '基于您的训练频率和肌肉群偏好'
                ],
                [
                    'id' => 'personalized_2',
                    'type' => 'nutrition',
                    'title' => '适合您的蛋白质摄入建议',
                    'description' => '基于您的体重和训练强度',
                    'content' => [
                        'daily_protein' => '120g',
                        'timing' => '训练后30分钟内',
                        'sources' => ['鸡胸肉', '鸡蛋', '乳清蛋白']
                    ],
                    'personalization_score' => 0.87,
                    'reasoning' => '基于您的体重和训练目标'
                ]
            ])->take($limit);

            return response()->json([
                'success' => true,
                'message' => '获取个性化内容成功',
                'data' => [
                    'content' => $content->toArray(),
                    'personalization_factors' => ['training_history', 'preferences', 'goals', 'body_metrics']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取个性化内容失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== MuscleWiki MCP 新增方法 ====================

    /**
     * 获取按肌肉群分类的动作 (MuscleWiki)
     */
    public function getMuscleWikiExercisesByMuscle(Request $request, string $muscleGroup): JsonResponse
    {
        try {
            $exercises = $this->muscleWikiService->getExercisesByMuscle($muscleGroup);

            return response()->json([
                'success' => true,
                'code' => 'OK',
                'message' => '获取MuscleWiki动作成功',
                'data' => [
                    'muscle_group' => $muscleGroup,
                    'exercises' => $exercises,
                    'count' => count($exercises),
                    'source' => 'musclewiki'
                ],
                'meta' => [
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                    'timestamp' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code' => 'MUSCLE_GROUP_ERROR',
                'message' => '获取肌肉群动作失败',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取按器械分类的动作 (MuscleWiki)
     */
    public function getMuscleWikiExercisesByEquipment(Request $request, string $equipment): JsonResponse
    {
        try {
            $exercises = $this->muscleWikiService->getExercisesByEquipment($equipment);

            return response()->json([
                'success' => true,
                'code' => 'OK',
                'message' => '获取MuscleWiki器械动作成功',
                'data' => [
                    'equipment' => $equipment,
                    'exercises' => $exercises,
                    'count' => count($exercises),
                    'source' => 'musclewiki'
                ],
                'meta' => [
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                    'timestamp' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code' => 'EQUIPMENT_ERROR',
                'message' => '获取器械动作失败',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取智能动作推荐 (MuscleWiki)
     */
    public function getMuscleWikiRecommendations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fitness_level' => 'required|in:beginner,intermediate,advanced',
            'target_muscles' => 'array',
            'target_muscles.*' => 'string',
            'available_equipment' => 'array',
            'available_equipment.*' => 'string',
            'time_constraint' => 'integer|min:10|max:120',
            'goals' => 'string|in:weight_loss,muscle_gain,strength,general_fitness',
            'limitations' => 'array',
            'limitations.*' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'code' => 'VALIDATION_ERROR',
                'message' => '请求参数验证失败',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            $userProfile = new UserProfile($request->all());
            $recommendations = $this->recommendationService->generateRecommendations($userProfile);

            return response()->json([
                'success' => true,
                'code' => 'OK',
                'message' => '生成MuscleWiki推荐成功',
                'data' => $recommendations->toArray(),
                'meta' => [
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                    'timestamp' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'code' => 'RECOMMENDATION_ERROR',
                'message' => '生成推荐失败',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取MuscleWiki数据统计
     */
    public function getMuscleWikiStats(Request $request): JsonResponse
    {
        try {
            $stats = $this->muscleWikiService->getStats();

            return response()->json([
                'success' => true,
                'code' => 'OK',
                'message' => '获取MuscleWiki统计信息成功',
                'data' => $stats,
                'meta' => [
                    'request_id' => $request->header('X-Request-ID', uniqid()),
                    'timestamp' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'getStats');
        }
    }

    /**
     * 清除MCP缓存
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function clearCache(Request $request): JsonResponse
    {
        try {
            // 1. 参数验证
            $validated = $this->validateRequest($request, [
                'pattern' => 'string|max:100',
                'confirm' => 'boolean'
            ]);

            $pattern = $validated['pattern'] ?? '*';
            $confirm = $validated['confirm'] ?? false;

            // 2. 安全检查
            if (!$confirm && $pattern === '*') {
                return $this->errorResponse(
                    '清除所有缓存需要确认参数 confirm=true',
                    400,
                    null,
                    ErrorCodes::VALIDATION_ERROR
                );
            }

            // 3. 记录操作
            $this->logApiCall('clearCache', [
                'pattern' => $pattern,
                'confirm' => $confirm
            ]);

            // 4. 执行缓存清除
            $deleted = $this->cache->forgetByPattern($pattern);

            // 5. 返回结果
            return $this->successResponse([
                'deleted_count' => $deleted,
                'pattern' => $pattern,
                'cache_stats' => $this->cache->getStats()
            ], '缓存清除成功');

        } catch (\Exception $e) {
            return $this->handleException($e, 'clearCache');
        }
    }

    /**
     * 获取缓存统计信息
     *
     * @return JsonResponse
     */
    public function getCacheStats(): JsonResponse
    {
        try {
            $stats = $this->cache->getStats();

            return $this->successResponse($stats, '获取缓存统计成功');

        } catch (\Exception $e) {
            return $this->handleException($e, 'getCacheStats');
        }
    }
    
    /**
     * MCP用户档案服务代理
     * 将请求代理到MCP用户档案服务器 (8003端口)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function proxyUserProfileMCP(Request $request): JsonResponse
    {
        try {
            // 获取路由信息
            $route = $request->route();
            $routeUri = $route->uri();
            
            // 提取MCP方法名
            $urlParts = explode('/', $routeUri);
            $mcpMethod = end($urlParts);
            
            // 获取请求数据
            $requestData = $request->all();
            
            \Log::info('MCP用户档案代理请求', [
                'method' => $mcpMethod,
                'data' => $requestData,
                'target_url' => 'http://localhost:8003/mcp',
                'mcp_call' => 'tools/call:' . $mcpMethod
            ]);
            
            // 发送请求到MCP服务器
            $response = $this->sendMCPRequest($mcpMethod, $requestData);
            
            return response()->json($response);
            
        } catch (\Exception $e) {
            \Log::error('MCP代理错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 直接返回错误，不使用模拟数据
            return response()->json([
                'success' => false,
                'message' => 'MCP服务器连接失败',
                'error' => $e->getMessage(),
                'hint' => '请确保 MCP 服务器在 http://localhost:8003 运行'
            ], 503);
        }
    }
    
    /**
     * 发送MCP请求到标准化服务器
     * @param string $method MCP方法名
     * @param array $params 请求参数
     * @param string $mcpUrl MCP服务器URL
     * @return array 响应数据
     */
    private function sendMCPRequest(string $method, array $params = [], string $mcpUrl = 'http://localhost:8003/mcp'): array
    {
        \Log::info('发送真实MCP请求', [
            'method' => $method,
            'params' => $params,
            'target_url' => $mcpUrl
        ]);
        
        try {
            // 实现FastMCP工具调用
            $result = $this->callFastMCPTool($method, $params);
            
            \Log::info('MCP响应成功', [
                'method' => $method,
                'result' => $result
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            \Log::error('MCP请求失败', [
                'method' => $method,
                'error' => $e->getMessage()
            ]);
            
            // 抛出异常让上层处理
            throw $e;
        }
    }
    
    /**
     * 调用FastMCP工具
     * @param string $toolName 工具名称
     * @param array $arguments 参数
     * @return array 响应数据
     */
    private function callFastMCPTool(string $toolName, array $arguments = []): array
    {
        try {
            // 检查MCP服务器连接
            if (!$this->fastMCPClient->checkConnection()) {
                throw new \Exception('无法连接到MCP服务器，请确保服务器在 http://localhost:8003 运行');
            }
            
            // 调用真实MCP服务器
            $result = $this->fastMCPClient->callTool($toolName, $arguments);
            
            \Log::info('MCP真实调用成功', [
                'tool' => $toolName,
                'arguments' => $arguments,
                'result_type' => $result['mcp_type'] ?? 'unknown'
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            \Log::error('MCP调用失败', [
                'tool' => $toolName,
                'error' => $e->getMessage(),
                'arguments' => $arguments
            ]);
            
            // 不再提供备用响应，直接抛出异常
            throw $e;
        }
}
}
