<?php

namespace App\Filament\Admin\Resources\GineeSyncLogResource\Pages;

use App\Filament\Admin\Resources\GineeSyncLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Forms;
use App\Models\GineeSyncLog;

class ListGineeSyncLogs extends ListRecords
{
    protected static string $resource = GineeSyncLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // ✅ ENHANCED CLEANUP WITH DATE RANGE
            Actions\Action::make('cleanup_old_logs')
                ->label('🗑️ Clean Sync Logs')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Clean Sync Logs')
                ->modalDescription('Select date range to delete sync logs. This will update all statistics.')
                ->form([
                    Forms\Components\Section::make('Cleanup Options')
                        ->schema([
                            Forms\Components\Select::make('cleanup_type')
                                ->label('Cleanup Type')
                                ->options([
                                    'older_than_days' => 'Delete logs older than X days',
                                    'date_range' => 'Delete logs in specific date range',
                                    'all_logs' => 'Delete ALL logs (reset to 0)',
                                    'failed_only' => 'Delete failed logs only',
                                    'dry_run_only' => 'Delete dry run logs only',
                                ])
                                ->default('older_than_days')
                                ->live()
                                ->required(),
                                
                            Forms\Components\TextInput::make('days_to_keep')
                                ->label('Keep logs from last X days')
                                ->numeric()
                                ->default(30)
                                ->minValue(1)
                                ->maxValue(365)
                                ->visible(fn ($get) => $get('cleanup_type') === 'older_than_days')
                                ->required(fn ($get) => $get('cleanup_type') === 'older_than_days'),
                                
                            Forms\Components\DatePicker::make('from_date')
                                ->label('From Date')
                                ->visible(fn ($get) => $get('cleanup_type') === 'date_range')
                                ->required(fn ($get) => $get('cleanup_type') === 'date_range'),
                                
                            Forms\Components\DatePicker::make('to_date')
                                ->label('To Date')
                                ->visible(fn ($get) => $get('cleanup_type') === 'date_range')
                                ->required(fn ($get) => $get('cleanup_type') === 'date_range'),
                                
                            Forms\Components\Placeholder::make('warning')
                                ->label('⚠️ Warning')
                                ->content(fn ($get) => match($get('cleanup_type')) {
                                    'all_logs' => 'This will delete ALL sync logs and reset statistics to 0!',
                                    'date_range' => 'This will delete all logs between selected dates.',
                                    'older_than_days' => 'This will delete logs older than selected days.',
                                    'failed_only' => 'This will delete only failed sync logs.',
                                    'dry_run_only' => 'This will delete only dry run logs.',
                                    default => 'Please select cleanup type.'
                                })
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                        
                    Forms\Components\Section::make('Preview (Before Deletion)')
                        ->schema([
                            Forms\Components\Placeholder::make('preview_stats')
                                ->label('Logs to be deleted')
                                ->content(function ($get) {
                                    return $this->getPreviewStats($get());
                                })
                                ->columnSpanFull(),
                        ]),
                ])
                ->action(function (array $data) {
                    $deleted = $this->performCleanup($data);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('✅ Cleanup Completed')
                        ->body("Deleted {$deleted} log entries. Statistics updated.")
                        ->success()
                        ->duration(8000)
                        ->send();
                        
                    // Refresh the page to update statistics
                    redirect(request()->header('Referer'));
                }),

            // ✅ DATE RANGE VIEWER
            Actions\Action::make('view_by_date')
                ->label('📅 View by Date')
                ->icon('heroicon-o-calendar-days')
                ->color('info')
                ->form([
                    Forms\Components\Section::make('Date Range Statistics')
                        ->schema([
                            Forms\Components\DatePicker::make('view_from_date')
                                ->label('From Date')
                                ->default(now()->subWeek())
                                ->live(),
                                
                            Forms\Components\DatePicker::make('view_to_date')
                                ->label('To Date')
                                ->default(now())
                                ->live(),
                                
                            Forms\Components\Placeholder::make('date_stats')
                                ->label('Statistics for Selected Range')
                                ->content(function ($get) {
                                    return $this->getDateRangeStats($get('view_from_date'), $get('view_to_date'));
                                })
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ])
                ->action(function (array $data) {
                    // Apply date filter to the table
                    $this->tableFilters = [
                        'created_at' => [
                            'created_from' => $data['view_from_date'],
                            'created_until' => $data['view_to_date'],
                        ]
                    ];
                    
                    \Filament\Notifications\Notification::make()
                        ->title('📅 Date Filter Applied')
                        ->body("Showing logs from {$data['view_from_date']} to {$data['view_to_date']}")
                        ->info()
                        ->send();
                }),

            // ✅ EXPORT LOGS
            Actions\Action::make('export_logs')
                ->label('📊 Export Logs')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->form([
                    Forms\Components\Section::make('Export Options')
                        ->schema([
                            Forms\Components\Select::make('export_range')
                                ->label('Export Range')
                                ->options([
                                    'all' => 'All logs',
                                    'today' => 'Today only',
                                    'week' => 'This week',
                                    'month' => 'This month',
                                    'custom' => 'Custom date range',
                                ])
                                ->default('week')
                                ->live(),
                                
                            Forms\Components\DatePicker::make('export_from')
                                ->label('From Date')
                                ->visible(fn ($get) => $get('export_range') === 'custom'),
                                
                            Forms\Components\DatePicker::make('export_to')
                                ->label('To Date')
                                ->visible(fn ($get) => $get('export_range') === 'custom'),
                                
                            Forms\Components\CheckboxList::make('export_columns')
                                ->label('Columns to Export')
                                ->options([
                                    'created_at' => 'Date/Time',
                                    'operation_type' => 'Operation',
                                    'sku' => 'SKU',
                                    'product_name' => 'Product Name',
                                    'status' => 'Status',
                                    'old_stock' => 'Old Stock',
                                    'new_stock' => 'New Stock',
                                    'change' => 'Change',
                                    'method_used' => 'Method',
                                    'message' => 'Message',
                                    'session_id' => 'Session ID',
                                ])
                                ->default(['created_at', 'operation_type', 'sku', 'product_name', 'status', 'old_stock', 'new_stock', 'change'])
                                ->columns(2),
                        ]),
                ])
                ->action(function (array $data) {
                    return $this->exportLogs($data);
                }),

            // ✅ REFRESH STATISTICS
            Actions\Action::make('refresh_stats')
                ->label('🔄 Refresh Stats')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function () {
                    // Clear any cached statistics
                    cache()->forget('ginee_sync_stats');
                    
                    \Filament\Notifications\Notification::make()
                        ->title('🔄 Statistics Refreshed')
                        ->body('All statistics have been recalculated')
                        ->info()
                        ->send();
                        
                    redirect(request()->header('Referer'));
                }),
        ];
    }

    /**
     * ✅ GET PREVIEW STATS BEFORE DELETION
     */
    protected function getPreviewStats(array $data): string
    {
        $query = GineeSyncLog::query();
        
        switch ($data['cleanup_type']) {
            case 'older_than_days':
                $days = $data['days_to_keep'] ?? 30;
                $query->where('created_at', '<', now()->subDays($days));
                break;
                
            case 'date_range':
                if (!empty($data['from_date']) && !empty($data['to_date'])) {
                    $query->whereBetween('created_at', [$data['from_date'], $data['to_date']]);
                }
                break;
                
            case 'all_logs':
                // All logs will be deleted
                break;
                
            case 'failed_only':
                $query->where('status', 'failed');
                break;
                
            case 'dry_run_only':
                $query->where('dry_run', true);
                break;
        }
        
        $total = $query->count();
        $successful = $query->where('status', 'success')->count();
        $failed = $query->where('status', 'failed')->count();
        $dryRun = $query->where('dry_run', true)->count();
        
        return "📊 **{$total}** total logs will be deleted\n" .
               "✅ {$successful} successful logs\n" .
               "❌ {$failed} failed logs\n" .
               "🧪 {$dryRun} dry run logs";
    }

    /**
     * ✅ PERFORM CLEANUP BASED ON SELECTED OPTIONS
     */
    protected function performCleanup(array $data): int
    {
        $query = GineeSyncLog::query();
        
        switch ($data['cleanup_type']) {
            case 'older_than_days':
                $days = $data['days_to_keep'] ?? 30;
                $query->where('created_at', '<', now()->subDays($days));
                break;
                
            case 'date_range':
                if (!empty($data['from_date']) && !empty($data['to_date'])) {
                    $query->whereBetween('created_at', [$data['from_date'], $data['to_date']]);
                }
                break;
                
            case 'all_logs':
                // Delete all logs - no additional conditions
                break;
                
            case 'failed_only':
                $query->where('status', 'failed');
                break;
                
            case 'dry_run_only':
                $query->where('dry_run', true);
                break;
        }
        
        return $query->delete();
    }

    /**
     * ✅ GET DATE RANGE STATISTICS
     */
    protected function getDateRangeStats($fromDate, $toDate): string
    {
        if (!$fromDate || !$toDate) {
            return "Please select both from and to dates.";
        }
        
        $query = GineeSyncLog::whereBetween('created_at', [$fromDate, $toDate]);
        
        $total = $query->count();
        $successful = $query->whereIn('status', ['success', 'skipped'])->count();
        $failed = $query->where('status', 'failed')->count();
        $skipped = $query->where('status', 'skipped')->count();
        $successRate = $total > 0 ? round((($successful + $skipped) / $total) * 100, 1) : 0;

        
        $uniqueSkus = $query->whereNotNull('sku')->distinct('sku')->count();
        $uniqueSessions = $query->whereNotNull('session_id')->distinct('session_id')->count();
        
        return "📊 **Statistics for {$fromDate} to {$toDate}**\n\n" .
               "📈 **Total Operations:** {$total}\n" .
               "✅ **Successful:** {$successful}\n" .
               "❌ **Failed:** {$failed}\n" .
               "⏭️ **Skipped:** {$skipped}\n" .
               "📊 **Success Rate:** {$successRate}%\n\n" .
               "🏷️ **Unique SKUs:** {$uniqueSkus}\n" .
               "🔄 **Sync Sessions:** {$uniqueSessions}";
    }

    /**
     * ✅ EXPORT LOGS TO CSV
     */
    protected function exportLogs(array $data)
    {
        $query = GineeSyncLog::query();
        
        // Apply date range filter
        switch ($data['export_range']) {
            case 'today':
                $query->whereDate('created_at', now()->toDateString());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                break;
            case 'custom':
                if (!empty($data['export_from']) && !empty($data['export_to'])) {
                    $query->whereBetween('created_at', [$data['export_from'], $data['export_to']]);
                }
                break;
        }
        
        $logs = $query->orderBy('created_at', 'desc')->limit(10000)->get();
        $columns = $data['export_columns'] ?? [];
        
        return response()->streamDownload(function () use ($logs, $columns) {
            $handle = fopen('php://output', 'w');
            
            // Write header
            $headers = [];
            foreach ($columns as $column) {
                $headers[] = match($column) {
                    'created_at' => 'Date/Time',
                    'operation_type' => 'Operation',
                    'sku' => 'SKU',
                    'product_name' => 'Product Name',
                    'status' => 'Status',
                    'old_stock' => 'Old Stock',
                    'new_stock' => 'New Stock',
                    'change' => 'Change',
                    'method_used' => 'Method Used',
                    'message' => 'Message',
                    'session_id' => 'Session ID',
                    default => ucfirst(str_replace('_', ' ', $column))
                };
            }
            fputcsv($handle, $headers);
            
            // Write data
            foreach ($logs as $log) {
                $row = [];
                foreach ($columns as $column) {
                    $row[] = match($column) {
                        'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
                        'change' => $log->change ?? ($log->new_stock - $log->old_stock),
                        default => $log->{$column} ?? ''
                    };
                }
                fputcsv($handle, $row);
            }
            
            fclose($handle);
        }, 'ginee_sync_logs_' . $data['export_range'] . '_' . now()->format('Y-m-d_H-i-s') . '.csv');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            GineeSyncLogResource\Widgets\SyncStatsWidget::class,
        ];
    }
}

