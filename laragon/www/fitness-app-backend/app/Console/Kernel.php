<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        
        // 定时任务调度
        $schedule->command('cache:prune-stale-tags')->hourly();
        $schedule->command('queue:prune-batches')->daily();
        
        // 自定义定时任务
        $schedule->call(function () {
            // 清理过期缓存
            app(\App\Services\CacheService::class)->cleanupExpiredCache();
        })->daily();

        $schedule->call(function () {
            // 数据库优化
            app(\App\Services\DatabaseOptimizationService::class)->cleanupExpiredData();
        })->weekly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
