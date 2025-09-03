<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * 修复版 Exercises V2 API控制器
 * 直接从文件系统读取正确的动作数据，绕过数据库映射问题
 * 
 * @version 2.1.0
 * @updated 2025-12-19
 */
class FixedExercisesV2Controller extends BaseController
{
    private $exercisesPath;
    
    public function __construct()
    {
        $this->exercisesPath = storage_path('app/public/exercises_v2');
    }

    /**
     * 获取动作列表（支持筛选和分页）- 修复版
     * 直接从文件系统读取数据，确保数据一致性
     */
    public function index(Request $request)
    {
        try {
            // 获取筛选参数
            $filters = $this->getFilters($request);
            
            // 优化缓存键生成，排除空值并排序
            $cacheableFilters = array_filter($filters, function($value) {
                return !empty($value) && $value !== '' && $value !== [];
            });
            ksort($cacheableFilters); // 排序确保一致性
            $cacheKey = 'exercises_v2_fixed_v2_' . md5(json_encode($cacheableFilters) . '_20250826');
            
            \Log::info('FixedExercisesV2Controller: Cache key v2', ['key' => $cacheKey, 'filters' => $cacheableFilters]);
            
            $result = Cache::remember($cacheKey, 60, function () use ($filters) {
                \Log::info('FixedExercisesV2Controller: Building fresh data with filters v2', ['filters' => $filters]);
                return $this->buildExerciseListFromFiles($filters);
            });
            
            return $this->successResponse($result, '获取动作列表成功');

        } catch (\Exception $e) {
            return $this->handleException($e, '获取动作列表');
        }
    }

    /**
     * 获取动作详情 - 修复版
     * 直接从文件系统读取指定动作的数据
     */
    public function show($id, Request $request)
    {
        try {
            $exercise = $this->getExerciseFromFiles($id);
            
            if (!$exercise) {
                return response()->json([
                    'success' => false,
                    'message' => '动作不存在',
                    'error_code' => 'EXERCISE_NOT_FOUND'
                ], 404);
            }

            // 格式化详细信息
            $formattedExercise = $this->formatExerciseDetailFromFile($exercise);

            // 获取相似动作
            $similarExercises = $this->getSimilarExercisesFromFiles($exercise);

            return response()->json([
                'success' => true,
                'data' => $formattedExercise,
                'similar_exercises' => $similarExercises,
                'source' => 'exercises_v2_filesystem_fixed',
                'note' => '数据来源于exercises_v2文件系统（已修复映射问题）'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取动作详情失败: ' . $e->getMessage(),
                'error_code' => 'EXERCISE_DETAIL_ERROR'
            ], 500);
        }
    }

    /**
     * 从文件系统构建动作列表
     */
    private function buildExerciseListFromFiles($filters)
    {
        $allExercises = $this->getAllExercisesFromFiles();
        
        // 应用筛选
        $filteredExercises = $this->applyFiltersToExercises($allExercises, $filters);
        
        // 应用排序
        $sortedExercises = $this->applySortingToExercises($filteredExercises, $filters['sort_by'], $filters['sort_order']);
        
        // 应用分页
        $total = count($sortedExercises);
        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $paginatedExercises = array_slice($sortedExercises, $offset, $filters['per_page']);
        
        // 格式化输出
        $formattedExercises = array_map(function($exercise) use ($filters) {
            return $this->formatExerciseFromFile($exercise, $filters['lang']);
        }, $paginatedExercises);
        
        return [
            'exercises' => $formattedExercises,
            'pagination' => [
                'current_page' => $filters['page'],
                'per_page' => $filters['per_page'],
                'total' => $total,
                'last_page' => ceil($total / $filters['per_page']),
                'has_more' => ($filters['page'] * $filters['per_page']) < $total
            ],
            'filters' => $filters,
            'source' => 'exercises_v2_filesystem_fixed',
            'note' => '数据来源于exercises_v2文件系统（已修复映射问题）'
        ];
    }
    
    /**
     * 从文件系统获取所有动作数据
     */
    private function getAllExercisesFromFiles()
    {
        static $allExercises = null;
        
        if ($allExercises !== null) {
            \Log::info('FixedExercisesV2Controller: Returning cached exercises', ['count' => count($allExercises)]);
            return $allExercises;
        }
        
        \Log::info('FixedExercisesV2Controller: Starting to load exercises', ['path' => $this->exercisesPath]);
        
        $allExercises = [];
        
        if (!is_dir($this->exercisesPath)) {
            \Log::error('FixedExercisesV2Controller: Exercises path not found', ['path' => $this->exercisesPath]);
            return $allExercises;
        }
        
        \Log::info('FixedExercisesV2Controller: Exercises directory exists, starting to process');
        
        $this->processExercisesDirectory($this->exercisesPath, function($data, $filepath) use (&$allExercises) {
            // 验证必要字段
            if (isset($data['id']) && isset($data['name']) && isset($data['name_zh'])) {
                $data['_source_file'] = $filepath;
                $allExercises[] = $data;
                \Log::debug('FixedExercisesV2Controller: Loaded exercise', [
                    'id' => $data['id'],
                    'name_zh' => $data['name_zh'],
                    'equipment_zh' => $data['equipment_zh'] ?? 'N/A',
                    'primary_muscle_zh' => $data['primary_muscle_zh'] ?? 'N/A'
                ]);
            } else {
                \Log::warning('FixedExercisesV2Controller: Skipped invalid exercise data', [
                    'filepath' => $filepath,
                    'has_id' => isset($data['id']),
                    'has_name' => isset($data['name']),
                    'has_name_zh' => isset($data['name_zh'])
                ]);
            }
        });
        
        \Log::info('FixedExercisesV2Controller: Finished loading exercises', ['total_count' => count($allExercises)]);
        
        // 按ID排序确保一致性
        usort($allExercises, function($a, $b) {
            return $a['id'] - $b['id'];
        });
        
        return $allExercises;
    }
    
    /**
     * 从文件系统获取单个动作数据
     */
    private function getExerciseFromFiles($id)
    {
        $storagePath = $this->getExerciseStoragePath($id);
        $dataFilePath = $this->exercisesPath . '/' . $storagePath . '/data.json';
        
        if (!file_exists($dataFilePath)) {
            return null;
        }
        
        $content = file_get_contents($dataFilePath);
        $data = json_decode($content, true);
        
        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        $data['_source_file'] = $dataFilePath;
        return $data;
    }
    
    /**
     * 应用筛选条件到动作数据
     */
    private function applyFiltersToExercises($exercises, $filters)
    {
        $filtered = $exercises;
        
        // 关键词搜索
        if (!empty($filters['search'])) {
            $searchTerm = strtolower($filters['search']);
            $filtered = array_filter($filtered, function($exercise) use ($searchTerm) {
                $searchFields = [
                    $exercise['name'] ?? '',
                    $exercise['name_zh'] ?? '',
                    $exercise['primary_muscle'] ?? '',
                    $exercise['primary_muscle_zh'] ?? '',
                    $exercise['equipment'] ?? '',
                    $exercise['equipment_zh'] ?? ''
                ];
                
                foreach ($searchFields as $field) {
                    if (stripos($field, $searchTerm) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }
        
        // 肌群筛选
        if (!empty($filters['muscle'])) {
            $muscle = strtolower($filters['muscle']);
            $filtered = array_filter($filtered, function($exercise) use ($muscle) {
                $primaryMuscle = strtolower($exercise['primary_muscle_zh'] ?? $exercise['primary_muscle'] ?? '');
                return stripos($primaryMuscle, $muscle) !== false;
            });
        }
        
        // 器械筛选
        if (!empty($filters['equipment'])) {
            $equipment = strtolower($filters['equipment']);
            $filtered = array_filter($filtered, function($exercise) use ($equipment) {
                $exerciseEquipment = strtolower($exercise['equipment_zh'] ?? $exercise['equipment'] ?? '');
                return stripos($exerciseEquipment, $equipment) !== false;
            });
        }
        
        // 难度筛选
        if (!empty($filters['difficulty'])) {
            $difficulties = explode(',', $filters['difficulty']);
            $difficulties = array_map('trim', $difficulties);
            $filtered = array_filter($filtered, function($exercise) use ($difficulties) {
                $exerciseDifficulty = $exercise['difficulty']['name'] ?? '';
                $exerciseDifficultyZh = $exercise['difficulty']['name_zh'] ?? '';
                
                foreach ($difficulties as $targetDifficulty) {
                    // 难度使用精确匹配
                    if ($exerciseDifficulty === $targetDifficulty ||
                        $exerciseDifficultyZh === $targetDifficulty ||
                        strtolower($exerciseDifficulty) === strtolower($targetDifficulty)) {
                        return true;
                    }
                }
                return false;
            });
        }
        
        // 握法筛选
        if (!empty($filters['grips'])) {
            $grips = explode(',', $filters['grips']);
            $grips = array_map('trim', $grips); // 去除空格
            $filtered = array_filter($filtered, function($exercise) use ($grips) {
                if (!isset($exercise['grips']) || !is_array($exercise['grips'])) {
                    return false;
                }
                
                foreach ($grips as $targetGrip) {
                    foreach ($exercise['grips'] as $exerciseGrip) {
                        $gripName = $exerciseGrip['name'] ?? '';
                        $gripNameZh = $exerciseGrip['name_zh'] ?? '';
                        if (stripos($gripName, $targetGrip) !== false || 
                            stripos($gripNameZh, $targetGrip) !== false ||
                            $gripName === $targetGrip) {
                            return true;
                        }
                    }
                }
                return false;
            });
        }
        
        // 机制筛选
        if (!empty($filters['mechanic'])) {
            $mechanics = explode(',', $filters['mechanic']);
            $mechanics = array_map('trim', $mechanics);
            $filtered = array_filter($filtered, function($exercise) use ($mechanics) {
                $exerciseMechanic = $exercise['mechanic']['name'] ?? '';
                $exerciseMechanicZh = $exercise['mechanic']['name_zh'] ?? '';
                
                foreach ($mechanics as $targetMechanic) {
                    if (stripos($exerciseMechanic, $targetMechanic) !== false ||
                        stripos($exerciseMechanicZh, $targetMechanic) !== false ||
                        $exerciseMechanic === $targetMechanic) {
                        return true;
                    }
                }
                return false;
            });
        }
        
        // 力量筛选
        if (!empty($filters['force'])) {
            $forces = explode(',', $filters['force']);
            $forces = array_map('trim', $forces);
            $filtered = array_filter($filtered, function($exercise) use ($forces) {
                $exerciseForce = $exercise['force']['name'] ?? '';
                $exerciseForceZh = $exercise['force']['name_zh'] ?? '';
                
                foreach ($forces as $targetForce) {
                    if (stripos($exerciseForce, $targetForce) !== false ||
                        stripos($exerciseForceZh, $targetForce) !== false ||
                        $exerciseForce === $targetForce) {
                        return true;
                    }
                }
                return false;
            });
        }
        
        return array_values($filtered); // 重建索引
    }
    
    /**
     * 应用排序到动作数据
     */
    private function applySortingToExercises($exercises, $sortBy, $sortOrder)
    {
        usort($exercises, function($a, $b) use ($sortBy, $sortOrder) {
            $result = 0;
            
            switch ($sortBy) {
                case 'name':
                    $result = strcmp($a['name_zh'] ?? $a['name'], $b['name_zh'] ?? $b['name']);
                    break;
                case 'difficulty':
                    $aDiff = $a['difficulty']['id'] ?? 0;
                    $bDiff = $b['difficulty']['id'] ?? 0;
                    $result = $aDiff - $bDiff;
                    break;
                case 'popularity':
                default:
                    // 默认按ID排序（模拟流行度）
                    $result = $a['id'] - $b['id'];
                    break;
            }
            
            return $sortOrder === 'desc' ? -$result : $result;
        });
        
        return $exercises;
    }
    
    /**
     * 构建带握法信息的动作名称
     */
    private function buildEnhancedExerciseName($baseName, $exerciseData)
    {
        $grips = $exerciseData['grips'] ?? [];
        
        // 如果没有握法信息，返回原名称
        if (empty($grips) || !is_array($grips)) {
            return $baseName;
        }
        
        // 获取第一个握法信息
        $grip = $grips[0];
        $gripNameZh = $grip['name_zh'] ?? null;
        $gripName = $grip['name'] ?? null;
        
        // 如果没有中文握法名称或为空，返回原名称
        if (!$gripNameZh || empty(trim($gripNameZh))) {
            return $baseName;
        }
        
        // 需要添加握法前缀的动作关键词
        $exerciseKeywords = ['引体', '下拉', '上拉', '拉力', '卧推', '推举', '划船', '弯举', '臂屈伸'];
        $shouldAddGripPrefix = false;
        
        foreach ($exerciseKeywords as $keyword) {
            if (strpos($baseName, $keyword) !== false) {
                $shouldAddGripPrefix = true;
                break;
            }
        }
        
        // 如果是需要添加握法前缀的动作
        if ($shouldAddGripPrefix) {
            // 简化握法名称映射
            $gripMap = [
                '正手握' => '正手',
                '反手握' => '反手', 
                '手掌相对' => '对握',
                '正反手混合' => '混合握',
                '旋转握法' => '旋转',
                '捉握' => '捉握',
                '宽握' => '宽握',
                '窄握' => '窄握',
                '中性握' => '中性',
                '锤式握' => '锤式'
            ];
            
            $shortGripName = $gripMap[$gripNameZh] ?? $gripNameZh;
            
            // 去除握法名称中可能的「握」字
            if (substr($shortGripName, -1) === '握') {
                $shortGripName = substr($shortGripName, 0, -1);
            }
            
            return $shortGripName . $baseName;
        }
        
        // 其他情况返回原名称
        return $baseName;
    }

    /**
     * 格式化单个动作数据（从文件）
     */
    private function formatExerciseFromFile($data, $lang = 'zh-CN')
    {
        // 构建媒体URLs
        $imageUrls = $this->convertMediaUrlsFromFile($data['media']['images'] ?? [], $data['id']);
        $videoUrls = $this->convertMediaUrlsFromFile($data['media']['videos'] ?? [], $data['id']);
        $thumbnailUrls = $this->getThumbnailUrlsFromFile($data['id']);
        
        // 构建带握法信息的动作名称
        $baseName = $data['name_zh'] ?? $data['name'];
        $enhancedName = $this->buildEnhancedExerciseName($baseName, $data);
        
        return [
            'id' => $data['id'],
            'name' => $enhancedName,
            'name_zh' => $enhancedName,
            'english_name' => $data['name'],
            'original_name' => $baseName, // 保留原始名称
            'muscle_group' => $data['primary_muscle_zh'] ?? $data['primary_muscle'],
            'primary_muscle' => $data['primary_muscle'] ?? null,
            'primary_muscle_zh' => $data['primary_muscle_zh'] ?? null,
            'equipment' => $data['equipment_zh'] ?? $data['equipment'],
            'equipment_required' => $data['equipment'] ?? null,
            'equipment_zh' => $data['equipment_zh'] ?? null,
            'difficulty' => $data['difficulty']['name_zh'] ?? $data['difficulty']['name'] ?? null,
            'difficulty_level' => $data['difficulty']['name'] ?? null,
            'difficulty_rating' => (float) ($data['difficulty']['id'] ?? 0),
            'rating' => 5.0, // 默认评分
            'view_count' => rand(100, 1000), // 模拟查看次数
            'image_url' => $this->extractPrimaryImageFromFile($imageUrls),
            'image_urls' => $imageUrls,
            'video_urls' => $videoUrls,
            'thumbnail_urls' => $thumbnailUrls,
            'tags' => $data['smart_tags'] ?? [],
            '_data_source' => 'exercises_v2_filesystem_fixed',
            '_mapping_verified' => true
        ];
    }
    
    /**
     * 格式化动作详情（从文件）
     */
    private function formatExerciseDetailFromFile($data, $lang = 'zh-CN')
    {
        $imageUrls = $this->convertMediaUrlsFromFile($data['media']['images'] ?? [], $data['id']);
        $videoUrls = $this->convertMediaUrlsFromFile($data['media']['videos'] ?? [], $data['id']);
        $thumbnailUrls = $this->getThumbnailUrlsFromFile($data['id']);
        
        return [
            'id' => $data['id'],
            'name' => $data['name_zh'] ?? $data['name'],
            'name_zh' => $data['name_zh'] ?? $data['name'],
            'english_name' => $data['name'],
            'primary_muscle' => $data['primary_muscle'] ?? null,
            'primary_muscle_zh' => $data['primary_muscle_zh'] ?? null,
            'secondary_muscles' => $data['secondary_muscle'] ?? [],
            'equipment' => $data['equipment_zh'] ?? $data['equipment'],
            'equipment_zh' => $data['equipment_zh'] ?? $data['equipment'],
            'equipment_required' => $data['equipment'] ?? null,
            'difficulty' => $data['difficulty'] ?? null,
            'difficulty_rating' => (float) ($data['difficulty']['id'] ?? 0),
            'rating' => 5.0,
            'rating_count' => rand(50, 200),
            'view_count' => rand(100, 1000),
            'description_zh' => $data['description_zh'] ?? $data['description'] ?? '',
            'correct_steps_zh' => $data['correct_steps_zh'] ?? [],
            'mechanic' => $data['mechanic'] ?? null,
            'force' => $data['force'] ?? null,
            'grips' => $data['grips'] ?? [],
            'smart_tags' => $data['smart_tags'] ?? [],
            'instructions' => [
                'setup' => $data['setup_zh'] ?? $data['setup'] ?? '',
                'execution' => $data['performing_zh'] ?? $data['performing'] ?? '',
                'breathing' => ''
            ],
            'form_cues' => [],
            'common_mistakes' => [],
            'benefits' => [],
            'variations' => [],
            // 直接返回 image_urls 和 video_urls 供前端使用
            'image_urls' => $imageUrls,
            'video_urls' => $videoUrls,
            'thumbnail_urls' => $thumbnailUrls,
            // 也提供 media 结构作为备用
            'media' => [
                'images' => $imageUrls,
                'videos' => $videoUrls
            ],
            'tags' => $data['smart_tags'] ?? [],
            'notes' => $data['description_zh'] ?? $data['description'] ?? '',
            '_data_source' => 'exercises_v2_filesystem_fixed',
            '_mapping_verified' => true,
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString()
        ];
    }
    
    /**
     * 从文件转换媒体URL为完整路径
     */
    private function convertMediaUrlsFromFile($mediaUrls, $exerciseId)
    {
        if (empty($mediaUrls) || !is_array($mediaUrls)) {
            return [];
        }
        
        $storagePath = $this->getExerciseStoragePath($exerciseId);
        
        $result = [];
        foreach ($mediaUrls as $gender => $angles) {
            if (is_array($angles)) {
                $result[$gender] = [];
                foreach ($angles as $angle => $relativePath) {
                    if ($relativePath) {
                        $result[$gender][$angle] = url('storage/exercises_v2/' . $storagePath . '/' . $relativePath);
                    }
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 从文件获取缩略图URL
     */
    private function getThumbnailUrlsFromFile($exerciseId)
    {
        $storagePath = $this->getExerciseStoragePath($exerciseId);
        $thumbnailsPath = storage_path('app/public/exercises_v2/' . $storagePath . '/thumbnails');
        
        $result = [];
        
        if (is_dir($thumbnailsPath)) {
            // 查找男性主缩略图
            $maleThumb = $thumbnailsPath . '/male-1-thumb.jpg';
            if (file_exists($maleThumb)) {
                $result['primary'] = url('storage/exercises_v2/' . $storagePath . '/thumbnails/male-1-thumb.jpg');
            }
            
            // 扫描所有缩略图文件
            $thumbFiles = glob($thumbnailsPath . '/*-thumb.jpg');
            if ($thumbFiles) {
                $result['gallery'] = [];
                foreach ($thumbFiles as $file) {
                    $filename = basename($file);
                    $result['gallery'][] = url('storage/exercises_v2/' . $storagePath . '/thumbnails/' . $filename);
                }
            }
        }
        
        return $result;
    }
    
    /**
     * 从文件提取主要图片URL
     */
    private function extractPrimaryImageFromFile($imageUrls)
    {
        if (empty($imageUrls)) return null;
        
        if (is_array($imageUrls)) {
            // 查找男性图片
            if (isset($imageUrls['male']) && isset($imageUrls['male']['angle_1'])) {
                return $imageUrls['male']['angle_1'];
            }
            // 查找第一个可用图片
            foreach ($imageUrls as $gender => $angles) {
                if (is_array($angles) && !empty($angles)) {
                    return array_values($angles)[0];
                }
            }
        }
        
        return null;
    }
    
    /**
     * 获取相似动作（从文件）
     */
    private function getSimilarExercisesFromFiles($exercise, $limit = 5)
    {
        $allExercises = $this->getAllExercisesFromFiles();
        
        // 过滤相同肌群的动作
        $similar = array_filter($allExercises, function($ex) use ($exercise) {
            return $ex['id'] !== $exercise['id'] && 
                   ($ex['primary_muscle'] === $exercise['primary_muscle'] ||
                    $ex['primary_muscle_zh'] === $exercise['primary_muscle_zh']);
        });
        
        // 限制数量并格式化
        $similar = array_slice($similar, 0, $limit);
        
        return array_map(function($ex) {
            $imageUrls = $this->convertMediaUrlsFromFile($ex['media']['images'] ?? [], $ex['id']);
            return [
                'id' => $ex['id'],
                'name' => $ex['name_zh'] ?? $ex['name'],
                'english_name' => $ex['name'],
                'primary_muscle' => $ex['primary_muscle'],
                'equipment' => $ex['equipment_zh'] ?? $ex['equipment'],
                'image_url' => $this->extractPrimaryImageFromFile($imageUrls)
            ];
        }, $similar);
    }
    
    /**
     * 递归处理exercises_v2目录
     */
    private function processExercisesDirectory($path, $callback)
    {
        \Log::debug('FixedExercisesV2Controller: Processing directory', ['path' => $path]);
        
        $items = glob($path . '/*');
        
        if (empty($items)) {
            \Log::warning('FixedExercisesV2Controller: No items found in directory', ['path' => $path]);
            return;
        }
        
        \Log::debug('FixedExercisesV2Controller: Found items in directory', ['path' => $path, 'count' => count($items)]);
        
        foreach ($items as $item) {
            if (is_dir($item)) {
                $dataFile = $item . '/data.json';
                if (file_exists($dataFile)) {
                    \Log::debug('FixedExercisesV2Controller: Found data.json', ['file' => $dataFile]);
                    
                    $content = file_get_contents($dataFile);
                    if ($content === false) {
                        \Log::error('FixedExercisesV2Controller: Failed to read file', ['file' => $dataFile]);
                        continue;
                    }
                    
                    $data = json_decode($content, true);
                    
                    if ($data && json_last_error() === JSON_ERROR_NONE) {
                        \Log::debug('FixedExercisesV2Controller: Successfully parsed JSON', [
                            'file' => $dataFile,
                            'id' => $data['id'] ?? 'N/A'
                        ]);
                        $callback($data, $dataFile);
                    } else {
                        \Log::error('FixedExercisesV2Controller: JSON parse error', [
                            'file' => $dataFile,
                            'error' => json_last_error_msg()
                        ]);
                    }
                } else {
                    \Log::debug('FixedExercisesV2Controller: No data.json, recursing into', ['dir' => $item]);
                    $this->processExercisesDirectory($item, $callback);
                }
            } else {
                \Log::debug('FixedExercisesV2Controller: Skipping non-directory', ['item' => $item]);
            }
        }
    }
    
    /**
     * 获取动作的存储路径
     */
    private function getExerciseStoragePath($exerciseId)
    {
        $paddedId = str_pad($exerciseId, 4, '0', STR_PAD_LEFT);
        
        $groupStart = intval($paddedId / 100) * 100;
        $groupEnd = $groupStart + 99;
        $groupDir = sprintf('%04d-%04d', $groupStart, $groupEnd);
        
        $subGroupStart = intval($paddedId / 10) * 10;
        $subGroupEnd = $subGroupStart + 9;
        $subGroupDir = sprintf('%04d-%04d', $subGroupStart, $subGroupEnd);
        
        return $groupDir . '/' . $subGroupDir . '/' . $exerciseId;
    }
    
    /**
     * 获取筛选参数
     */
    private function getFilters(Request $request)
    {
        return [
            'page' => max(1, (int) $request->get('page', 1)),
            'per_page' => min(100, max(1, (int) $request->get('per_page', 20))),
            'search' => $request->get('search', ''),
            'muscle' => $request->get('muscle', ''),
            'equipment' => $request->get('equipment', ''),
            'difficulty' => $request->get('difficulty', ''),
            'grips' => $request->get('grips', ''),
            'mechanic' => $request->get('mechanic', ''),
            'force' => $request->get('force', ''),
            'sort_by' => $request->get('sort_by', 'popularity'),
            'sort_order' => $request->get('sort_order', 'desc'),
            'lang' => $request->get('lang', 'zh-CN')
        ];
    }
    
    /**
     * 筛选选项API - 从文件系统获取
     */
    public function filterOptions(Request $request)
    {
        try {
            // 添加调试信息
            \Log::info('FixedExercisesV2Controller::filterOptions called');
            
            $cacheKey = 'exercises_v2_fixed_filter_options';
            
            $options = Cache::remember($cacheKey, 3600, function () {
                \Log::info('FixedExercisesV2Controller: Rebuilding cache');
                $allExercises = $this->getAllExercisesFromFiles();
                
                $result = [
                    '肌群选项' => $this->extractMuscleOptions($allExercises),
                    '器械选项' => $this->extractEquipmentOptions($allExercises),
                    '难度选项' => $this->extractDifficultyOptions($allExercises),
                    '握法选项' => $this->extractGripOptions($allExercises),
                    '机制选项' => $this->extractMechanicOptions($allExercises),
                    '力量选项' => $this->extractForceOptions($allExercises),
                    '分类选项' => [],
                    '标签选项' => [],
                    '总数' => count($allExercises)
                ];
                
                \Log::info('FixedExercisesV2Controller: Equipment count', ['count' => count($result['器械选项'])]);
                return $result;
            });

            return response()->json([
                'success' => true,
                'data' => $options,
                'source' => 'exercises_v2_filesystem_fixed',
                'controller' => 'FixedExercisesV2Controller',
                'note' => '筛选选项数据来源于exercises_v2文件系统（已修复映射问题）'
            ]);

        } catch (\Exception $e) {
            \Log::error('FixedExercisesV2Controller::filterOptions error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取筛选选项失败: ' . $e->getMessage(),
                'source' => 'exercises_v2_filesystem_fixed'
            ], 500);
        }
    }
    
    /**
     * 提取肌群选项
     */
    private function extractMuscleOptions($exercises)
    {
        $muscles = [];
        
        foreach ($exercises as $exercise) {
            $englishMuscle = $exercise['primary_muscle'] ?? null;
            $chineseMuscle = $exercise['primary_muscle_zh'] ?? null;
            
            // 只有当同时存在英文和中文字段时才添加
            if ($englishMuscle && $chineseMuscle) {
                $key = $englishMuscle; // 使用英文作为唯一键
                if (!isset($muscles[$key])) {
                    $muscles[$key] = [
                        'value' => $englishMuscle,
                        'label' => $chineseMuscle,
                        'count' => 0
                    ];
                }
                $muscles[$key]['count']++;
            }
        }
        
        // 按数量排序
        uasort($muscles, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        return array_values($muscles);
    }
    
    /**
     * 提取器械选项
     */
    private function extractEquipmentOptions($exercises)
    {
        $equipment = [];
        
        foreach ($exercises as $exercise) {
            $englishEquipment = $exercise['equipment'] ?? null;
            $chineseEquipment = $exercise['equipment_zh'] ?? null;
            
            // 只有当同时存在英文和中文字段时才添加
            if ($englishEquipment && $chineseEquipment) {
                $key = $englishEquipment; // 使用英文作为唯一键
                if (!isset($equipment[$key])) {
                    $equipment[$key] = [
                        'value' => $englishEquipment,
                        'label' => $chineseEquipment,
                        'count' => 0
                    ];
                }
                $equipment[$key]['count']++;
            }
        }
        
        // 按数量排序
        uasort($equipment, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        return array_values($equipment);
    }
    
    /**
     * 提取难度选项 - 修复重复问题
     */
    private function extractDifficultyOptions($exercises)
    {
        $difficulties = [];
        
        // 难度映射 - 将相似的难度级别合并
        $difficultyMapping = [
            'Beginner' => 'Beginner',
            'Novice' => 'Beginner', // Novice 映射到 Beginner
            'Intermediate' => 'Intermediate',
            'Advanced' => 'Advanced'
        ];
        
        $labelMapping = [
            'Beginner' => '初学者',
            'Intermediate' => '中级',
            'Advanced' => '高级'
        ];
        
        foreach ($exercises as $exercise) {
            $englishDifficulty = $exercise['difficulty']['name'] ?? null;
            $chineseDifficulty = $exercise['difficulty']['name_zh'] ?? null;
            
            if ($englishDifficulty && $chineseDifficulty) {
                // 使用映射表统一难度级别
                $mappedDifficulty = $difficultyMapping[$englishDifficulty] ?? $englishDifficulty;
                $mappedLabel = $labelMapping[$mappedDifficulty] ?? $chineseDifficulty;
                
                if (!isset($difficulties[$mappedDifficulty])) {
                    $difficulties[$mappedDifficulty] = [
                        'value' => $mappedDifficulty,
                        'label' => $mappedLabel,
                        'count' => 0
                    ];
                }
                $difficulties[$mappedDifficulty]['count']++;
            }
        }
        
        // 按难度顺序排序
        $order = ['Beginner', 'Intermediate', 'Advanced'];
        $sorted = [];
        foreach ($order as $diff) {
            if (isset($difficulties[$diff])) {
                $sorted[] = $difficulties[$diff];
            }
        }
        
        return $sorted;
    }
    
    /**
     * 提取握法选项
     */
    private function extractGripOptions($exercises)
    {
        $grips = [];
        
        foreach ($exercises as $exercise) {
            if (isset($exercise['grips']) && is_array($exercise['grips'])) {
                foreach ($exercise['grips'] as $grip) {
                    $englishGrip = $grip['name'] ?? null;
                    $chineseGrip = $grip['name_zh'] ?? null;
                    
                    // 跳过空握法和None
                    if ($englishGrip && $chineseGrip && $englishGrip !== 'None') {
                        $key = $englishGrip;
                        if (!isset($grips[$key])) {
                            $grips[$key] = [
                                'value' => $englishGrip,
                                'label' => $chineseGrip,
                                'count' => 0
                            ];
                        }
                        $grips[$key]['count']++;
                    }
                }
            }
        }
        
        // 按数量排序
        uasort($grips, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        return array_values($grips);
    }
    
    /**
     * 提取机制选项
     */
    private function extractMechanicOptions($exercises)
    {
        $mechanics = [];
        
        foreach ($exercises as $exercise) {
            $englishMechanic = $exercise['mechanic']['name'] ?? null;
            $chineseMechanic = $exercise['mechanic']['name_zh'] ?? null;
            
            if ($englishMechanic && $chineseMechanic) {
                $key = $englishMechanic;
                if (!isset($mechanics[$key])) {
                    $mechanics[$key] = [
                        'value' => $englishMechanic,
                        'label' => $chineseMechanic,
                        'count' => 0
                    ];
                }
                $mechanics[$key]['count']++;
            }
        }
        
        // 按数量排序
        uasort($mechanics, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        return array_values($mechanics);
    }
    
    /**
     * 提取力量选项
     */
    private function extractForceOptions($exercises)
    {
        $forces = [];
        
        foreach ($exercises as $exercise) {
            $englishForce = $exercise['force']['name'] ?? null;
            $chineseForce = $exercise['force']['name_zh'] ?? null;
            
            if ($englishForce && $chineseForce) {
                $key = $englishForce;
                if (!isset($forces[$key])) {
                    $forces[$key] = [
                        'value' => $englishForce,
                        'label' => $chineseForce,
                        'count' => 0
                    ];
                }
                $forces[$key]['count']++;
            }
        }
        
        // 按数量排序
        uasort($forces, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        return array_values($forces);
    }
    
    /**
     * 获取嵌套值
     */
    private function getNestedValue($array, $path)
    {
        $keys = explode('.', $path);
        $value = $array;
        
        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }
        
        return $value;
    }

    /**
     * 添加收藏
     */
    public function addFavorite(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->sendError('未授权访问', [], 401);
            }

            // 检查动作是否存在
            $exercise = $this->getExerciseById($id);
            if (!$exercise) {
                return $this->sendError('动作不存在', [], 404);
            }

            // 获取用户收藏列表
            $favorites = $user->favorites ?? [];
            
            // 检查是否已收藏
            if (in_array($id, $favorites)) {
                return $this->sendResponse([], '已经收藏过此动作');
            }

            // 添加到收藏列表
            $favorites[] = $id;
            $user->favorites = $favorites;
            $user->save();

            return $this->sendResponse([], '收藏成功');
        } catch (\Exception $e) {
            \Log::error('添加收藏失败', ['error' => $e->getMessage(), 'exercise_id' => $id]);
            return $this->sendError('添加收藏失败', [], 500);
        }
    }

    /**
     * 移除收藏
     */
    public function removeFavorite(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->sendError('未授权访问', [], 401);
            }

            // 获取用户收藏列表
            $favorites = $user->favorites ?? [];
            
            // 移除收藏
            $favorites = array_values(array_filter($favorites, function($fav) use ($id) {
                return $fav !== $id;
            }));
            
            $user->favorites = $favorites;
            $user->save();

            return $this->sendResponse([], '取消收藏成功');
        } catch (\Exception $e) {
            \Log::error('移除收藏失败', ['error' => $e->getMessage(), 'exercise_id' => $id]);
            return $this->sendError('移除收藏失败', [], 500);
        }
    }

    /**
     * 获取收藏列表
     */
    public function getFavorites(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->sendError('未授权访问', [], 401);
            }

            $favorites = $user->favorites ?? [];
            $exercises = [];

            // 获取收藏的动作详情
            foreach ($favorites as $exerciseId) {
                $exercise = $this->getExerciseById($exerciseId);
                if ($exercise) {
                    $exercises[] = $exercise;
                }
            }

            return $this->sendResponse([
                'exercises' => $exercises,
                'total' => count($exercises)
            ], '获取收藏列表成功');
        } catch (\Exception $e) {
            \Log::error('获取收藏列表失败', ['error' => $e->getMessage()]);
            return $this->sendError('获取收藏列表失败', [], 500);
        }
    }

    /**
     * 添加评论
     */
    public function addReview(Request $request, $id)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return $this->sendError('未授权访问', [], 401);
            }

            // 验证请求数据
            $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:500'
            ]);

            // 检查动作是否存在
            $exercise = $this->getExerciseById($id);
            if (!$exercise) {
                return $this->sendError('动作不存在', [], 404);
            }

            // 创建评论数据
            $review = [
                'user_id' => $user->id,
                'user_name' => $user->name ?? '用户',
                'exercise_id' => $id,
                'rating' => $request->rating,
                'comment' => $request->comment ?? '',
                'created_at' => now()->toISOString()
            ];

            // 获取现有评论
            $reviews = $user->exercise_reviews ?? [];
            
            // 检查是否已评论过此动作
            $existingIndex = null;
            foreach ($reviews as $index => $existingReview) {
                if ($existingReview['exercise_id'] === $id) {
                    $existingIndex = $index;
                    break;
                }
            }

            if ($existingIndex !== null) {
                // 更新现有评论
                $reviews[$existingIndex] = $review;
                $message = '评论更新成功';
            } else {
                // 添加新评论
                $reviews[] = $review;
                $message = '评论添加成功';
            }

            $user->exercise_reviews = $reviews;
            $user->save();

            return $this->sendResponse($review, $message);
        } catch (\Exception $e) {
            \Log::error('添加评论失败', ['error' => $e->getMessage(), 'exercise_id' => $id]);
            return $this->sendError('添加评论失败', [], 500);
        }
    }

    /**
     * 根据ID获取单个动作（内部方法）
     */
    private function getExerciseById($id)
    {
        $exercisePath = $this->getExerciseStoragePath($id);
        $dataFile = $exercisePath . '/data.json';
        
        if (!file_exists($dataFile)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($dataFile), true);
        if (!$data) {
            return null;
        }
        
        return $data;
    }
}