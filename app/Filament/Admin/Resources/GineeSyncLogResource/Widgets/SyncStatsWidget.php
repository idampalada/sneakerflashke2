<?php

namespace App\Filament\Admin\Resources\GineeSyncLogResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\GineeSyncLog;

class SyncStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // ✅ REAL-TIME STATISTICS (no caching for immediate update after cleanup)
        $totalSyncs = GineeSyncLog::count();
        $todaysSyncs = GineeSyncLog::whereDate('created_at', now()->toDateString())->count();
        $thisWeekSyncs = GineeSyncLog::whereBetween('created_at', [
            now()->startOfWeek(), 
            now()->endOfWeek()
        ])->count();
        
        $totalWithStatus = GineeSyncLog::whereIn('status', ['success', 'failed', 'skipped'])->count();
// ✅ BENAR - hitung 'success' DAN 'skipped'
$successfulSyncs = GineeSyncLog::whereIn('status', ['success', 'skipped'])->count();
$successRate = $totalWithStatus > 0 ? round(($successfulSyncs / $totalWithStatus) * 100, 1) : 0;
        
        return [
            Stat::make('Total Syncs', number_format($totalSyncs))
                ->description('All time operations')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),
                
            Stat::make("Today's Syncs", number_format($todaysSyncs))
                ->description('Operations today')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info'),
                
            Stat::make('Success Rate', $successRate . '%')
                ->description($this->getSuccessRateDescription($successRate))
                ->descriptionIcon($successRate >= 80 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($this->getSuccessRateColor($successRate)),
                
            Stat::make('This Week', number_format($thisWeekSyncs))
                ->description('Weekly operations')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('success'),
        ];
    }
    
    protected function getSuccessRateDescription(float $rate): string
    {
        return match(true) {
            $rate >= 90 => 'Excellent',
            $rate >= 80 => 'Good',
            $rate >= 70 => 'Fair',
            $rate >= 50 => 'Needs Improvement',
            default => 'Poor'
        };
    }
    
    protected function getSuccessRateColor(float $rate): string
    {
        return match(true) {
            $rate >= 80 => 'success',
            $rate >= 60 => 'warning',
            default => 'danger'
        };
    }
}