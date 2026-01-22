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
        // ========================================
        // AUTO-DELIVERED ORDERS SCHEDULED JOBS
        // ========================================
        
        // 🔧 Check recent shipments (shipped dalam 48 jam) - setiap 15 menit
        $schedule->command('orders:check-delivered-fixed --recent')
            ->everyFifteenMinutes()
            ->withoutOverlapping(10) // 10 minutes timeout
            ->runInBackground()
            ->onOneServer()
            ->when(function () {
                // Hanya jalan di jam kerja untuk mengurangi load
                return now()->hour >= 6 && now()->hour <= 23;
            })
            ->appendOutputTo(storage_path('logs/scheduler-delivered-recent.log'));

        // 🔧 Check older shipments - setiap jam
        $schedule->command('orders:check-delivered-fixed')
            ->hourly()
            ->withoutOverlapping(20) // 20 minutes timeout
            ->runInBackground()
            ->onOneServer()
            ->between('07:00', '22:00') // Hanya jam 7 pagi - 10 malam
            ->appendOutputTo(storage_path('logs/scheduler-delivered.log'));

        // 🔧 Cleanup job - check semua shipments daily
        $schedule->command('orders:check-delivered-fixed')
            ->daily()
            ->at('02:00') // Jam 2 pagi saat traffic rendah
            ->withoutOverlapping(30) // 30 minutes timeout
            ->appendOutputTo(storage_path('logs/scheduler-delivered-daily.log'));

        // ========================================
        // EXISTING SCHEDULED JOBS
        // ========================================
        
        // Keep existing filament excel cleanup
        $schedule->command('filament-excel:prune')
            ->daily();

        // 🔧 Optional: Test job untuk memastikan scheduler berjalan
        $schedule->call(function () {
            \Log::info('Scheduler heartbeat: ' . now()->format('Y-m-d H:i:s'));
        })->everyFiveMinutes()
          ->name('scheduler_heartbeat');
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