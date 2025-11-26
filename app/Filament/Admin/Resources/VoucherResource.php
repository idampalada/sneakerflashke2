<?php

// =====================================
// 1. FILAMENT VOUCHER RESOURCE
// File: app/Filament/Admin/Resources/VoucherResource.php
// =====================================

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\VoucherResource\Pages;
use App\Models\Voucher;
use App\Services\VoucherSyncService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Shop';
    protected static ?int $navigationSort = 4;
    protected static ?string $recordTitleAttribute = 'name_voucher';

    public static function form(Form $form): Form
    {
        $schema = [
            // Basic Information Section
            Forms\Components\Section::make('Voucher Information')
                ->description('Essential voucher details synchronized with Google Spreadsheet')
                ->schema([
                    Forms\Components\TextInput::make('voucher_code')
                        ->label('Voucher Code')
                        ->required()
                        ->maxLength(100)
                        ->unique(Voucher::class, 'voucher_code', ignoreRecord: true)
                        ->placeholder('e.g., WELCOME50, SAVE10')
                        ->helperText('Unique code customers will enter to redeem this voucher')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('name_voucher')
                        ->label('Voucher Name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g., Welcome New Customer, Flash Sale 50%')
                        ->helperText('Internal name for this voucher campaign')
                        ->columnSpan(1),

                    Forms\Components\Select::make('code_product')
                        ->label('Product Scope')
                        ->options([
                            'All product' => 'All Products',
                            'Running' => 'Running Shoes',
                            'Basketball' => 'Basketball Shoes', 
                            'Lifestyle' => 'Lifestyle/Casual',
                            'Training' => 'Training Shoes',
                            'Apparel' => 'Apparel',
                            'Accessories' => 'Accessories'
                        ])
                        ->default('All product')
                        ->helperText('Which products this voucher applies to')
                        ->columnSpan(1),

                    Forms\Components\Select::make('category_customer')
                        ->label('Customer Category')
                        ->options([
                            'all customer' => 'All Customers',
                            'basic' => 'Basic Members',
                            'advance' => 'Advanced Members',
                            'ultimate' => 'Ultimate Members',
                            'vip' => 'VIP Members'
                        ])
                        ->default('all customer')
                        ->helperText('Which customer tier can use this voucher')
                        ->columnSpan(1),
                ])->columns(2),

            // Voucher Type & Value Section
            Forms\Components\Section::make('Discount Configuration')
                ->description('Set the discount type and value for this voucher')
                ->schema([
                    Forms\Components\Select::make('voucher_type')
                        ->label('Discount Type')
                        ->options([
                            'NOMINAL' => 'Fixed Amount (Rupiah)',
                            'PERCENT' => 'Percentage (%)'
                        ])
                        ->required()
                        ->reactive()
                        ->helperText('Choose between fixed amount or percentage discount')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('value')
                        ->label('Discount Value')
                        ->required()
                        ->placeholder(function (callable $get) {
                            return $get('voucher_type') === 'NOMINAL' ? 'Rp50.000' : '10%';
                        })
                        ->helperText(function (callable $get) {
                            return $get('voucher_type') === 'NOMINAL' 
                                ? 'Enter amount like: Rp50.000' 
                                : 'Enter percentage like: 10%';
                        })
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('discount_max')
                        ->label('Maximum Discount (Rp)')
                        ->numeric()
                        ->placeholder('100000')
                        ->helperText('Maximum discount amount for percentage vouchers')
                        ->visible(fn (callable $get) => $get('voucher_type') === 'PERCENT')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('min_purchase')
                        ->label('Minimum Purchase (Rp)')
                        ->numeric()
                        ->default(0)
                        ->placeholder('50000')
                        ->helperText('Minimum order amount to use this voucher')
                        ->columnSpan(1),
                ])->columns(2),

            // Usage Limits Section
            Forms\Components\Section::make('Usage Limits')
                ->description('Control how many times this voucher can be used')
                ->schema([
                    Forms\Components\TextInput::make('quota')
                        ->label('Total Quota')
                        ->numeric()
                        ->default(100)
                        ->minValue(1)
                        ->helperText('Total number of times this voucher can be used')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('claim_per_customer')
                        ->label('Usage Per Customer')
                        ->numeric()
                        ->default(1)
                        ->minValue(1)
                        ->helperText('How many times one customer can use this voucher')
                        ->columnSpan(1),

                    Forms\Components\Placeholder::make('total_used')
                        ->label('Times Used')
                        ->content(fn (?Voucher $record): string => 
                            $record ? (string) $record->total_used : '0')
                        ->columnSpan(1),

                    Forms\Components\Placeholder::make('remaining_quota')
                        ->label('Remaining Quota')
                        ->content(fn (?Voucher $record): string => 
                            $record ? (string) max(0, $record->quota - $record->total_used) : '0')
                        ->columnSpan(1),
                ])->columns(2),

            // Validity Period Section
            Forms\Components\Section::make('Validity Period')
                ->description('Set when this voucher is active and can be used')
                ->schema([
                    Forms\Components\DateTimePicker::make('start_date')
                        ->label('Start Date & Time')
                        ->helperText('When customers can start using this voucher')
                        ->columnSpan(1),

                    Forms\Components\DateTimePicker::make('end_date')
                        ->label('End Date & Time')
                        ->helperText('When this voucher expires')
                        ->columnSpan(1),

                    Forms\Components\Placeholder::make('current_status')
                        ->label('Current Status')
                        ->content(function (?Voucher $record): string {
                            if (!$record) return 'New voucher';
                            
                            $status = $record->current_status;
                            $badges = [
                                'active' => '🟢 Active',
                                'pending' => '🟡 Pending',
                                'expired' => '🔴 Expired',
                                'quota_full' => '⚫ Quota Full',
                                'inactive' => '⚪ Inactive'
                            ];
                            
                            return $badges[$status] ?? ucfirst($status);
                        })
                        ->columnSpan(2),
                ])->columns(2),

            // System Settings Section
            Forms\Components\Section::make('System Settings')
                ->description('Control voucher visibility and sync status')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active Voucher')
                        ->default(true)
                        ->helperText('Inactive vouchers cannot be used by customers')
                        ->columnSpan(1),

                    Forms\Components\Select::make('sync_status')
                        ->label('Sync Status')
                        ->options([
                            'synced' => 'Synced',
                            'pending' => 'Pending Sync',
                            'error' => 'Sync Error'
                        ])
                        ->default('synced')
                        ->helperText('Status of synchronization with Google Spreadsheet')
                        ->columnSpan(1),

                    Forms\Components\Placeholder::make('spreadsheet_row_id')
                        ->label('Spreadsheet Row')
                        ->content(fn (?Voucher $record): string => 
                            $record?->spreadsheet_row_id ? "Row {$record->spreadsheet_row_id}" : 'Not synced')
                        ->columnSpan(1),

                    Forms\Components\Placeholder::make('last_sync')
                        ->label('Last Updated')
                        ->content(fn (?Voucher $record): string => 
                            $record?->updated_at ? $record->updated_at->diffForHumans() : 'Never')
                        ->columnSpan(1),
                ])->columns(2),
        ];

        return $form->schema($schema);
    }

    public static function table(Table $table): Table
{
    return $table
        ->columns([
            // Basic Info Columns
            Tables\Columns\TextColumn::make('voucher_code')
                ->label('Voucher Code')
                ->searchable()
                ->sortable()
                ->weight('bold')
                ->copyable()
                ->copyMessage('Voucher code copied!')
                ->tooltip('Click to copy')
                ->wrap(),

            Tables\Columns\TextColumn::make('name_voucher')
                ->label('Voucher Name')
                ->searchable()
                ->sortable()
                ->limit(25)
                ->wrap()
                ->tooltip(fn ($record) => $record->name_voucher),

            Tables\Columns\TextColumn::make('code_product')
                ->label('Product Scope')
                ->badge()
                ->color('secondary')
                ->formatStateUsing(fn (string $state): string => 
                    $state === 'All product' ? 'All Products' : $state)
                ->toggleable(),

            // Discount Configuration
            Tables\Columns\BadgeColumn::make('voucher_type')
                ->label('Type')
                ->colors([
                    'success' => 'NOMINAL',
                    'info' => 'PERCENT',
                ]),

            Tables\Columns\TextColumn::make('value')
                ->label('Discount Value')
                ->sortable()
                ->weight('semibold')
                ->color(fn ($record) => $record->voucher_type === 'NOMINAL' ? 'success' : 'info'),

            Tables\Columns\TextColumn::make('min_purchase')
                ->label('Min Purchase')
                ->formatStateUsing(fn ($state) => 
                    $state > 0 ? 'Rp ' . number_format($state, 0, ',', '.') : 'No minimum')
                ->color(fn ($state) => $state > 0 ? 'warning' : 'secondary')
                ->toggleable(),

            Tables\Columns\TextColumn::make('discount_max')
                ->label('Max Discount')
                ->formatStateUsing(fn ($state) => 
                    $state > 0 ? 'Rp ' . number_format($state, 0, ',', '.') : 'No limit')
                ->color('danger')
                ->toggleable(),

            // Usage Information
            Tables\Columns\TextColumn::make('usage_progress')
                ->label('Usage Progress')
                ->formatStateUsing(fn (Voucher $record): string => 
                    "{$record->total_used} / {$record->quota}")
                ->description(fn (Voucher $record): string => 
                    $record->quota > 0 
                        ? round(($record->total_used / $record->quota) * 100, 1) . '% used'
                        : '0% used')
                ->color(fn (Voucher $record) => 
                    $record->quota > 0 && ($record->total_used / $record->quota) > 0.8 ? 'danger' : 'success'),

            Tables\Columns\TextColumn::make('remaining_quota')
                ->label('Remaining')
                ->formatStateUsing(fn (Voucher $record): string => 
                    (string) max(0, $record->quota - $record->total_used))
                ->color(fn (Voucher $record) => 
                    ($record->quota - $record->total_used) <= 10 ? 'danger' : 'success')
                ->toggleable(),

            Tables\Columns\TextColumn::make('claim_per_customer')
                ->label('Per Customer')
                ->alignCenter()
                ->toggleable(),

            // Status Information
            Tables\Columns\BadgeColumn::make('current_status')
                ->label('Current Status')
                ->colors([
                    'success' => 'active',
                    'warning' => 'pending',
                    'danger' => 'expired',
                    'secondary' => 'quota_full',
                    'gray' => 'inactive',
                ])
                ->formatStateUsing(fn (string $state): string => match($state) {
                    'active' => 'Active',
                    'pending' => 'Pending',
                    'expired' => 'Expired',
                    'quota_full' => 'Quota Full',
                    'inactive' => 'Inactive',
                    default => ucfirst($state)
                }),

            Tables\Columns\BadgeColumn::make('category_customer')
                ->label('Customer Tier')
                ->colors([
                    'success' => 'all customer',
                    'info' => 'basic',
                    'warning' => 'advance',
                    'danger' => 'ultimate',
                    'primary' => 'vip',
                ])
                ->formatStateUsing(fn (string $state): string => match($state) {
                    'all customer' => 'All Customers',
                    'basic' => 'Basic',
                    'advance' => 'Advanced',
                    'ultimate' => 'Ultimate',
                    'vip' => 'VIP',
                    default => ucfirst($state)
                }),

            // Date Information
            Tables\Columns\TextColumn::make('start_date')
                ->label('Valid From')
                ->dateTime('d/m/Y')
                ->sortable()
                ->color(fn ($state) => 
                    $state && $state->isFuture() ? 'warning' : 'success')
                ->description(fn ($state) => 
                    $state ? $state->format('H:i') : 'No start date')
                ->toggleable(),

            Tables\Columns\TextColumn::make('end_date')
                ->label('Valid Until')
                ->dateTime('d/m/Y')
                ->sortable()
                ->color(fn ($state) => 
                    $state && $state->isPast() ? 'danger' : 'success')
                ->description(fn ($state) => 
                    $state ? $state->format('H:i') : 'No end date')
                ->toggleable(),

            // System Information
            Tables\Columns\IconColumn::make('is_active')
                ->label('Active')
                ->boolean()
                ->sortable()
                ->toggleable(),

            Tables\Columns\BadgeColumn::make('sync_status')
                ->label('Sync Status')
                ->colors([
                    'success' => 'synced',
                    'warning' => 'pending',
                    'danger' => 'error',
                ])
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('spreadsheet_row_id')
                ->label('Sheet Row')
                ->formatStateUsing(fn ($state) => $state ? "Row {$state}" : 'Not synced')
                ->color('gray')
                ->toggleable(isToggledHiddenByDefault: true),

            // Timestamps
            Tables\Columns\TextColumn::make('created_at')
                ->label('Created')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('updated_at')
                ->label('Last Updated')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->since()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            // Voucher Type Filter
            SelectFilter::make('voucher_type')
                ->label('Discount Type')
                ->options([
                    'NOMINAL' => 'Fixed Amount (Nominal)',
                    'PERCENT' => 'Percentage (%)',
                ])
                ->placeholder('All Types'),

            // Customer Category Filter
            SelectFilter::make('category_customer')
                ->label('Customer Tier')
                ->options([
                    'all customer' => 'All Customers',
                    'basic' => 'Basic Members',
                    'advance' => 'Advanced Members', 
                    'ultimate' => 'Ultimate Members',
                    'vip' => 'VIP Members',
                ])
                ->placeholder('All Tiers'),

            // Product Scope Filter
            SelectFilter::make('code_product')
                ->label('Product Scope')
                ->options([
                    'All product' => 'All Products',
                    'Running' => 'Running Shoes',
                    'Basketball' => 'Basketball Shoes',
                    'Lifestyle' => 'Lifestyle/Casual',
                    'Training' => 'Training Shoes',
                    'Apparel' => 'Apparel',
                    'Accessories' => 'Accessories',
                ])
                ->placeholder('All Products'),

            // Status Filters
            Filter::make('active_vouchers')
                ->label('Currently Active')
                ->query(fn (Builder $query): Builder => 
                    $query->where('is_active', true)
                          ->where('start_date', '<=', now())
                          ->where('end_date', '>=', now())
                          ->whereRaw('quota > total_used'))
                ->toggle(),

            Filter::make('expiring_soon')
                ->label('Expiring Soon (7 days)')
                ->query(fn (Builder $query): Builder => 
                    $query->where('is_active', true)
                          ->whereBetween('end_date', [now(), now()->addDays(7)]))
                ->toggle(),

            Filter::make('low_quota')
                ->label('Low Quota (< 10 remaining)')
                ->query(fn (Builder $query): Builder => 
                    $query->whereRaw('(quota - total_used) < 10')
                          ->where('quota', '>', 0))
                ->toggle(),

            Filter::make('never_used')
                ->label('Never Used')
                ->query(fn (Builder $query): Builder => 
                    $query->where('total_used', 0))
                ->toggle(),

            // Date Range Filters
            Filter::make('created_date')
                ->form([
                    Forms\Components\DatePicker::make('created_from')
                        ->label('Created From'),
                    Forms\Components\DatePicker::make('created_until')
                        ->label('Created Until'),
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
                        $indicators['created_from'] = 'Created from ' . Carbon::parse($data['created_from'])->toFormattedDateString();
                    }
                    if ($data['created_until'] ?? null) {
                        $indicators['created_until'] = 'Created until ' . Carbon::parse($data['created_until'])->toFormattedDateString();
                    }
                    return $indicators;
                }),

            // Sync Status Filter
            SelectFilter::make('sync_status')
                ->label('Sync Status')
                ->options([
                    'synced' => 'Synced',
                    'pending' => 'Pending Sync',
                    'error' => 'Sync Error',
                ])
                ->placeholder('All Sync Status'),
        ])
        
        ->actions([
    Tables\Actions\ActionGroup::make([
        Tables\Actions\ViewAction::make()
            ->label('View Details')
            ->icon('heroicon-o-eye'),
            
        Tables\Actions\EditAction::make()
            ->label('Edit Voucher')
            ->icon('heroicon-o-pencil'),
        
        Tables\Actions\Action::make('toggle_active')
            ->label(fn (Voucher $record) => $record->is_active ? 'Deactivate' : 'Activate')
            ->icon(fn (Voucher $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
            ->color(fn (Voucher $record) => $record->is_active ? 'danger' : 'success')
            ->action(function (Voucher $record) {
                $record->update(['is_active' => !$record->is_active]);
                $status = $record->is_active ? 'activated' : 'deactivated';
                
                Notification::make()
                    ->title("Voucher {$status}")
                    ->body("Voucher {$record->voucher_code} has been {$status}")
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalHeading(fn (Voucher $record) => $record->is_active ? 'Deactivate Voucher' : 'Activate Voucher')
            ->modalDescription(fn (Voucher $record) => $record->is_active 
                ? 'Are you sure you want to deactivate this voucher? Customers will no longer be able to use it.'
                : 'Are you sure you want to activate this voucher? Customers will be able to use it immediately.'),

        Tables\Actions\Action::make('test_voucher')
            ->label('Test Voucher')
            ->icon('heroicon-o-beaker')
            ->color('info')
            ->form([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('test_amount')
                        ->label('Test Order Amount (Rp)')
                        ->numeric()
                        ->default(100000)
                        ->required()
                        ->placeholder('100000')
                        ->helperText('Enter order amount to test discount calculation'),
                        
                    Forms\Components\TextInput::make('test_customer_id')
                        ->label('Test Customer ID')
                        ->default('test_customer_' . time())
                        ->helperText('Customer ID for testing usage limits'),
                ])
            ])
            ->action(function (Voucher $record, array $data) {
                $validation = $record->isValidForUser(
                    $data['test_customer_id'], 
                    $data['test_amount']
                );
                
                if ($validation['valid']) {
                    Notification::make()
                        ->title('✅ Voucher is Valid!')
                        ->body("Order Amount: Rp " . number_format($data['test_amount'], 0, ',', '.') . "\nDiscount: Rp " . number_format($validation['discount'], 0, ',', '.') . "\nFinal Amount: Rp " . number_format($data['test_amount'] - $validation['discount'], 0, ',', '.'))
                        ->success()
                        ->duration(10000)
                        ->send();
                } else {
                    Notification::make()
                        ->title('❌ Voucher is Invalid')
                        ->body($validation['message'])
                        ->warning()
                        ->duration(8000)
                        ->send();
                }
            })
            ->modalHeading('Test Voucher Validation')
            ->modalDescription('Test this voucher with different order amounts to see if it would be valid.')
            ->modalSubmitActionLabel('Test Voucher'),

        Tables\Actions\Action::make('view_usage')
            ->label('View Usage History')
            ->icon('heroicon-o-chart-bar')
            ->color('secondary')
            ->url(fn (Voucher $record): string => '/admin/voucher-usage?tableFilters[voucher_id][value]=' . $record->id)
            ->openUrlInNewTab(),

        Tables\Actions\Action::make('copy_code')
            ->label('Copy Code')
            ->icon('heroicon-o-clipboard')
            ->color('gray')
            ->action(function (Voucher $record) {
                Notification::make()
                    ->title('Code Copied!')
                    ->body("Voucher code '{$record->voucher_code}' copied to clipboard")
                    ->success()
                    ->duration(3000)
                    ->send();
            })
            ->extraAttributes(fn (Voucher $record) => [
                'onclick' => "navigator.clipboard.writeText('{$record->voucher_code}'); event.stopPropagation();"
            ]),

        Tables\Actions\Action::make('duplicate')
            ->label('Duplicate Voucher')
            ->icon('heroicon-o-document-duplicate')
            ->color('warning')
            ->action(function (Voucher $record) {
                $newVoucherData = $record->toArray();
                
                // Remove unique fields and reset counters
                unset($newVoucherData['id']);
                unset($newVoucherData['created_at']);
                unset($newVoucherData['updated_at']);
                
                $newVoucherData['voucher_code'] = $record->voucher_code . '_COPY_' . substr(time(), -4);
                $newVoucherData['name_voucher'] = $record->name_voucher . ' (Copy)';
                $newVoucherData['total_used'] = 0;
                $newVoucherData['sync_status'] = 'pending';
                $newVoucherData['spreadsheet_row_id'] = null;
                
                $newVoucher = Voucher::create($newVoucherData);
                
                Notification::make()
                    ->title('Voucher Duplicated')
                    ->body("New voucher created: {$newVoucher->voucher_code}")
                    ->success()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('view')
                            ->button()
                            ->url("/admin/vouchers/{$newVoucher->id}/edit")
                            ->label('Edit New Voucher'),
                    ])
                    ->persistent()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalHeading('Duplicate Voucher')
            ->modalDescription('This will create a copy of this voucher with a new code.')
            ->modalSubmitActionLabel('Create Duplicate'),

    ])->label('Actions')->icon('heroicon-m-ellipsis-vertical'),
])
->headerActions([
    Tables\Actions\Action::make('sync_from_spreadsheet')
        ->label('Sync from Spreadsheet')
        ->icon('heroicon-o-arrow-down-tray')
        ->color('primary')
        ->action(function () {
            try {
                $syncService = new \App\Services\VoucherSyncService();
                $result = $syncService->syncFromSpreadsheet();
                
                Notification::make()
                    ->title('✅ Sync Completed!')
                    ->body("Processed: {$result['processed']} vouchers\nCreated: {$result['created']}\nUpdated: {$result['updated']}\nErrors: {$result['errors']}")
                    ->success()
                    ->duration(10000)
                    ->send();
                    
                // Refresh the page to show updated data
                redirect('/admin/vouchers');
                
            } catch (\Exception $e) {
                Notification::make()
                    ->title('❌ Sync Failed')
                    ->body('Error: ' . $e->getMessage())
                    ->danger()
                    ->duration(15000)
                    ->send();
            }
        })
        ->requiresConfirmation()
        ->modalHeading('Sync Vouchers from Google Spreadsheet')
        ->modalDescription('This will update existing vouchers and create new ones. Vouchers with the same code will be updated.')
        ->modalSubmitActionLabel('Normal Sync'),

// NEW: Force Create All Rows Action
    Tables\Actions\Action::make('force_create_all_rows')
        ->label('Force Create All Rows')
        ->icon('heroicon-o-plus-circle')
        ->color('warning')
        ->action(function () {
            try {
                $syncService = new \App\Services\VoucherSyncService();
                $result = $syncService->syncFromSpreadsheetForceNew();
                
                Notification::make()
                    ->title('🚀 Force Create Completed!')
                    ->body("Processed: {$result['processed']} rows\nCreated: {$result['created']} NEW vouchers\nUpdated: {$result['updated']}\nErrors: {$result['errors']}")
                    ->success()
                    ->duration(12000)
                    ->send();
                    
                // Refresh the page to show new data
                redirect('/admin/vouchers');
                
            } catch (\Exception $e) {
                Notification::make()
                    ->title('❌ Force Create Failed')
                    ->body('Error: ' . $e->getMessage())
                    ->danger()
                    ->duration(15000)
                    ->send();
            }
        })
        ->requiresConfirmation()
        ->modalHeading('Force Create All Spreadsheet Rows')
        ->modalDescription('⚠️ This will create a NEW voucher record for EVERY row in your spreadsheet, even if voucher codes are the same. Duplicate codes will be made unique automatically (e.g., SNEAK123_MERDEKA1, SNEAK123_MERDEKA3). Use this if you want each spreadsheet row to be a separate voucher.')
        ->modalSubmitActionLabel('Force Create All'),

    Tables\Actions\Action::make('clear_all_vouchers')
        ->label('Clear All Vouchers')
        ->icon('heroicon-o-trash')
        ->color('danger')
        ->action(function () {
            try {
                $count = Voucher::count();
                Voucher::truncate(); // Delete all vouchers
                
                Notification::make()
                    ->title('🗑️ All Vouchers Deleted')
                    ->body("Deleted {$count} vouchers. You can now sync fresh data from spreadsheet.")
                    ->success()
                    ->duration(8000)
                    ->send();
                    
                redirect('/admin/vouchers');
                
            } catch (\Exception $e) {
                Notification::make()
                    ->title('❌ Clear Failed')
                    ->body('Error: ' . $e->getMessage())
                    ->danger()
                    ->send();
            }
        })
        ->requiresConfirmation()
        ->modalHeading('⚠️ Clear ALL Vouchers')
        ->modalDescription('This will DELETE ALL vouchers from the database. This action cannot be undone. Usage history will also be deleted. Are you absolutely sure?')
        ->modalSubmitActionLabel('Yes, Delete All')
        ->extraAttributes(['style' => 'display: none;']) // Hidden by default
        ->visible(fn () => Voucher::count() > 0), // Only show if vouchers exist

    Tables\Actions\Action::make('view_spreadsheet')
        ->label('Open Spreadsheet')
        ->icon('heroicon-o-document-text')
        ->color('secondary')
        ->url('https://docs.google.com/spreadsheets/d/' . config('google-sheets.voucher.spreadsheet_id'))
        ->openUrlInNewTab(),

    // NEW: Show/Hide Advanced Actions
    Tables\Actions\Action::make('toggle_advanced_actions')
        ->label('Advanced Actions')
        ->icon('heroicon-o-cog-6-tooth')
        ->color('gray')
        ->action(function () {
            // This will be handled by JavaScript to show/hide advanced actions
            Notification::make()
                ->title('Advanced Actions')
                ->body('Advanced actions like "Clear All" are now visible. Use with caution!')
                ->warning()
                ->send();
        })
        ->extraAttributes([
            'onclick' => "
                document.querySelector('[data-action=\"clear_all_vouchers\"]').style.display = 
                document.querySelector('[data-action=\"clear_all_vouchers\"]').style.display === 'none' ? 'block' : 'none';
            "
        ]),

    Tables\Actions\Action::make('view_spreadsheet')
        ->label('Open Spreadsheet')
        ->icon('heroicon-o-document-text')
        ->color('secondary')
        ->url('https://docs.google.com/spreadsheets/d/' . config('google-sheets.voucher.spreadsheet_id'))
        ->openUrlInNewTab(),

    Tables\Actions\Action::make('toggle_columns')
        ->label('Toggle Columns')
        ->icon('heroicon-o-view-columns')
        ->color('gray')
        ->action(function () {
            // This will be handled by Filament's built-in column toggle
            Notification::make()
                ->title('Column Toggle')
                ->body('Use the column toggle button in the table toolbar to show/hide columns')
                ->info()
                ->send();
        }),

    Tables\Actions\Action::make('export_vouchers')
        ->label('Export Vouchers')
        ->icon('heroicon-o-arrow-down-tray')
        ->color('info')
        ->action(function () {
            // Export functionality placeholder
            Notification::make()
                ->title('Export Feature')
                ->body('Export functionality will be implemented in a future update')
                ->info()
                ->send();
        }),

    Tables\Actions\Action::make('bulk_create')
        ->label('Bulk Create')
        ->icon('heroicon-o-plus-circle')
        ->color('success')
        ->action(function () {
            // Bulk create functionality placeholder
            Notification::make()
                ->title('Bulk Create Feature')
                ->body('Bulk voucher creation will be implemented in a future update')
                ->info()
                ->send();
        }),
])
->bulkActions([
    Tables\Actions\BulkActionGroup::make([
        Tables\Actions\DeleteBulkAction::make()
            ->requiresConfirmation()
            ->modalHeading('Delete Selected Vouchers')
            ->modalDescription('Are you sure you want to delete the selected vouchers? This action cannot be undone. All usage history will be preserved.')
            ->modalSubmitActionLabel('Delete Vouchers'),
        
        Tables\Actions\BulkAction::make('activate')
            ->label('Activate Selected')
            ->icon('heroicon-o-play')
            ->color('success')
            ->action(function ($records) {
                $count = $records->count();
                $records->each->update(['is_active' => true]);
                
                Notification::make()
                    ->title('Vouchers Activated')
                    ->body("{$count} vouchers have been activated successfully")
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalHeading('Activate Selected Vouchers')
            ->modalDescription('Are you sure you want to activate all selected vouchers? They will become available for customers to use.')
            ->modalSubmitActionLabel('Activate Vouchers'),

        Tables\Actions\BulkAction::make('deactivate')
            ->label('Deactivate Selected')
            ->icon('heroicon-o-pause')
            ->color('danger')
            ->action(function ($records) {
                $count = $records->count();
                $records->each->update(['is_active' => false]);
                
                Notification::make()
                    ->title('Vouchers Deactivated')
                    ->body("{$count} vouchers have been deactivated successfully")
                    ->success()
                    ->send();
            })
            ->requiresConfirmation()
            ->modalHeading('Deactivate Selected Vouchers')
            ->modalDescription('Are you sure you want to deactivate all selected vouchers? Customers will no longer be able to use them.')
            ->modalSubmitActionLabel('Deactivate Vouchers'),

        Tables\Actions\BulkAction::make('mark_pending_sync')
            ->label('Mark for Sync')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->action(function ($records) {
                $count = $records->count();
                $records->each->update(['sync_status' => 'pending']);
                
                Notification::make()
                    ->title('Marked for Sync')
                    ->body("{$count} vouchers marked as pending sync")
                    ->success()
                    ->send();
            }),

        Tables\Actions\BulkAction::make('export_selected')
            ->label('Export Selected')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('info')
            ->action(function ($records) {
                Notification::make()
                    ->title('Export Selected')
                    ->body('Export functionality for selected vouchers will be implemented soon')
                    ->info()
                    ->send();
            }),
    ]),
])
->emptyStateActions([
    Tables\Actions\CreateAction::make()
        ->label('Create First Voucher')
        ->icon('heroicon-o-plus'),
        
    Tables\Actions\Action::make('sync_from_spreadsheet_empty')
        ->label('Sync from Spreadsheet')
        ->icon('heroicon-o-arrow-down-tray')
        ->color('primary')
        ->action(function () {
            try {
                $syncService = new \App\Services\VoucherSyncService();
                $result = $syncService->syncFromSpreadsheet();
                
                Notification::make()
                    ->title('Sync Completed!')
                    ->body("Imported {$result['processed']} vouchers from spreadsheet")
                    ->success()
                    ->send();
                    
                redirect('/admin/vouchers');
                
            } catch (\Exception $e) {
                Notification::make()
                    ->title('Sync Failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }),
])
->defaultSort('created_at', 'desc')
->striped()
->paginated([10, 25, 50, 100])
->poll('30s') // Auto-refresh every 30 seconds
->deferLoading()
->persistSortInSession()
->persistSearchInSession()
->persistFiltersInSession();
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
            'index' => Pages\ListVouchers::route('/'),
            'create' => Pages\CreateVoucher::route('/create'),
            'view' => Pages\ViewVoucher::route('/{record}'),
            'edit' => Pages\EditVoucher::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)
                                ->where('start_date', '<=', now())
                                ->where('end_date', '>=', now())
                                ->whereRaw('quota > total_used')
                                ->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'success';
    }
}