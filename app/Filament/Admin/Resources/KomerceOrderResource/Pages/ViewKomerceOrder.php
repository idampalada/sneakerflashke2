<?php

namespace App\Filament\Admin\Resources\KomerceOrderResource\Pages;

use App\Filament\Admin\Resources\KomerceOrderResource;
use App\Models\Order;
use App\Services\KomerceOrderService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

class ViewKomerceOrder extends ViewRecord
{
    protected static string $resource = KomerceOrderResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Order Information')
                    ->schema([
                        TextEntry::make('order_number')
                            ->label('Order Number')
                            ->copyable(),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match($state) {
                                'paid' => 'success',
                                'pending' => 'warning', 
                                'shipped' => 'info',
                                'delivered' => 'success',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('total_amount')
                            ->label('Total Amount')
                            ->money('IDR'),
                        TextEntry::make('created_at')
                            ->label('Order Date')
                            ->dateTime(),
                    ])
                    ->columns(2),

                Section::make('Customer Information')
                    ->schema([
                        TextEntry::make('customer_name')
                            ->label('Customer Name'),
                        TextEntry::make('customer_email')
                            ->label('Email')
                            ->copyable(),
                        TextEntry::make('customer_phone')
                            ->label('Phone')
                            ->copyable(),
                        TextEntry::make('shipping_address')
                            ->label('Shipping Address')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Komerce Information')
                    ->schema([
                        TextEntry::make('komerce_order_id')
                            ->label('Komerce Order ID')
                            ->state(function (Order $record) {
                                $meta = json_decode($record->meta_data ?? '{}', true) ?? [];
                                return $meta['komerce_order_id'] ?? 'Auto-created on payment';
                            })
                            ->copyable(),
                        
                        TextEntry::make('pickup_requested_at')
                            ->label('Pickup Status')
                            ->state(function (Order $record) {
                                if ($record->pickup_requested_at) {
                                    $meta = json_decode($record->meta_data ?? '{}', true) ?? [];
                                    $pickup = $meta['komerce_pickup'] ?? [];
                                    $date = $record->pickup_requested_at->format('d M Y');
                                    $time = $pickup['pickup_time'] ?? 'N/A';
                                    return "Requested for {$date} at {$time}";
                                }
                                return 'Ready for pickup request';
                            }),

                        // ✅ AWB FROM DATABASE COLUMN
                        TextEntry::make('komerce_awb')
                            ->label('AWB (Airway Bill)')
                            ->placeholder('Generated after pickup request')
                            ->copyable(),
                            
                        TextEntry::make('tracking_number')
                            ->label('Tracking Number')
                            ->placeholder('Generated after label creation')
                            ->copyable(),
                        
                        TextEntry::make('label_url')
                            ->label('Label Download')
                            ->state(function (Order $record) {
                                $meta = json_decode($record->meta_data ?? '{}', true) ?? [];
                                if (isset($meta['komerce_label']['label_url'])) {
                                    return 'Available for download';
                                }
                                return 'Generated after pickup request';
                            }),
                    ])
                    ->columns(2),

                Section::make('Order Items')
                    ->schema([
                        TextEntry::make('order_items')
                            ->label('Products')
                            ->state(function (Order $record) {
                                $html = '<div class="space-y-2">';
                                foreach ($record->orderItems as $item) {
                                    $subtotal = number_format($item->total_price, 0, ',', '.');
                                    $html .= "<div class='flex justify-between items-center p-2 bg-gray-50 rounded'>";
                                    $html .= "<div>";
                                    $html .= "<div class='font-medium'>{$item->product_name}</div>";
                                    $html .= "<div class='text-sm text-gray-500'>Qty: {$item->quantity} × Rp " . number_format($item->product_price, 0, ',', '.') . "</div>";
                                    $html .= "</div>";
                                    $html .= "<div class='font-semibold text-green-600'>Rp {$subtotal}</div>";
                                    $html .= "</div>";
                                }
                                $html .= '</div>';
                                return $html;
                            })
                            ->html()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Request Pickup Action
            Actions\Action::make('request_pickup')
                ->label('Request Pickup')
                ->icon('heroicon-o-truck')
                ->color('warning')
                ->visible(function (): bool {
                    return $this->getRecord()->isReadyForPickup();
                })
                ->form([
                    Forms\Components\DatePicker::make('pickup_date')
                        ->label('Pickup Date')
                        ->required()
                        ->default(now()->addDay()),
                    Forms\Components\Select::make('pickup_time')
                        ->label('Pickup Time')
                        ->options([
                            '09:00' => '09:00 - Morning',
                            '11:00' => '11:00 - Late Morning',
                            '14:00' => '14:00 - Afternoon',
                            '16:00' => '16:00 - Late Afternoon',
                        ])
                        ->default('14:00')
                        ->required(),
                    Forms\Components\Select::make('pickup_vehicle')
                        ->label('Vehicle Type')
                        ->options([
                            'Motor' => 'Motor (Standard)',
                            'Mobil' => 'Car (Large packages)',
                        ])
                        ->default('Motor')
                        ->required(),
                ])
                ->action(function (array $data) {
                    $this->requestPickup($data, $this->getRecord());
                }),

            // Generate Label Action
            Actions\Action::make('generate_label')
                ->label('Generate Label')
                ->icon('heroicon-o-tag')
                ->color('info')
                ->visible(function (): bool {
                    $record = $this->getRecord();
                    return $record->hasPickupRequested() && !$record->tracking_number;
                })
                ->action(function () {
                    $this->generateLabel($this->getRecord());
                }),

            // Track Shipment Action
            Actions\Action::make('track_shipment')
                ->label('Track Shipment')
                ->icon('heroicon-o-magnifying-glass')
                ->color('primary')
                ->visible(function (): bool {
                    $record = $this->getRecord();
                    return !empty($record->tracking_number);
                })
                ->action(function () {
                    $this->trackShipment($this->getRecord());
                }),

            // Download Label Action
            Actions\Action::make('download_label')
                ->label('Download Label')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(function (): bool {
                    $record = $this->getRecord();
                    $meta = json_decode($record->meta_data ?? '{}', true) ?? [];
                    return isset($meta['komerce_label']['label_url']);
                })
                ->url(function (): string {
                    $record = $this->getRecord();
                    $meta = json_decode($record->meta_data ?? '{}', true) ?? [];
                    return $meta['komerce_label']['label_url'] ?? '#';
                }, shouldOpenInNewTab: true),
        ];
    }

    // HELPER METHODS
    protected function requestPickup(array $data, Order $record): void
    {
        try {
            $meta = json_decode($record->meta_data ?? '{}', true) ?? [];
            $komerceOrderId = $meta['komerce_order_id'] ?? null;
            
            if (!$komerceOrderId) {
                throw new \Exception('Order belum auto-created di Komerce system');
            }

            $komerceService = app(KomerceOrderService::class);
            $result = $komerceService->requestPickup(
                $data['pickup_date'],
                $data['pickup_time'],
                [$komerceOrderId],
                $data['pickup_vehicle']
            );

            if ($result['success']) {
                $resultData = $result['data'];
                
                // Check if failed
                if (isset($resultData['status']) && $resultData['status'] === 'failed') {
                    Notification::make()
                        ->title('Pickup Request Failed')
                        ->body('Order mungkin sudah di-pickup sebelumnya atau ada masalah di Komerce system.')
                        ->warning()
                        ->send();
                    return;
                }
                
                // ✅ SAVE AWB & TIMESTAMP TO DATABASE COLUMNS
                $record->update([
                    'komerce_awb' => $resultData['awb'] ?? null,
                    'pickup_requested_at' => now(),
                ]);
                
                // Save detailed info to JSON
                $meta['komerce_pickup'] = [
                    'pickup_requested_at' => now()->toISOString(),
                    'pickup_date' => $data['pickup_date'],
                    'pickup_time' => $data['pickup_time'],
                    'pickup_vehicle' => $data['pickup_vehicle'],
                    'order_no' => $resultData['order_no'] ?? $komerceOrderId,
                    'awb' => $resultData['awb'] ?? null,
                    'status' => $resultData['status'] ?? 'unknown'
                ];
                $record->update(['meta_data' => json_encode($meta)]);

                $awbText = $resultData['awb'] ? "\nAWB: {$resultData['awb']}" : '';
                
                Notification::make()
                    ->title('Pickup Requested Successfully!')
                    ->body("Pickup scheduled for {$data['pickup_date']} at {$data['pickup_time']}{$awbText}")
                    ->success()
                    ->send();

                // Refresh the page
                $this->redirect($this->getUrl());
            } else {
                throw new \Exception($result['message'] ?? 'Failed to request pickup');
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error Requesting Pickup')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function generateLabel(Order $record): void
    {
        try {
            $meta = json_decode($record->meta_data ?? '{}', true) ?? [];
            $komerceOrderId = $meta['komerce_order_id'] ?? null;
            
            $komerceService = app(KomerceOrderService::class);
            $result = $komerceService->printLabel($komerceOrderId, 'page_2');

            if ($result['success']) {
                $meta['komerce_label'] = [
                    'label_generated_at' => now()->toISOString(),
                    'label_url' => $result['data']['label_url'] ?? null,
                    'airway_bill' => $result['data']['airway_bill'] ?? null,
                ];
                
                $record->update([
                    'meta_data' => json_encode($meta),
                    'tracking_number' => $result['data']['airway_bill'] ?? null
                ]);

                Notification::make()
                    ->title('Label Generated Successfully!')
                    ->body("Tracking Number: {$result['data']['airway_bill']}")
                    ->success()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('download')
                            ->label('Download Label')
                            ->url($result['data']['label_url'] ?? '#', shouldOpenInNewTab: true)
                    ])
                    ->send();

                // Refresh the page
                $this->redirect($this->getUrl());
            } else {
                throw new \Exception($result['message'] ?? 'Failed to generate label');
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error Generating Label')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function trackShipment(Order $record): void
    {
        try {
            $komerceService = app(KomerceOrderService::class);
            $result = $komerceService->trackShipment($record->tracking_number, 'JNE');

            if ($result['success']) {
                $status = $result['data']['status'] ?? 'Unknown';
                $history = $result['data']['history'] ?? [];
                
                $bodyText = "Current Status: {$status}";
                if (!empty($history)) {
                    $bodyText .= "\n\nRecent Updates:";
                    foreach (array_slice($history, 0, 3) as $track) {
                        $bodyText .= "\n• {$track['description']} ({$track['date']})";
                    }
                }
                
                Notification::make()
                    ->title("Tracking: {$record->tracking_number}")
                    ->body($bodyText)
                    ->info()
                    ->duration(10000)
                    ->send();
            } else {
                throw new \Exception($result['message'] ?? 'Failed to track shipment');
            }
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error Tracking Shipment')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}