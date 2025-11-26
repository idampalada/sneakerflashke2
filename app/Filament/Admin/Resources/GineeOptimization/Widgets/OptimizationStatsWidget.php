<?php

// CREATE: app/Filament/Admin/Resources/GineeOptimization/Widgets/OptimizationStatsWidget.php

namespace App\Filament\Admin\Resources\GineeOptimization\Widgets;

use App\Models\GineeSyncLog;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class OptimizationStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '10s';
    
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $since24h = now()->subDay();
        
        // Performance comparison data
        $standardSyncs = GineeSyncLog::where('created_at', '>', $since24h)
            ->where('type', 'bulk_sync_background')
            ->whereNull('summary->optimization_enabled')
            ->get();

        $optimizedSyncs = GineeSyncLog::where('created_at', '>', $since24h)
            ->whereIn('type', ['bulk_optimized_summary', 'optimized_bulk_background'])
            ->get();

        // Calculate performance metrics
        $standardSpeed = $this->calculateAverageSpeed($standardSyncs);
        $optimizedSpeed = $this->calculateAverageSpeed($optimizedSyncs);
        $performanceGain = $standardSpeed > 0 && $optimizedSpeed > 0 ? 
            round($optimizedSpeed / $standardSpeed, 1) : 0;

        // Active syncs
        $activeSyncs = GineeSyncLog::where('status', 'started')
            ->where('started_at', '>', now()->subHours(2))
            ->get();

        // Success rates
        $todaySuccessRate = $this->getSuccessRate(today());
        
        // Recent large batch performance
        $recentLargeBatch = GineeSyncLog::where('items_processed', '>', 100)
            ->where('created_at', '>', now()->subWeek())
            ->orderBy('created_at', 'desc')
            ->first();

        return [
            Stat::make('Standard Method', $standardSpeed . ' SKUs/sec')
                ->description('Last 24h average (' . $standardSyncs->count() . ' syncs)')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info')
                ->chart($this->getSpeedTrend($standardSyncs)),

            Stat::make('Optimized Method', $optimizedSpeed . ' SKUs/sec')
                ->description('Last 24h average (' . $optimizedSyncs->count() . ' syncs)')
                ->descriptionIcon('heroicon-m-rocket-launch')
                ->color('success')
                ->chart($this->getSpeedTrend($optimizedSyncs)),

            Stat::make('Performance Gain', $performanceGain > 0 ? $performanceGain . 'x' : 'No data')
                ->description($this->getPerformanceDescription($performanceGain))
                ->descriptionIcon('heroicon-m-chart-bar-square')
                ->color($this->getPerformanceColor($performanceGain)),

            Stat::make('Active Syncs', $activeSyncs->count())
                ->description($this->getActiveSyncsDescription($activeSyncs))
                ->descriptionIcon('heroicon-m-clock')
                ->color($activeSyncs->count() > 0 ? 'warning' : 'success'),

            Stat::make('Today Success Rate', $todaySuccessRate . '%')
                ->description('All sync operations today')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($todaySuccessRate >= 90 ? 'success' : ($todaySuccessRate >= 70 ? 'warning' : 'danger')),

            Stat::make('Largest Recent Batch', $recentLargeBatch ? number_format($recentLargeBatch->items_processed) . ' SKUs' : 'None')
                ->description($recentLargeBatch ? 
                    'Completed ' . $recentLargeBatch->created_at->diffForHumans() : 
                    'No large batches this week')
                ->descriptionIcon('heroicon-m-squares-2x2')
                ->color($recentLargeBatch && $recentLargeBatch->items_processed > 1000 ? 'success' : 'gray'),
        ];
    }

    private function calculateAverageSpeed($syncs): float
    {
        if ($syncs->count() === 0) return 0;

        $totalSpeed = 0;
        $validSyncs = 0;

        foreach ($syncs as $sync) {
            if ($sync->started_at && $sync->completed_at && $sync->items_processed > 0) {
                $duration = $sync->started_at->diffInSeconds($sync->completed_at);
                if ($duration > 0) {
                    $speed = $sync->items_processed / $duration;
                    $totalSpeed += $speed;
                    $validSyncs++;
                }
            }
        }

        return $validSyncs > 0 ? round($totalSpeed / $validSyncs, 2) : 0;
    }

    private function getSuccessRate($since): int
    {
        $total = GineeSyncLog::where('created_at', '>', $since)
            ->whereIn('status', ['success', 'failed', 'skipped'])
            ->count();

        $successful = GineeSyncLog::where('created_at', '>', $since)
            ->whereIn('status', ['success', 'skipped'])
            ->count();

        return $total > 0 ? round((($successful + $skipped) / $total) * 100) : 0;
    }

    private function getSpeedTrend($syncs): array
    {
        // Simple trend calculation for last few syncs
        $trend = [];
        $recent = $syncs->sortBy('created_at')->take(7);
        
        foreach ($recent as $sync) {
            if ($sync->started_at && $sync->completed_at && $sync->items_processed > 0) {
                $duration = $sync->started_at->diffInSeconds($sync->completed_at);
                $speed = $duration > 0 ? $sync->items_processed / $duration : 0;
                $trend[] = round($speed, 2);
            }
        }

        return array_slice(array_pad($trend, 7, 0), -7); // Last 7 points
    }

    private function getPerformanceDescription(float $gain): string
    {
        if ($gain <= 0) return 'Need more data for comparison';
        if ($gain >= 10) return 'Excellent performance boost!';
        if ($gain >= 5) return 'Great improvement achieved';
        if ($gain >= 2) return 'Good performance gain';
        return 'Moderate improvement';
    }

    private function getPerformanceColor(float $gain): string
    {
        if ($gain >= 5) return 'success';
        if ($gain >= 2) return 'warning';
        return 'gray';
    }

    private function getActiveSyncsDescription($activeSyncs): string
    {
        if ($activeSyncs->count() === 0) {
            return 'No active syncs running';
        }

        $totalItems = $activeSyncs->sum('items_processed');
        $oldestSync = $activeSyncs->min('started_at');
        
        $description = $activeSyncs->count() . ' sync' . ($activeSyncs->count() > 1 ? 's' : '') . ' running';
        
        if ($totalItems > 0) {
            $description .= ', ' . number_format($totalItems) . ' SKUs processed';
        }
        
        if ($oldestSync) {
            $description .= ', oldest started ' . now()->parse($oldestSync)->diffForHumans();
        }

        return $description;
    }
}