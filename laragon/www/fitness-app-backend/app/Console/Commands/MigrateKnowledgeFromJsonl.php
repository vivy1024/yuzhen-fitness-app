<?php

namespace App\Console\Commands;

use App\Models\Knowledge;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateKnowledgeFromJsonl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'knowledge:migrate-jsonl 
                            {--file= : JSONL文件路径}
                            {--batch-size=100 : 批处理大小}
                            {--clean : 清空现有数据}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '从JSONL文件迁移知识库数据到数据库';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $filePath = $this->option('file');
        $batchSize = (int) $this->option('batch-size');
        $clean = $this->option('clean');

        if (!$filePath) {
            $this->error('请指定JSONL文件路径: --file=path/to/file.jsonl');
            return 1;
        }

        if (!File::exists($filePath)) {
            $this->error("文件不存在: {$filePath}");
            return 1;
        }

        $this->info("开始迁移知识库数据: {$filePath}");
        $this->info("批处理大小: {$batchSize}");

        // 清空现有数据
        if ($clean) {
            $this->warn('清空现有知识库数据...');
            if ($this->confirm('确定要清空所有现有知识库数据吗？')) {
                Knowledge::truncate();
                $this->info('现有数据已清空');
            } else {
                $this->info('取消清空操作');
            }
        }

        $startTime = microtime(true);
        $totalProcessed = 0;
        $totalInserted = 0;
        $errors = 0;
        $batch = [];

        $progressBar = $this->output->createProgressBar();
        $progressBar->setFormat('详细');

        try {
            $handle = fopen($filePath, 'r');
            
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if (empty($line)) continue;

                try {
                    $data = json_decode($line, true);
                    if (!$data || !isset($data['text'])) {
                        $errors++;
                        continue;
                    }

                    $processedData = $this->processKnowledgeData($data);
                    if ($processedData) {
                        $batch[] = $processedData;
                        $totalProcessed++;
                    }

                    // 批量插入
                    if (count($batch) >= $batchSize) {
                        $inserted = $this->insertBatch($batch);
                        $totalInserted += $inserted;
                        $batch = [];
                        
                        $progressBar->advance($batchSize);
                        $progressBar->setMessage("已处理: {$totalProcessed}, 已插入: {$totalInserted}, 错误: {$errors}");
                    }

                } catch (\Exception $e) {
                    $errors++;
                    $this->warn("处理数据时出错: " . $e->getMessage());
                }
            }

            // 插入剩余数据
            if (!empty($batch)) {
                $inserted = $this->insertBatch($batch);
                $totalInserted += $inserted;
                $progressBar->advance(count($batch));
            }

            fclose($handle);
            $progressBar->finish();

        } catch (\Exception $e) {
            $this->error("迁移过程中出现错误: " . $e->getMessage());
            return 1;
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        $this->newLine(2);
        $this->info("迁移完成！");
        $this->table(
            ['统计项', '数量'],
            [
                ['总处理记录', $totalProcessed],
                ['成功插入', $totalInserted],
                ['错误记录', $errors],
                ['耗时', "{$duration}秒"],
                ['平均速度', round($totalProcessed / $duration, 2) . '条/秒'],
            ]
        );

        // 更新统计信息
        $this->updateStatistics();

        return 0;
    }

    /**
     * 处理知识数据
     */
    private function processKnowledgeData(array $data): ?array
    {
        $content = $data['text'] ?? '';
        if (empty($content) || mb_strlen($content) < 10) {
            return null;
        }

        // 提取标题
        $title = $this->extractTitle($content);
        
        // 分类内容
        $category = $this->categorizeContent($content);
        
        // 提取标签
        $tags = $this->extractTags($content);
        
        // 计算质量分数
        $qualityScore = $this->calculateQualityScore($content);

        return [
            'title' => $title,
            'content' => $content,
            'category' => $category,
            'tags' => json_encode($tags),
            'source' => 'jsonl_migration',
            'quality_score' => $qualityScore,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * 提取标题
     */
    private function extractTitle(string $content): string
    {
        // 尝试从内容开头提取标题
        $lines = explode("\n", $content);
        $firstLine = trim($lines[0]);
        
        // 如果第一行是问题格式
        if (Str::contains($firstLine, ['？', '?', '如何', '什么', '怎么', '为什么'])) {
            return mb_substr($firstLine, 0, 100);
        }
        
        // 如果第一行较短且像标题
        if (mb_strlen($firstLine) <= 50 && mb_strlen($firstLine) >= 5) {
            return $firstLine;
        }
        
        // 否则从内容中提取关键词作为标题
        $keywords = $this->extractKeywords($content);
        if (!empty($keywords)) {
            return implode(' ', array_slice($keywords, 0, 3)) . '相关知识';
        }
        
        // 最后使用内容开头
        return mb_substr($content, 0, 30) . '...';
    }

    /**
     * 内容分类
     */
    private function categorizeContent(string $content): string
    {
        $categories = [
            'training' => ['训练', '锻炼', '动作', '计划', '力量', '有氧', '无氧', '健身房'],
            'nutrition' => ['营养', '饮食', '蛋白质', '热量', '卡路里', '维生素', '矿物质', '补剂'],
            'science' => ['科学', '研究', '理论', '原理', '生理', '解剖', '运动学', '生物力学'],
            'recovery' => ['康复', '恢复', '休息', '睡眠', '按摩', '拉伸', '放松', '伤病'],
        ];

        $contentLower = mb_strtolower($content);
        $scores = [];

        foreach ($categories as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $count = mb_substr_count($contentLower, $keyword);
                $score += $count;
            }
            $scores[$category] = $score;
        }

        $maxCategory = array_keys($scores, max($scores))[0];
        return max($scores) > 0 ? $maxCategory : 'general';
    }

    /**
     * 提取标签
     */
    private function extractTags(string $content): array
    {
        $commonTags = [
            '力量训练', '有氧运动', '减脂', '增肌', '营养', '蛋白质', '碳水化合物',
            '深蹲', '卧推', '硬拉', '引体向上', '俯卧撑', '跑步', '游泳', '瑜伽',
            '胸肌', '背肌', '腿部', '肩部', '手臂', '腹肌', '核心', '柔韧性',
            '初学者', '中级', '高级', '健身房', '家庭健身', '器械', '自重',
        ];

        $tags = [];
        $contentLower = mb_strtolower($content);

        foreach ($commonTags as $tag) {
            if (mb_strpos($contentLower, mb_strtolower($tag)) !== false) {
                $tags[] = $tag;
            }
        }

        return array_unique(array_slice($tags, 0, 10)); // 最多10个标签
    }

    /**
     * 提取关键词
     */
    private function extractKeywords(string $content): array
    {
        $fitnessKeywords = [
            '训练', '锻炼', '健身', '减脂', '增肌', '力量', '有氧', '无氧',
            '蛋白质', '营养', '饮食', '热量', '肌肉', '脂肪', '深蹲', '卧推'
        ];

        $keywords = [];
        foreach ($fitnessKeywords as $keyword) {
            if (mb_strpos($content, $keyword) !== false) {
                $keywords[] = $keyword;
            }
        }

        return array_unique($keywords);
    }

    /**
     * 计算质量分数
     */
    private function calculateQualityScore(string $content): int
    {
        $score = 0;
        $length = mb_strlen($content);

        // 长度评分
        if ($length > 50) $score += 10;
        if ($length > 200) $score += 20;
        if ($length > 500) $score += 30;
        if ($length > 1000) $score += 40;

        // 专业词汇评分
        $professionalTerms = [
            '运动学', '生理学', '营养学', '训练原理', '肌肉纤维', 
            '代谢', '蛋白质合成', '糖原', '乳酸', '心率'
        ];
        
        foreach ($professionalTerms as $term) {
            if (mb_strpos($content, $term) !== false) {
                $score += 5;
            }
        }

        // 结构化内容评分
        if (preg_match('/\d+[、.]/', $content)) $score += 10; // 有序列表
        if (mb_substr_count($content, "\n") > 2) $score += 10; // 多段落

        return min($score, 100);
    }

    /**
     * 批量插入数据
     */
    private function insertBatch(array $batch): int
    {
        try {
            DB::table('knowledge')->insert($batch);
            return count($batch);
        } catch (\Exception $e) {
            $this->warn("批量插入失败: " . $e->getMessage());
            
            // 尝试逐条插入
            $inserted = 0;
            foreach ($batch as $item) {
                try {
                    DB::table('knowledge')->insert($item);
                    $inserted++;
                } catch (\Exception $e) {
                    // 忽略单条插入失败
                }
            }
            return $inserted;
        }
    }

    /**
     * 更新统计信息
     */
    private function updateStatistics(): void
    {
        $this->info("\n更新统计信息...");
        
        $stats = [
            '总记录数' => Knowledge::count(),
            '活跃记录数' => Knowledge::where('is_active', true)->count(),
            '平均质量分数' => round(Knowledge::avg('quality_score'), 2),
        ];

        $categoryStats = Knowledge::selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $this->table(['统计项', '数值'], collect($stats)->map(fn($v, $k) => [$k, $v])->toArray());
        $this->table(['分类', '数量'], collect($categoryStats)->map(fn($v, $k) => [$k, $v])->toArray());
    }
}
