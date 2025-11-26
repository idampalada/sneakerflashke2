<?php

namespace App\Filament\Admin\Resources\ProductResource\Pages;

use App\Filament\Admin\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    // 🔥 ULTIMATE FIX: Override mutateFormDataBeforeFill
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Log original data
        Log::info('🔍 EditProduct: BEFORE FILL (RAW)', [
            'product_id' => $data['id'] ?? 'unknown',
            'has_images_key' => isset($data['images']),
            'images_type' => isset($data['images']) ? gettype($data['images']) : 'not_set',
            'images_value' => $data['images'] ?? 'not_set'
        ]);

        // Pastikan images dalam format yang benar untuk form
        if (isset($data['images'])) {
            if (is_string($data['images'])) {
                $decoded = json_decode($data['images'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $data['images'] = $decoded;
                } else {
                    $data['images'] = !empty($data['images']) ? [$data['images']] : [];
                }
            } elseif (!is_array($data['images'])) {
                $data['images'] = [];
            }

            // Filter dan clean
            $data['images'] = array_filter($data['images'], function($image) {
                return !empty($image) && is_string($image);
            });
            $data['images'] = array_values($data['images']);
        } else {
            $data['images'] = [];
        }

        Log::info('🔍 EditProduct: AFTER FILL PROCESSING', [
            'product_id' => $data['id'] ?? 'unknown',
            'final_images_count' => count($data['images']),
            'final_images' => $data['images']
        ]);

        return $data;
    }

    // 🔥 ULTIMATE FIX: Override mutateFormDataBeforeSave dengan FORCE PRESERVE
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Get original images dari database
        $originalImages = $this->record->images ?? [];
        
        Log::info('🔍 EditProduct: BEFORE SAVE ANALYSIS', [
            'product_id' => $this->record->id,
            'original_images_count' => count($originalImages),
            'original_images' => $originalImages,
            'form_has_images' => isset($data['images']),
            'form_images_count' => isset($data['images']) && is_array($data['images']) ? count($data['images']) : 0,
            'form_images' => $data['images'] ?? 'not_set'
        ]);

        // 🚨 CRITICAL DECISION LOGIC:
        if (!isset($data['images']) || empty($data['images'])) {
            // JIKA FORM TIDAK KIRIM IMAGES ATAU KOSONG
            if (!empty($originalImages)) {
                // DAN ORIGINAL ADA IMAGES -> PRESERVE ORIGINAL
                Log::warning('⚠️ EditProduct: Form sent empty images, PRESERVING original', [
                    'product_id' => $this->record->id,
                    'preserving_count' => count($originalImages)
                ]);
                $data['images'] = $originalImages;
            } else {
                // DAN ORIGINAL JUGA KOSONG -> BIARKAN KOSONG
                Log::info('ℹ️ EditProduct: Both form and original empty', [
                    'product_id' => $this->record->id
                ]);
                $data['images'] = [];
            }
        } else {
            // JIKA FORM KIRIM IMAGES -> VALIDASI DAN GUNAKAN
            if (is_array($data['images'])) {
                $cleanImages = array_filter($data['images'], function($image) {
                    return !empty($image) && is_string($image);
                });
                $data['images'] = array_values($cleanImages);
                
                Log::info('✅ EditProduct: Using form images', [
                    'product_id' => $this->record->id,
                    'form_images_count' => count($data['images']),
                    'form_images' => $data['images']
                ]);
            } else {
                // FORM KIRIM TAPI BUKAN ARRAY -> PRESERVE ORIGINAL
                Log::warning('⚠️ EditProduct: Form sent invalid images type, preserving original', [
                    'product_id' => $this->record->id,
                    'form_images_type' => gettype($data['images'])
                ]);
                $data['images'] = $originalImages;
            }
        }

        Log::info('🔍 EditProduct: FINAL SAVE DECISION', [
            'product_id' => $this->record->id,
            'final_images_count' => count($data['images']),
            'final_images' => $data['images']
        ]);

        return $data;
    }

    // 🔥 VERIFICATION: Check hasil save
    protected function afterSave(): void
    {
        $savedRecord = $this->record->fresh();
        $savedImages = $savedRecord->images ?? [];
        
        Log::info('🔍 EditProduct: FINAL VERIFICATION', [
            'product_id' => $savedRecord->id,
            'saved_images_count' => count($savedImages),
            'saved_images' => $savedImages,
            'record_name' => $savedRecord->name
        ]);

        // Notification berdasarkan hasil
        if (empty($savedImages)) {
            Notification::make()
                ->title('⚠️ Warning: No images found')
                ->body('Product was saved but no images were detected. This might indicate a data issue - check the logs.')
                ->warning()
                ->persistent()
                ->send();
        } else {
            Notification::make()
                ->title('✅ Product updated successfully')
                ->body("Product '{$savedRecord->name}' saved with " . count($savedImages) . ' image(s)')
                ->success()
                ->send();
        }
    }

    // 🔥 ADDITIONAL: Monitor form actions
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->before(function (array $data) {
                    Log::info('🔍 EditProduct: FORM ACTION TRIGGERED', [
                        'product_id' => $this->record->id,
                        'action_data_keys' => array_keys($data),
                        'has_images_in_action' => isset($data['images']),
                        'images_in_action' => $data['images'] ?? 'not_set'
                    ]);
                }),
            $this->getCancelFormAction(),
        ];
    }

    // 🔥 OVERRIDE: Handle form data with extra protection
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        // Get form data BEFORE it's processed
        $formData = $this->form->getState();
        
        Log::info('🔍 EditProduct: SAVE METHOD CALLED', [
            'product_id' => $this->record->id,
            'form_state_has_images' => isset($formData['images']),
            'form_state_images_count' => isset($formData['images']) && is_array($formData['images']) ? count($formData['images']) : 0,
            'form_state_images' => $formData['images'] ?? 'not_set'
        ]);

        // Call parent save method
        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }
}