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
use Illuminate\Support\Facades\Storage;

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
                            '17:00' => '17:00 - Late Afternoon',
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
                    $this->requestPickup($data);
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
                    $this->generateLabel();
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
                    $this->trackShipment();
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
                ->action(function () {
                    $this->downloadLabel();
                }),
        ];
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Request pickup for the order
     */
    protected function requestPickup(array $data): void
    {
        try {
            \Log::info('🚚 Starting pickup request', [
                'order_id' => $this->getRecord()->id,
                'data' => $data
            ]);

            $record = $this->getRecord();
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
                
                // Save AWB & timestamp to database columns
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

                // Refresh current page properly
                $this->redirect($this->getUrl());
            } else {
                throw new \Exception($result['message'] ?? 'Failed to request pickup');
            }
        } catch (\Exception $e) {
            \Log::error('Pickup request error', [
                'order_id' => $this->getRecord()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Notification::make()
                ->title('Error Requesting Pickup')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Generate shipping label for the order
     */
    protected function generateLabel(): void
    {
        try {
            \Log::info('🏷️ Starting label generation', [
                'order_id' => $this->getRecord()->id,
                'komerce_order_id' => $this->getKomerceOrderId()
            ]);

            $record = $this->getRecord();
            $meta = json_decode($record->meta_data ?? '{}', true) ?? [];
            $komerceOrderId = $meta['komerce_order_id'] ?? null;
            
            if (!$komerceOrderId) {
                throw new \Exception('Komerce Order ID not found');
            }

            $komerceService = app(KomerceOrderService::class);
            $result = $komerceService->printLabel($komerceOrderId, 'page_2');

            if ($result['success']) {
                $resultData = $result['data'];
                \Log::info('📊 Label generation successful', ['result_data' => $resultData]);

                // Multi-format support for API response
                $downloadUrl = null;
                $awbNumber = null;
                $pdfFilename = 'komerce_label.pdf';

                // Priority 1: NEW FORMAT (download_url)
                if (isset($resultData['download_url']) && !empty($resultData['download_url'])) {
                    $downloadUrl = $resultData['download_url'];
                    \Log::info('✅ Found download_url field', ['url' => $downloadUrl]);
                    
                    // Extract AWB from path if available
                    if (isset($resultData['path']) && !empty($resultData['path'])) {
                        $path = $resultData['path'];
                        $pathInfo = pathinfo($path);
                        $filename = $pathInfo['filename']; // e.g., label-07-01-2026-21-07-01768193152
                        
                        $parts = explode('-', $filename);
                        if (count($parts) > 1) {
                            $awbNumber = end($parts); // 01768193152
                            \Log::info('✅ Extracted AWB from path', ['awb' => $awbNumber]);
                        }
                    }
                    
                    if (isset($resultData['filename'])) {
                        $pdfFilename = $resultData['filename'];
                    }
                }
                // Priority 2: FALLBACK (base_64)
                elseif (isset($resultData['base_64']) && !empty($resultData['base_64'])) {
                    $base64Pdf = $resultData['base_64'];
                    \Log::info('✅ Found base_64 field, storing in session');
                    
                    // Store in session for download route
                    session(['komerce_pdf_' . $komerceOrderId => base64_decode($base64Pdf)]);
                    $downloadUrl = route('checkout.komerce.download-label', ['order' => $komerceOrderId]);
                    
                    // Extract AWB from path if available
                    if (isset($resultData['path'])) {
                        $pathParts = explode('-', basename($resultData['path'], '.pdf'));
                        if (count($pathParts) > 1) {
                            $awbNumber = end($pathParts);
                        }
                    }
                }
                // Priority 3: FALLBACK (label_url)
                elseif (isset($resultData['label_url']) && !empty($resultData['label_url'])) {
                    $downloadUrl = $resultData['label_url'];
                    $awbNumber = $resultData['airway_bill'] ?? null;
                    \Log::info('✅ Found label_url field', ['url' => $downloadUrl]);
                }
                // Priority 4: FALLBACK - Use existing AWB from pickup
                if (!$awbNumber) {
                    // Check existing AWB from pickup
                    if (isset($meta['komerce_pickup']['awb']) && !empty($meta['komerce_pickup']['awb'])) {
                        $awbNumber = $meta['komerce_pickup']['awb'];
                        \Log::info('✅ Using existing AWB from pickup', ['awb' => $awbNumber]);
                    } elseif (isset($meta['awb']) && !empty($meta['awb'])) {
                        $awbNumber = $meta['awb'];
                        \Log::info('✅ Using existing AWB from meta', ['awb' => $awbNumber]);
                    } elseif (!empty($record->komerce_awb)) {
                        $awbNumber = $record->komerce_awb;
                        \Log::info('✅ Using existing AWB from database column', ['awb' => $awbNumber]);
                    }
                }
                
                if (!$downloadUrl) {
                    throw new \Exception('No PDF content or label URL found in response. Available fields: ' . implode(', ', array_keys($resultData)));
                }

                // Update meta_data with label information
                $meta['komerce_label'] = [
                    'label_generated_at' => now()->toISOString(),
                    'label_url' => $downloadUrl,
                    'airway_bill' => $awbNumber,
                    'pdf_filename' => $pdfFilename,
                ];
                
                // Update database
                $record->update([
                    'meta_data' => json_encode($meta),
                    'tracking_number' => $awbNumber
                ]);

                \Log::info('✅ Label generation completed successfully', [
                    'download_url' => $downloadUrl,
                    'awb' => $awbNumber
                ]);

                Notification::make()
                    ->title('Label Generated Successfully!')
                    ->body("Tracking Number: " . ($awbNumber ?: 'Available in download'))
                    ->success()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('download')
                            ->label('Download Label')
                            ->url($downloadUrl, shouldOpenInNewTab: true)
                    ])
                    ->send();

                // Refresh current page properly
                $this->redirect($this->getUrl());
            } else {
                throw new \Exception($result['message'] ?? 'Failed to generate label');
            }
        } catch (\Exception $e) {
            \Log::error('Label Generation Error', [
                'order_id' => $this->getRecord()->id,
                'komerce_order_id' => $this->getKomerceOrderId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Notification::make()
                ->title('Error Generating Label')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Track shipment status
     */
    protected function trackShipment(): void
    {
        try {
            $record = $this->getRecord();
            
            // Get AWB from multiple sources with priority
            $awbNumber = null;
            
            // Priority 1: Database column komerce_awb
            if (!empty($record->komerce_awb)) {
                $awbNumber = $record->komerce_awb;
                \Log::info('✅ Using AWB from komerce_awb column', ['awb' => $awbNumber]);
            }
            // Priority 2: Meta data pickup AWB
            else {
                $meta = json_decode($record->meta_data ?? '{}', true) ?? [];
                
                if (isset($meta['komerce_pickup']['awb']) && !empty($meta['komerce_pickup']['awb'])) {
                    $awbNumber = $meta['komerce_pickup']['awb'];
                    \Log::info('✅ Using AWB from pickup meta data', ['awb' => $awbNumber]);
                }
                // Priority 3: Meta data root AWB
                elseif (isset($meta['awb']) && !empty($meta['awb'])) {
                    $awbNumber = $meta['awb'];
                    \Log::info('✅ Using AWB from root meta data', ['awb' => $awbNumber]);
                }
                // Priority 4: Fallback to tracking_number if no AWB found
                elseif (!empty($record->tracking_number)) {
                    $awbNumber = $record->tracking_number;
                    \Log::info('⚠️ Using tracking_number as fallback AWB', ['awb' => $awbNumber]);
                }
            }
            
            if (!$awbNumber) {
                throw new \Exception('No AWB or tracking number available for shipment tracking');
            }

            \Log::info('🔍 Starting shipment tracking', ['awb' => $awbNumber]);

            $komerceService = app(KomerceOrderService::class);
            $result = $komerceService->trackShipment($awbNumber, 'JNE');

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
                    ->title("Tracking AWB: {$awbNumber}")
                    ->body($bodyText)
                    ->info()
                    ->duration(10000)
                    ->send();
            } else {
                throw new \Exception($result['message'] ?? 'Failed to track shipment');
            }
        } catch (\Exception $e) {
            \Log::error('Track Shipment Error', [
                'order_id' => $this->getRecord()->id,
                'error' => $e->getMessage()
            ]);

            Notification::make()
                ->title('Error Tracking Shipment')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Get Komerce Order ID helper
     */
    private function getKomerceOrderId(): ?string
    {
        $meta = json_decode($this->getRecord()->meta_data ?? '{}', true) ?? [];
        return $meta['komerce_order_id'] ?? null;
    }

    /**
     * Download label - triggers API call with logging and stores PDF locally
     */
    protected function downloadLabel(): void
    {
        try {
            $record = $this->getRecord();
            $meta = json_decode($record->meta_data ?? '{}', true) ?? [];
            $komerceOrderId = $meta['komerce_order_id'] ?? null;
            
            if (!$komerceOrderId) {
                throw new \Exception('Komerce Order ID not found');
            }

            // Check if we already have a local PDF
            $localPdfPath = "komerce-labels/{$komerceOrderId}.pdf";
            
            if (Storage::disk('local')->exists($localPdfPath)) {
                \Log::info('📥 Download Label - Using existing local PDF', [
                    'order_id' => $record->id,
                    'local_path' => $localPdfPath,
                    'komerce_order_id' => $komerceOrderId
                ]);

                // Redirect to download route which will trigger browser download
                $downloadUrl = route('checkout.komerce.download-label', ['order' => $komerceOrderId]);
                $this->redirect($downloadUrl);
                return;
            }

            \Log::info('📥 Download Label - Generating and storing new PDF locally', [
                'order_id' => $record->id,
                'komerce_order_id' => $komerceOrderId
            ]);

            // Generate new label via API
            $komerceService = app(KomerceOrderService::class);
            $result = $komerceService->printLabel($komerceOrderId, 'page_2');

            if ($result['success']) {
                $resultData = $result['data'];
                $pdfContent = null;
                
                // Priority 1: Use base64 content directly
                if (isset($resultData['base_64']) && !empty($resultData['base_64'])) {
                    $pdfContent = base64_decode($resultData['base_64']);
                    
                    \Log::info('📥 Using base64 PDF content from API response', [
                        'pdf_size' => strlen($pdfContent),
                        'komerce_order_id' => $komerceOrderId
                    ]);
                }
                // Priority 2: Download from provided URL
                elseif (isset($resultData['download_url']) && !empty($resultData['download_url'])) {
                    try {
                        $pdfContent = file_get_contents($resultData['download_url']);
                        
                        \Log::info('📥 Downloaded PDF from external URL', [
                            'download_url' => $resultData['download_url'],
                            'pdf_size' => strlen($pdfContent),
                            'komerce_order_id' => $komerceOrderId
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('❌ Failed to download PDF from URL', [
                            'download_url' => $resultData['download_url'],
                            'error' => $e->getMessage()
                        ]);
                        throw new \Exception('Failed to download PDF from external URL');
                    }
                }
                // Priority 3: Check if we have existing URL in meta
                elseif (isset($meta['komerce_label']['label_url'])) {
                    try {
                        $externalUrl = $meta['komerce_label']['label_url'];
                        $pdfContent = file_get_contents($externalUrl);
                        
                        \Log::info('📥 Downloaded PDF from existing meta URL', [
                            'existing_url' => $externalUrl,
                            'pdf_size' => strlen($pdfContent),
                            'komerce_order_id' => $komerceOrderId
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('❌ Failed to download PDF from meta URL', [
                            'existing_url' => $meta['komerce_label']['label_url'] ?? 'N/A',
                            'error' => $e->getMessage()
                        ]);
                        throw new \Exception('Failed to download PDF from stored URL');
                    }
                }

                if ($pdfContent && strlen($pdfContent) > 0) {
                    // Store PDF locally
                    Storage::disk('local')->put($localPdfPath, $pdfContent);
                    
                    \Log::info('✅ PDF stored locally successfully', [
                        'local_path' => $localPdfPath,
                        'pdf_size' => strlen($pdfContent),
                        'order_id' => $record->id,
                        'komerce_order_id' => $komerceOrderId
                    ]);

                    // Redirect to download route which will trigger browser download
                    $downloadUrl = route('checkout.komerce.download-label', ['order' => $komerceOrderId]);
                    $this->redirect($downloadUrl);
                    
                    Notification::make()
                        ->title('Label Downloaded Successfully')
                        ->body('PDF has been generated and is ready for download.')
                        ->success()
                        ->send();
                } else {
                    throw new \Exception('No valid PDF content found in API response');
                }
            } else {
                throw new \Exception($result['message'] ?? 'Failed to generate label');
            }

        } catch (\Exception $e) {
            \Log::error('❌ Download Label Error', [
                'order_id' => $this->getRecord()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Notification::make()
                ->title('Error Downloading Label')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}