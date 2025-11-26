<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\GineeSyncLogResource\Pages;
use App\Filament\Admin\Resources\GineeSyncLogResource\RelationManagers;
use App\Models\GineeSyncLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\MaxWidth;

class GineeSyncLogResource extends Resource
{
    protected static ?string $model = GineeSyncLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Ginee Sync Logs';

    protected static ?string $modelLabel = 'Sync Log';

    protected static ?string $pluralModelLabel = 'Sync Logs';

    protected static ?string $navigationGroup = 'Ginee Management';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('product_name')
                            ->label('Product Name')
                            ->maxLength(255),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'success' => 'Success',
                                'failed' => 'Failed',
                                'pending' => 'Pending',
                            ])
                            ->required(),

                        Forms\Components\Select::make('operation_type')
                            ->label('Operation Type')
                            ->options([
                                'sync' => 'Sync',
                                'push' => 'Push',
                                'both' => 'Both',
                            ])
                            ->default('sync'),

                        Forms\Components\Select::make('type')
                            ->label('Type')
                            ->options([
                                'individual_sync' => 'Individual Sync',
                                'bulk_sync' => 'Bulk Sync',
                                'bulk_optimized_sync' => 'Bulk Optimized Sync',
                                'bulk_sync_summary' => 'Bulk Sync Summary',
                            ])
                            ->required(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Stock Information')
                    ->schema([
                        Forms\Components\TextInput::make('old_stock')
                            ->label('Old Stock')
                            ->numeric()
                            ->default(0),

                        Forms\Components\TextInput::make('new_stock')
                            ->label('New Stock')
                            ->numeric()
                            ->default(0),

                        Forms\Components\TextInput::make('change')
                            ->label('Change')
                            ->numeric()
                            ->default(0)
                            ->helperText('Auto-calculated: new_stock - old_stock'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Toggle::make('dry_run')
                            ->label('Dry Run')
                            ->default(false),

                        Forms\Components\TextInput::make('session_id')
                            ->label('Session ID')
                            ->maxLength(255),

                        Forms\Components\Textarea::make('message')
                            ->label('Message')
                            ->rows(2),

                        Forms\Components\Textarea::make('error_message')
                            ->label('Error Message')
                            ->rows(2),

                        Forms\Components\KeyValue::make('metadata')
                            ->label('Metadata')
                            ->keyLabel('Key')
                            ->valueLabel('Value'),
                    ])
                    ->columns(2),
            ]);
    }

public static function table(Table $table): Table
{
    return $table
        ->columns([
            // Date Column
            Tables\Columns\TextColumn::make('created_at')
                ->label('Date')
                ->date('M d, Y')
                ->sortable()
                ->toggleable(),

            // ✅ FIXED: Time Column - remove ->since()
            Tables\Columns\TextColumn::make('created_at')
                ->label('Time')
                ->formatStateUsing(function ($state, $record) {
                    return $record->created_at->format('H:i:s');
                })
                ->tooltip(function ($record) {
                    return $record->created_at->format('Y-m-d H:i:s');
                })
                ->sortable(),

            // ✅ FIXED: Operation Column - use operation_type field from database
            Tables\Columns\TextColumn::make('operation_type')
                ->label('Operation')
                ->badge()
                ->formatStateUsing(function ($state, $record) {
                    // Show actual operation type + dry run status
                    $operationType = match($state) {
                        'stock_push' => 'Stock Push',
                        'sync' => 'Sync',
                        'push' => 'Push',
                        'test' => 'Test',
                        default => ucfirst($state ?? 'Unknown')
                    };
                    
                    // Add dry run indicator
                    if ($record->dry_run) {
                        return "🧪 {$operationType}";
                    }
                    
                    return $operationType;
                })
                ->color(function ($record) {
                    if ($record->dry_run) {
                        return 'gray';
                    }
                    
                    return match($record->operation_type) {
                        'stock_push' => 'primary',
                        'sync' => 'success',
                        'push' => 'warning',
                        'test' => 'info',
                        default => 'secondary'
                    };
                })
                ->icon(function ($record) {
                    if ($record->dry_run) {
                        return 'heroicon-o-eye';
                    }
                    
                    return match($record->operation_type) {
                        'stock_push' => 'heroicon-o-arrow-down-circle',
                        'sync' => 'heroicon-o-arrow-path',
                        'push' => 'heroicon-o-arrow-up-circle', 
                        'test' => 'heroicon-o-beaker',
                        default => 'heroicon-o-cog'
                    };
                })
                ->sortable()
                ->searchable(),

            // SKU
            Tables\Columns\TextColumn::make('sku')
                ->label('SKU')
                ->searchable()
                ->sortable()
                ->copyable()
                ->weight('bold')
                ->limit(20)
                ->color('primary'),

            // Product Name
            Tables\Columns\TextColumn::make('product_name')
                ->label('Product')
                ->searchable()
                ->limit(25)
                ->tooltip(function ($record) {
                    return $record->product_name ?? 'No product name';
                })
                ->wrap(),

            // Status with Smart Logic
            Tables\Columns\BadgeColumn::make('status')
                ->label('Status')
                ->colors([
                    'success' => 'success',
                    'failed' => 'danger',
                    'pending' => 'warning',
                    'skipped' => 'gray',
                    'would_update' => 'info',
                ])
                ->icons([
                    'success' => 'heroicon-o-check-circle',
                    'failed' => 'heroicon-o-x-circle',
                    'pending' => 'heroicon-o-clock',
                    'skipped' => 'heroicon-o-arrow-right-circle',
                    'would_update' => 'heroicon-o-arrow-up-circle',
                ])
                ->formatStateUsing(function ($state, $record) {
                    if ($record->dry_run && $state === 'success') {
                        $change = $record->change;
                        if (is_null($change) && !is_null($record->old_stock) && !is_null($record->new_stock)) {
                            $change = $record->new_stock - $record->old_stock;
                        }
                        
                        return $change == 0 ? 'Skipped' : 'Would Update';
                    }
                    return ucfirst($state);
                })
                ->sortable(),

            // Old Stock
            Tables\Columns\TextColumn::make('old_stock')
                ->label('Old Stock')
                ->numeric()
                ->sortable()
                ->alignCenter()
                ->badge()
                ->color('gray')
                ->formatStateUsing(function ($state) {
                    return $state ?? '0';
                })
                ->tooltip('Stock from local database'),

            // New Stock
            Tables\Columns\TextColumn::make('new_stock')
                ->label('New Stock')
                ->numeric()
                ->sortable() 
                ->alignCenter()
                ->badge()
                ->color('info')
                ->formatStateUsing(function ($state) {
                    return $state ?? '0';
                })
                ->tooltip('Stock from Ginee API'),

            // Change with Color
            Tables\Columns\TextColumn::make('change')
                ->label('Change')
                ->numeric()
                ->sortable()
                ->alignCenter()
                ->badge()
                ->formatStateUsing(function ($state, $record) {
                    if (is_null($state)) {
                        if (!is_null($record->old_stock) && !is_null($record->new_stock)) {
                            $state = $record->new_stock - $record->old_stock;
                        } else {
                            return 'N/A';
                        }
                    }
                    
                    if ($state == 0) {
                        return '0';
                    }
                    
                    $prefix = $state > 0 ? '+' : '';
                    return "{$prefix}{$state}";
                })
                ->color(function ($state, $record) {
                    if (is_null($state)) {
                        if (!is_null($record->old_stock) && !is_null($record->new_stock)) {
                            $state = $record->new_stock - $record->old_stock;
                        }
                    }
                    
                    if ($state > 0) return 'success';
                    if ($state < 0) return 'danger';
                    return 'gray';
                })
                ->icon(function ($state, $record) {
                    if (is_null($state)) {
                        if (!is_null($record->old_stock) && !is_null($record->new_stock)) {
                            $state = $record->new_stock - $record->old_stock;
                        }
                    }
                    
                    if ($state > 0) return 'heroicon-o-arrow-up';
                    if ($state < 0) return 'heroicon-o-arrow-down';
                    return 'heroicon-o-minus';
                }),

            // Type
            Tables\Columns\TextColumn::make('type')
                ->label('Type')
                ->badge()
                ->color('info')
                ->formatStateUsing(function ($state) {
                    return match($state) {
                        'individual_sync' => 'Individual',
                        'bulk_sync' => 'Bulk',
                        'bulk_optimized_sync' => 'Bulk Optimized',
                        'bulk_sync_summary' => 'Summary',
                        'bulk_sync_start' => 'Started',
                        'bulk_sync_completed' => 'Completed',
                        'bulk_sync_failed' => 'Failed',
                        'enhanced_dashboard_fallback' => 'Enhanced Fallback',
                        'bulk_enhanced_fallback' => 'Bulk Enhanced',
                        default => ucfirst(str_replace('_', ' ', $state ?? 'Unknown')),
                    };
                })
                ->sortable()
                ->toggleable(),

            // Message
            Tables\Columns\TextColumn::make('message')
                ->label('Message')
                ->limit(40)
                ->tooltip(function ($record) {
                    return $record->message ?? 'No message';
                })
                ->formatStateUsing(function ($state, $record) {
                    if ($record->dry_run) {
                        $change = $record->change;
                        if (is_null($change) && !is_null($record->old_stock) && !is_null($record->new_stock)) {
                            $change = $record->new_stock - $record->old_stock;
                        }
                        
                        if ($change == 0) {
                            return "Dry run - Already in sync";
                        } else {
                            return "Dry run - Would update stock";
                        }
                    }
                    return $state ?? 'No message';
                })
                ->searchable()
                ->wrap()
                ->toggleable(),

            // ✅ ADDED: Method Used Column
            Tables\Columns\TextColumn::make('method_used')
                ->label('Method')
                ->badge()
                ->formatStateUsing(function ($state) {
                    return match($state) {
                        'stock_push' => 'Stock Push',
                        'enhanced_dashboard_fallback' => 'Enhanced Fallback',
                        'optimized_bulk' => 'Optimized Bulk',
                        'enhanced_fallback_bulk' => 'Enhanced Fallback',
                        'both_methods_failed' => 'Both Failed',
                        'same_as_single_test' => 'Same as Single',
                        default => ucfirst(str_replace('_', ' ', $state ?? 'Unknown'))
                    };
                })
                ->color(function ($state) {
                    return match($state) {
                        'stock_push' => 'primary',
                        'enhanced_dashboard_fallback' => 'warning',
                        'enhanced_fallback_bulk' => 'warning',
                        'optimized_bulk' => 'info',
                        'both_methods_failed' => 'danger',
                        default => 'gray'
                    };
                })
                ->sortable()
                ->toggleable(),

            // Session ID for tracking batches
            Tables\Columns\TextColumn::make('session_id')
                ->label('Batch ID')
                ->limit(12)
                ->fontFamily('mono')
                ->size('xs')
                ->color('gray')
                ->tooltip(function ($record) {
                    return "Session: {$record->session_id}";
                })
                ->copyable()
                ->toggleable()
                ->toggledHiddenByDefault(),

            // Error Message (if any)
            Tables\Columns\TextColumn::make('error_message')
                ->label('Error')
                ->limit(30)
                ->tooltip(function ($record) {
                    return $record->error_message;
                })
                ->color('danger')
                ->visible(fn ($record) => !empty($record->error_message))
                ->toggleable()
                ->toggledHiddenByDefault(),
        ])
        ->filters([
            // Status Filter
            Tables\Filters\SelectFilter::make('status')
                ->label('Status')
                ->options([
                    'success' => 'Success',
                    'failed' => 'Failed',
                    'pending' => 'Pending',
                    'skipped' => 'Skipped',
                ])
                ->placeholder('All Statuses'),

            // ✅ ADDED: Operation Type Filter
            Tables\Filters\SelectFilter::make('operation_type')
                ->label('Operation')
                ->options([
                    'stock_push' => 'Stock Push',
                    'sync' => 'Sync',
                    'push' => 'Push',
                    'test' => 'Test',
                ])
                ->placeholder('All Operations'),

            // ✅ ADDED: Method Used Filter
            Tables\Filters\SelectFilter::make('method_used')
                ->label('Method')
                ->options([
                    'stock_push' => 'Stock Push',
                    'enhanced_dashboard_fallback' => 'Enhanced Fallback',
                    'optimized_bulk' => 'Optimized Bulk',
                    'enhanced_fallback_bulk' => 'Enhanced Fallback Bulk',
                ])
                ->placeholder('All Methods'),

            // Type Filter
            Tables\Filters\SelectFilter::make('type')
                ->label('Type')
                ->options([
                    'individual_sync' => 'Individual Sync',
                    'bulk_sync' => 'Bulk Sync',
                    'bulk_optimized_sync' => 'Bulk Optimized Sync',
                    'bulk_sync_summary' => 'Bulk Sync Summary',
                    'enhanced_dashboard_fallback' => 'Enhanced Fallback',
                    'bulk_enhanced_fallback' => 'Bulk Enhanced',
                ])
                ->placeholder('All Types'),

            // Dry Run Filter
            Tables\Filters\TernaryFilter::make('dry_run')
                ->label('Operation Mode')
                ->placeholder('All Operations')
                ->trueLabel('Dry Run Only')
                ->falseLabel('Live Sync Only'),

            // Stock Changes Filter
            Tables\Filters\Filter::make('has_changes')
                ->label('Has Stock Changes')
                ->query(fn (Builder $query): Builder => $query->where('change', '!=', 0))
                ->toggle(),

            // Failed Only Filter
            Tables\Filters\Filter::make('failed_only')
                ->label('Failed Syncs Only')
                ->query(fn (Builder $query): Builder => $query->where('status', 'failed'))
                ->toggle(),

            // Date Range Filter
            Tables\Filters\Filter::make('created_at')
                ->form([
                    Forms\Components\DatePicker::make('created_from')
                        ->label('From Date'),
                    Forms\Components\DatePicker::make('created_until')
                        ->label('Until Date'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['created_from'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                        )
                        ->when(
                            $data['created_until'],
                            fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                        );
                })
                ->indicateUsing(function (array $data): array {
                    $indicators = [];
                    if ($data['created_from'] ?? null) {
                        $indicators['created_from'] = 'From ' . \Carbon\Carbon::parse($data['created_from'])->toFormattedDateString();
                    }
                    if ($data['created_until'] ?? null) {
                        $indicators['created_until'] = 'Until ' . \Carbon\Carbon::parse($data['created_until'])->toFormattedDateString();
                    }
                    return $indicators;
                }),

            // Session Filter
            Tables\Filters\Filter::make('session_id')
                ->form([
                    Forms\Components\TextInput::make('session_id')
                        ->label('Session ID')
                        ->placeholder('Enter session ID'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query->when(
                        $data['session_id'],
                        fn (Builder $query, $sessionId): Builder => $query->where('session_id', 'like', "%{$sessionId}%"),
                    );
                }),
        ])
        ->actions([
            // View Action
            Tables\Actions\ViewAction::make()
                ->modalWidth(\Filament\Support\Enums\MaxWidth::FourExtraLarge),

            // ✅ ADDED: View Session Action
            Tables\Actions\Action::make('view_session')
                ->label('View Session')
                ->icon('heroicon-o-queue-list')
                ->color('info')
                ->url(fn ($record) => 
                    static::getUrl('index', [
                        'tableFilters' => [
                            'session_id' => $record->session_id
                        ]
                    ])
                )
                ->visible(fn ($record) => !empty($record->session_id)),

            // Edit Action (for admin)
            Tables\Actions\EditAction::make()
                ->visible(fn ($record) => auth()->user()?->can('edit', $record) ?? false),

            // Delete Action (for admin)
            Tables\Actions\DeleteAction::make()
                ->visible(fn ($record) => auth()->user()?->can('delete', $record) ?? false),

            // Retry Action for Failed Records
            Tables\Actions\Action::make('retry_sync')
                ->label('Retry')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn ($record) => $record->status === 'failed' && !empty($record->sku))
                ->requiresConfirmation()
                ->modalHeading('Retry Sync')
                ->modalDescription(fn ($record) => "Retry syncing SKU: {$record->sku}?")
                ->action(function ($record) {
                    try {
                        $syncService = new \App\Services\GineeStockSyncService();
                        $result = $syncService->syncSingleSku($record->sku, false);
                        
                        if ($result['success']) {
                            \Filament\Notifications\Notification::make()
                                ->title('Retry Successful')
                                ->body("SKU {$record->sku} synced successfully")
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Retry Failed')
                                ->body($result['message'] ?? 'Unknown error')
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Retry Error')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                // Delete Bulk Action
                Tables\Actions\DeleteBulkAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Selected Logs')
                    ->modalDescription('Are you sure you want to delete the selected sync logs?'),

                // Mark as Reviewed
                Tables\Actions\BulkAction::make('mark_reviewed')
                    ->label('Mark as Reviewed')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function ($records) {
                        $records->each(function ($record) {
                            $record->update(['reviewed_at' => now()]);
                        });
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Marked as Reviewed')
                            ->body($records->count() . ' records marked as reviewed')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),

                // Export to CSV
                Tables\Actions\BulkAction::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function ($records) {
                        // Export logic here
                        \Filament\Notifications\Notification::make()
                            ->title('Export Started')
                            ->body('CSV export for ' . $records->count() . ' records')
                            ->info()
                            ->send();
                    }),
            ]),
        ])
        ->defaultSort('created_at', 'desc')
        ->poll('2s') // Auto refresh every 30 seconds for real-time monitoring
        ->persistSortInSession()
        ->persistSearchInSession()
        ->persistFiltersInSession()
        ->striped()
        ->paginated([10, 25, 50, 100])
        ->recordUrl(null) // Disable default row click
        ->emptyStateHeading('No Sync Records Found')
        ->emptyStateDescription('Sync logs will appear here when sync operations are performed.')
        ->emptyStateIcon('heroicon-o-document-magnifying-glass');
}

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGineeSyncLogs::route('/'),
            'create' => Pages\CreateGineeSyncLog::route('/create'),
            'view' => Pages\ViewGineeSyncLog::route('/{record}'),
            'edit' => Pages\EditGineeSyncLog::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            \App\Filament\Admin\Resources\GineeSyncLogResource\Widgets\SyncStatsWidget::class,
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', GineeSyncLog::class) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereDate('created_at', today())->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $todayCount = static::getModel()::whereDate('created_at', today())->count();
        
        if ($todayCount > 100) {
            return 'success';
        } elseif ($todayCount > 50) {
            return 'warning';
        }
        
        return 'primary';
    }
}