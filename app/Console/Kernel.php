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
        
        // Check recent shipments (shipped dalam 48 jam) - lebih sering
        $schedule->command('orders:check-delivered --recent')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer() // Jika multiple servers
            ->when(function () {
                // Hanya jalan di jam kerja untuk mengurangi load
                return now()->hour >= 6 && now()->hour <= 23;
            });

        // Check older shipments - lebih jarang
        $schedule->command('orders:check-delivered')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground()
            ->onOneServer()
            ->between('07:00', '22:00'); // Hanya jam 7 pagi - 10 malam

        // Cleanup job - check stuck shipments (shipped > 7 hari)
        $schedule->command('orders:check-delivered')
            ->daily()
            ->at('02:00') // Jam 2 pagi saat traffic rendah
            ->withoutOverlapping();

        // ========================================
        // EXISTING SCHEDULED JOBS (jika ada)
        // ========================================
        
        // Contoh jobs lain yang mungkin Anda punya:
        // $schedule->command('inspire')->hourly();
        
        // Model pruning (untuk clean up data lama)
        // $schedule->command('model:prune')->daily();
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