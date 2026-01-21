<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\KomerceOrderResource\Pages;
use App\Models\Order;
use App\Services\KomerceOrderService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class KomerceOrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Komerce Orders';
    protected static ?string $navigationGroup = 'Shop';
    protected static ?int $navigationSort = 4;

    public static function getNavigationBadge(): ?string
    {
        try {
            if (!Schema::hasColumn('orders', 'komerce_order_no')) return null;
            $count = static::getModel()::where('status', 'paid')->whereNull('komerce_order_no')->count();
            return $count > 0 ? (string) $count : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getEloquentQuery(): Builder
    {
        try {
            if (!Schema::hasColumn('orders', 'komerce_order_no')) {
                return parent::getEloquentQuery()->whereRaw('1 = 0');
            }
            // FIXED: Remove status filter to show all orders, not just paid ones
            return parent::getEloquentQuery()->orderBy('created_at', 'desc');
        } catch (\Exception $e) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('order_number')->label('Order Number')->disabled(),
            Forms\Components\TextInput::make('komerce_order_no')->label('Komerce Order No')->disabled(),
            Forms\Components\TextInput::make('customer_name')->label('Customer Name')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        if (!Schema::hasColumn('orders', 'komerce_order_no')) {
            return $table
                ->columns([
                    Tables\Columns\TextColumn::make('order_number')->label('Setup Required')->default('⚠️ Migration needed'),
                ])
                ->emptyStateHeading('Database Setup Required');
        }

        return $table
            ->columns([
                // Order Number
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order Number')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                // Komerce Order No
                Tables\Columns\TextColumn::make('komerce_order_no')
                    ->label('Komerce Status')
                    ->placeholder('🔄 Not synced')
                    ->searchable()
                    ->copyable(),

                // Customer
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->limit(30),

                // Total
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable(),

                // Status
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'primary' => 'processing',
                        'info' => 'shipped',
                        'danger' => 'cancelled',
                    ]),

                // Tracking
                Tables\Columns\TextColumn::make('tracking_number')
                    ->label('Tracking')
                    ->placeholder('No tracking')
                    ->searchable()
                    ->copyable(),

                // Date
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                
                // SYNC TO KOMERCE ACTION
                Action::make('sync_to_komerce')
                    ->label('Sync to Komerce')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(function ($record) {
                        try {
                            return $record 
                                && is_object($record) 
                                && property_exists($record, 'komerce_order_no')
                                && property_exists($record, 'status')
                                && in_array($record->status, ['paid', 'processing', 'shipped']) 
                                && empty($record->komerce_order_no);
                        } catch (\Exception $e) {
                            return false;
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Sync Order to Komerce')
                    ->modalDescription('This will create this order in Komerce system for shipping.')
                    ->action(function ($record) {
                        if (!$record || !is_object($record)) {
                            Notification::make()->title('Error')->body('Invalid record')->danger()->send();
                            return;
                        }

                        try {
                            // SIMPLE TEST - Just update komerce_order_no with a fake number for now
                            $fakeKomerceOrderNo = 'KOM' . time() . rand(1000, 9999);
                            $record->update(['komerce_order_no' => $fakeKomerceOrderNo]);
                            
                            Notification::make()
                                ->title('SUCCESS! Order Synced')
                                ->body("Test sync completed. Komerce Order No: {$fakeKomerceOrderNo}")
                                ->success()
                                ->send();

                            Log::info('Test sync to Komerce', [
                                'order_number' => $record->order_number,
                                'fake_komerce_order_no' => $fakeKomerceOrderNo
                            ]);

                        } catch (\Exception $e) {
                            Log::error('Sync error', [
                                'order_number' => $record->order_number ?? 'unknown',
                                'error' => $e->getMessage()
                            ]);
                            
                            Notification::make()
                                ->title('ERROR')
                                ->body('Sync failed: ' . $e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                
                // ✅ FIXED: GENERATE LABEL ACTION
                Action::make('generate_label')
                    ->label('Generate Label')
                    ->icon('heroicon-o-tag')
                    ->color('primary')
                    ->visible(function ($record) {
                        try {
                            return $record 
                                && is_object($record) 
                                && property_exists($record, 'komerce_order_no')
                                && property_exists($record, 'tracking_number')
                                && !empty($record->komerce_order_no)
                                && empty($record->tracking_number);
                        } catch (\Exception $e) {
                            return false;
                        }
                    })
                    ->action(function ($record) {
                        if (!$record || !is_object($record)) {
                            Notification::make()->title('Error')->body('Invalid record')->danger()->send();
                            return;
                        }

                        try {
                            $service = app(KomerceOrderService::class);
                            $result = $service->printLabel($record->komerce_order_no, 'page_2');

                            if ($result['success']) {
                                // ✅ FIXED: Handle PDF response correctly
                                if (isset($result['data']['pdf_content'])) {
                                    // Store PDF temporarily for download
                                    $filename = 'label_' . $record->komerce_order_no . '.pdf';
                                    $pdfPath = 'temp/' . $filename;
                                    
                                    // Store PDF in storage
                                    Storage::disk('public')->put($pdfPath, base64_decode($result['data']['pdf_content']));
                                    $downloadUrl = Storage::disk('public')->url($pdfPath);
                                    
                                    // Extract tracking from AWB if available
                                    $awbNumber = $result['data']['awb'] ?? $result['data']['airway_bill'] ?? null;
                                    if ($awbNumber) {
                                        $record->update(['tracking_number' => $awbNumber]);
                                    }
                                    
                                    Notification::make()
                                        ->title('SUCCESS! Label Generated')
                                        ->body($awbNumber ? "Tracking: {$awbNumber}" : "Label ready for download")
                                        ->success()
                                        ->actions([
                                            \Filament\Notifications\Actions\Action::make('download')
                                                ->label('Download Label')
                                                ->url($downloadUrl)
                                                ->openUrlInNewTab(),
                                        ])
                                        ->persistent()
                                        ->send();
                                        
                                    Log::info('✅ Komerce Label Generated Successfully from Filament', [
                                        'order_number' => $record->order_number,
                                        'komerce_order_no' => $record->komerce_order_no,
                                        'awb' => $awbNumber,
                                        'pdf_path' => $pdfPath
                                    ]);
                                    
                                } elseif (isset($result['data']['label_url'])) {
                                    // Direct URL from API
                                    $awbNumber = $result['data']['awb'] ?? $result['data']['airway_bill'] ?? null;
                                    if ($awbNumber) {
                                        $record->update(['tracking_number' => $awbNumber]);
                                    }
                                    
                                    Notification::make()
                                        ->title('SUCCESS! Label Generated')
                                        ->body($awbNumber ? "Tracking: {$awbNumber}" : "Label ready")
                                        ->success()
                                        ->actions([
                                            \Filament\Notifications\Actions\Action::make('download')
                                                ->label('Download Label')
                                                ->url($result['data']['label_url'])
                                                ->openUrlInNewTab(),
                                        ])
                                        ->persistent()
                                        ->send();
                                } else {
                                    // Fallback - track from result data
                                    $awbNumber = $result['data']['awb'] ?? $result['data']['airway_bill'] ?? null;
                                    if ($awbNumber) {
                                        $record->update(['tracking_number' => $awbNumber]);
                                    }
                                    
                                    Notification::make()
                                        ->title('Label Generated')
                                        ->body($awbNumber ? "Tracking: {$awbNumber}. Check Komerce dashboard for label." : "Check Komerce dashboard for label.")
                                        ->success()
                                        ->send();
                                }
                            } else {
                                Notification::make()->title('LABEL FAILED')->body($result['message'] ?? 'Unknown error')->danger()->send();
                                
                                Log::error('❌ Komerce Label Generation Failed from Filament', [
                                    'order_number' => $record->order_number,
                                    'komerce_order_no' => $record->komerce_order_no,
                                    'error' => $result['message'] ?? 'Unknown error'
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('❌ Komerce Label Generation Exception from Filament', [
                                'order_number' => $record->order_number ?? 'unknown',
                                'komerce_order_no' => $record->komerce_order_no ?? 'unknown',
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            
                            Notification::make()->title('ERROR')->body('System error: ' . $e->getMessage())->danger()->send();
                        }
                    }),

                // TRACK ACTION
                Action::make('track')
                    ->label('Track')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('info')
                    ->visible(function ($record) {
                        try {
                            return $record 
                                && is_object($record) 
                                && property_exists($record, 'tracking_number')
                                && !empty($record->tracking_number);
                        } catch (\Exception $e) {
                            return false;
                        }
                    })
                    ->action(function ($record) {
                        if (!$record || !is_object($record)) {
                            Notification::make()->title('Error')->body('Invalid record')->danger()->send();
                            return;
                        }

                        try {
                            $service = app(KomerceOrderService::class);
                            $result = $service->trackShipment($record->tracking_number, 'JNE');

                            if ($result['success']) {
                                $status = $result['data']['status'] ?? 'Unknown';
                                $location = $result['data']['location'] ?? '';
                                $lastUpdate = $result['data']['last_update'] ?? '';
                                
                                $bodyText = "Status: {$status}";
                                if ($location) $bodyText .= "\nLocation: {$location}";
                                if ($lastUpdate) $bodyText .= "\nLast Update: {$lastUpdate}";
                                
                                Notification::make()
                                    ->title("TRACKING: {$record->tracking_number}")
                                    ->body($bodyText)
                                    ->info()
                                    ->persistent()
                                    ->send();
                            } else {
                                Notification::make()->title('TRACKING FAILED')->body($result['message'] ?? 'Unknown error')->warning()->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()->title('ERROR')->body('System error: ' . $e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->emptyStateHeading('No Paid Orders')
            ->emptyStateDescription('No paid orders found. Orders will appear here when marked as paid.')
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->defaultSort('created_at', 'desc')
            ->poll('30s'); 
    }

    public static function getPages(): array
{
    return [
        'index' => Pages\ListKomerceOrders::route('/'),
        'view' => Pages\ViewKomerceOrder::route('/{record}'), // Simple approach
    ];
}

    public static function canViewAny(): bool
    {
        try {
            return Schema::hasColumn('orders', 'komerce_order_no');
        } catch (\Exception $e) {
            return false;
        }
    }
}