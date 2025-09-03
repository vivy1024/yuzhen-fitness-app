<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Exercise;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportMuscleWikiData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'musclewiki:import
                            {--path=F:\build_body\data\musclewiki\exercises : MuscleWiki数据目录路径}
                            {--force : 强制重新导入，清空现有数据}
                            {--dry-run : 仅预览导入，不实际执行}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '导入MuscleWiki健身动作数据到Laravel数据库';

    /**
     * 统计信息
     */
    private $stats = [
        'total_files' => 0,
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 开始导入MuscleWiki数据...');

        $dataPath = $this->option('path');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        // 验证数据路径
        if (!File::exists($dataPath)) {
            $this->error("❌ 数据目录不存在: {$dataPath}");
            return 1;
        }

        // 强制重新导入时清空数据
        if ($force && !$dryRun) {
            if ($this->confirm('⚠️  确定要清空现有的动作数据吗？')) {
                $this->info('🗑️  清空现有数据...');
                Exercise::truncate();
            }
        }

        // 获取所有肌肉群目录
        $muscleGroups = $this->getMuscleGroups($dataPath);

        if (empty($muscleGroups)) {
            $this->error('❌ 未找到任何肌肉群数据目录');
            return 1;
        }

        $this->info("📁 找到 " . count($muscleGroups) . " 个肌肉群目录");

        // 创建进度条
        $progressBar = $this->output->createProgressBar(0);
        $progressBar->setFormat('verbose');

        // 处理每个肌肉群
        foreach ($muscleGroups as $muscleGroup) {
            $this->processMusclGroup($muscleGroup, $dataPath, $dryRun, $progressBar);
        }

        $progressBar->finish();
        $this->newLine(2);

        // 显示统计信息
        $this->displayStats($dryRun);

        return 0;
    }

    /**
     * 获取所有肌肉群目录
     */
    private function getMuscleGroups(string $dataPath): array
    {
        $muscleGroups = [];
        $directories = File::directories($dataPath);

        foreach ($directories as $dir) {
            $muscleGroup = basename($dir);
            if ($this->isValidMuscleGroup($muscleGroup)) {
                $muscleGroups[] = $muscleGroup;
            }
        }

        return $muscleGroups;
    }

    /**
     * 验证是否为有效的肌肉群目录
     */
    private function isValidMuscleGroup(string $muscleGroup): bool
    {
        $validGroups = [
            'chest', 'back', 'shoulders', 'biceps', 'triceps',
            'legs', 'glutes', 'calves', 'core', 'forearms'
        ];

        return in_array($muscleGroup, $validGroups);
    }

    /**
     * 处理单个肌肉群
     */
    private function processMusclGroup(string $muscleGroup, string $dataPath, bool $dryRun, $progressBar)
    {
        $groupPath = $dataPath . DIRECTORY_SEPARATOR . $muscleGroup;
        $files = File::glob($groupPath . DIRECTORY_SEPARATOR . '*.json');

        $this->info("\n🔄 处理肌肉群: {$muscleGroup} (" . count($files) . " 个动作)");

        $progressBar->setMaxSteps($this->stats['total_files'] + count($files));
        $this->stats['total_files'] += count($files);

        foreach ($files as $file) {
            $this->processExerciseFile($file, $muscleGroup, $dryRun);
            $progressBar->advance();
        }
    }

    /**
     * 处理单个动作文件
     */
    private function processExerciseFile(string $filePath, string $muscleGroup, bool $dryRun)
    {
        try {
            $content = File::get($filePath);
            $data = json_decode($content, true);

            if (!$data) {
                $this->stats['errors']++;
                $this->warn("⚠️  无法解析JSON文件: " . basename($filePath));
                return;
            }

            // 验证必需字段
            if (!$this->validateExerciseData($data)) {
                $this->stats['errors']++;
                $this->warn("⚠️  数据验证失败: " . basename($filePath));
                return;
            }

            // 转换数据格式
            $exerciseData = $this->transformExerciseData($data, $muscleGroup);

            if ($dryRun) {
                $this->line("📋 [DRY-RUN] 将导入: {$exerciseData['name']} ({$exerciseData['musclewiki_id']})");
                $this->stats['imported']++;
                return;
            }

            // 检查是否已存在
            $existing = Exercise::where('musclewiki_id', $exerciseData['musclewiki_id'])->first();

            if ($existing) {
                $existing->update($exerciseData);
                $this->stats['updated']++;
            } else {
                Exercise::create($exerciseData);
                $this->stats['imported']++;
            }

        } catch (\Exception $e) {
            $this->stats['errors']++;
            $this->error("❌ 处理文件失败: " . basename($filePath) . " - " . $e->getMessage());
        }
    }

    /**
     * 验证动作数据
     */
    private function validateExerciseData(array $data): bool
    {
        $required = ['id', 'name', 'muscle_group'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 转换数据格式
     */
    private function transformExerciseData(array $data, string $muscleGroup): array
    {
        return [
            'musclewiki_id' => $data['id'],
            'name' => $data['name'] ?? '',
            'english_name' => $data['name_en'] ?? $data['english_name'] ?? '',
            'name_en' => $data['name_en'] ?? $data['english_name'] ?? '',
            'primary_muscle' => $muscleGroup,
            'secondary_muscles' => $data['muscle_group']['secondary'] ?? [],
            'equipment_required' => $data['equipment']['required'] ?? 'bodyweight',
            'equipment_alternatives' => $data['equipment']['alternatives'] ?? [],
            'difficulty_level' => $data['difficulty']['level'] ?? 'beginner',
            'difficulty_rating' => $data['difficulty']['rating'] ?? null,
            'instructions_setup' => $data['instructions']['setup'] ?? [],
            'instructions_execution' => $data['instructions']['execution'] ?? [],
            'instructions_breathing' => $data['instructions']['breathing'] ?? '',
            'form_cues' => $data['tips']['form_cues'] ?? [],
            'common_mistakes' => $data['tips']['common_mistakes'] ?? [],
            'video_urls' => $data['media']['video_urls'] ?? [],
            'image_urls' => $data['media']['image_urls'] ?? [],
            'video_local_paths' => $data['media']['video_local_paths'] ?? [],
            'image_local_paths' => $data['media']['image_local_paths'] ?? [],
            'tags' => $data['tags'] ?? [],
            'benefits' => $data['benefits'] ?? [],
            'variations' => $data['variations'] ?? [],
            'notes' => $data['notes'] ?? '',
            'is_active' => true,
            'view_count' => 0,
            'rating' => 0.00,
            'rating_count' => 0,
        ];
    }

    /**
     * 显示统计信息
     */
    private function displayStats(bool $dryRun)
    {
        $this->info('📊 导入统计:');
        $this->table(
            ['项目', '数量'],
            [
                ['总文件数', $this->stats['total_files']],
                ['新增动作', $this->stats['imported']],
                ['更新动作', $this->stats['updated']],
                ['跳过文件', $this->stats['skipped']],
                ['错误数量', $this->stats['errors']],
            ]
        );

        if ($dryRun) {
            $this->warn('⚠️  这是预览模式，未实际导入数据');
        } else {
            $successCount = $this->stats['imported'] + $this->stats['updated'];
            $this->info("✅ 成功处理 {$successCount} 个动作");

            if ($this->stats['errors'] > 0) {
                $this->warn("⚠️  有 {$this->stats['errors']} 个文件处理失败");
            }
        }
    }
}
