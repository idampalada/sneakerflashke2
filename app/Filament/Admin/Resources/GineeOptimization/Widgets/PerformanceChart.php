<?php

// CREATE: app/Filament/Admin/Resources/GineeOptimization/Widgets/PerformanceChart.php

namespace App\Filament\Admin\Resources\GineeOptimization\Widgets;

use App\Models\GineeSyncLog;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PerformanceChart extends ChartWidget
{
    protected static ?string $heading = '📊 Sync Performance Trends (Last 7 Days)';
    
    protected static ?int $sort = 2;
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        // Generate last 7 days
        $days = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $days->push([
                'date' => $date->format('M j'),
                'full_date' => $date->format('Y-m-d'),
            ]);
        }

        $standardPerformance = [];
        $optimizedPerformance = [];
        $syncCounts = [];
        $labels = [];

        foreach ($days as $day) {
            $labels[] = $day['date'];
            
            // Get daily sync performance data
            $dailyStandard = GineeSyncLog::whereDate('created_at', $day['full_date'])
                ->where('type', 'bulk_sync_background')
                ->whereNull('summary->optimization_enabled')
                ->get();

            $dailyOptimized = GineeSyncLog::whereDate('created_at', $day['full_date'])
                ->whereIn('type', ['bulk_optimized_summary', 'optimized_bulk_background'])
                ->get();

            // Calculate average speeds
            $standardSpeed = $this->calculateDailyAverageSpeed($dailyStandard);
            $optimizedSpeed = $this->calculateDailyAverageSpeed($dailyOptimized);
            
            $standardPerformance[] = $standardSpeed;
            $optimizedPerformance[] = $optimizedSpeed;
            $syncCounts[] = $dailyStandard->count() + $dailyOptimized->count();
        }

        return [
            'datasets' => [
                [
                    'label' => '🔄 Standard Method (SKUs/sec)',
                    'data' => $standardPerformance,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'borderWidth' => 3,
                    'fill' => true,
                    'tension' => 0.4,
                    'pointBackgroundColor' => 'rgb(59, 130, 246)',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 5,
                ],
                [
                    'label' => '🚀 Optimized Method (SKUs/sec)',
                    'data' => $optimizedPerformance,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderWidth' => 3,
                    'fill' => true,
                    'tension' => 0.4,
                    'pointBackgroundColor' => 'rgb(16, 185, 129)',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 5,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 20,
                    ],
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                    'backgroundColor' => 'rgba(0, 0, 0, 0.8)',
                    'titleColor' => '#fff',
                    'bodyColor' => '#fff',
                    'borderColor' => 'rgba(255, 255, 255, 0.1)',
                    'borderWidth' => 1,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Speed (SKUs per second)',
                        'font' => [
                            'weight' => 'bold',
                        ],
                    ],
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.1)',
                    ],
                ],
                'x' => [
                    'title' => [
                        'display' => true,
                        'text' => 'Date',
                        'font' => [
                            'weight' => 'bold',
                        ],
                    ],
                    'grid' => [
                        'color' => 'rgba(0, 0, 0, 0.1)',
                    ],
                ],
            ],
            'interaction' => [
                'mode' => 'nearest',
                'axis' => 'x',
                'intersect' => false,
            ],
        ];
    }

    private function calculateDailyAverageSpeed($syncs): float
    {
        if ($syncs->count() === 0) return 0;

        $totalSpeed = 0;
        $validSyncs = 0;

        foreach ($syncs as $sync) {
            if ($sync->started_at && $sync->completed_at && $sync->items_processed > 0) {
                $duration = max($sync->started_at->diffInSeconds($sync->completed_at), 1);
                $speed = $sync->items_processed / $duration;
                $totalSpeed += $speed;
                $validSyncs++;
            }
        }

        return $validSyncs > 0 ? round($totalSpeed / $validSyncs, 2) : 0;
    }
}

// CREATE: app/Filament/Admin/Resources/GineeOptimization/Widgets/BulkSyncForm.php

namespace App\Filament\Admin\Resources\GineeOptimization\Widgets;

use Filament\Widgets\Widget;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use App\Jobs\OptimizedBulkGineeSyncJob;
use Illuminate\Support\Facades\Log;

class BulkSyncForm extends Widget implements HasForms
{
    use InteractsWithForms;
    
    protected static string $view = 'filament.admin.resources.ginee-optimization.widgets.bulk-sync-form';
    
    protected int | string | array $columnSpan = 'full';
    
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'sync_skus' => '',
            'dry_run' => true,
            'chunk_size' => 50,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('🚀 Quick Bulk Sync')
                    ->description('Start optimized sync directly from dashboard')
                    ->schema([
                        Textarea::make('sync_skus')
                            ->label('SKU List')
                            ->placeholder("Paste your SKUs here:\nBOX, 197375689975, SKU001\nor one per line:\nBOX\n197375689975\nSKU001")
                            ->rows(6)
                            ->required()
                            ->helperText('Enter all your SKUs - system will auto-optimize for best performance'),
                            
                        Toggle::make('dry_run')
                            ->label('🧪 Dry Run Mode')
                            ->default(true)
                            ->helperText('Recommended for first test - preview changes without updating data')
                            ->inline(false),
                            
                        Select::make('chunk_size')
                            ->label('Performance Setting')
                            ->options([
                                25 => '🐌 Conservative (25 SKUs/batch) - Most stable',
                                50 => '⚡ Recommended (50 SKUs/batch) - Balanced',
                                75 => '🚀 Fast (75 SKUs/batch) - Higher performance', 
                                100 => '💨 Maximum (100 SKUs/batch) - Fastest',
                            ])
                            ->default(50)
                            ->helperText('Higher settings = faster processing, lower settings = more stable'),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function startSync(): void
    {
        try {
            $data = $this->form->getState();
            
            // Parse SKUs
            $skus = array_filter(array_map('trim', preg_split('/[,\n\r\t\s]+/', $data['sync_skus'])));
            $skus = array_unique($skus);

            if (empty($skus)) {
                Notification::make()
                    ->title('❌ No Valid SKUs')
                    ->body('Please enter at least one valid SKU')
                    ->warning()
                    ->send();
                return;
            }

            $sessionId = \Illuminate\Support\Str::uuid();
            $estimatedTime = ceil(count($skus) / 15);

            // Dispatch optimized job
            OptimizedBulkGineeSyncJob::dispatch($skus, [
                'session_id' => $sessionId,
                'dry_run' => $data['dry_run'],
                'chunk_size' => $data['chunk_size'],
                'user_id' => auth()->id(),
                'initiated_from' => 'dashboard_widget'
            ]);

            // Success notification
            Notification::make()
                ->title($data['dry_run'] ? '🧪 Dry Run Started' : '🚀 Sync Started')
                ->body($this->getSuccessMessage($sessionId, count($skus), $estimatedTime, $data['dry_run']))
                ->success()
                ->persistent()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('viewLogs')
                        ->label('📋 Monitor Progress')
                        ->url(route('filament.admin.resources.ginee-sync-logs.index'))
                        ->openUrlInNewTab(),
                ])
                ->send();

            // Reset form
            $this->form->fill([
                'sync_skus' => '',
                'dry_run' => true, 
                'chunk_size' => 50,
            ]);

            Log::info('🚀 [Widget] Quick sync started', [
                'session_id' => $sessionId,
                'sku_count' => count($skus),
                'dry_run' => $data['dry_run']
            ]);

        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Sync Failed')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();

            Log::error('❌ [Widget] Quick sync failed: ' . $e->getMessage());
        }
    }

    private function getSuccessMessage(string $sessionId, int $skuCount, int $estimatedTime, bool $dryRun): string
    {
        $message = "**Session:** " . substr($sessionId, 0, 8) . "...\n";
        $message .= "**SKUs:** " . number_format($skuCount) . "\n";
        $message .= "**Estimated:** ~{$estimatedTime} minutes\n";
        
        if ($dryRun) {
            $message .= "**Mode:** 🧪 PREVIEW ONLY - No data will be changed\n";
        } else {
            $message .= "**Mode:** ⚡ LIVE SYNC - Data will be updated\n";
        }
        
        $message .= "\n**Optimization:** Up to 10-20x faster than standard sync!";
        
        return $message;
    }
}