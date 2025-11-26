<?php
// File: app/Filament/Admin/Resources/CouponResource/Pages/EditCoupon.php

namespace App\Filament\Admin\Resources\CouponResource\Pages;

use App\Filament\Admin\Resources\CouponResource;
use App\Models\Coupon;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_performance')
                ->label('View Performance')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->modalContent(fn () => view('filament.admin.coupons.performance', [
                    'coupon' => $this->record,
                    'metrics' => $this->record->getPerformanceMetrics(),
                    'topCustomers' => $this->record->getTopCustomers(5)
                ]))
                ->modalWidth('7xl'),
                
            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->action(function () {
                    $newCoupon = $this->record->replicate();
                    $newCoupon->code = Coupon::generateUniqueCode($this->record->code . '_', 4);
                    $newCoupon->name = $this->record->name . ' (Copy)';
                    $newCoupon->used_count = 0;
                    $newCoupon->save();
                    
                    Notification::make()
                        ->title('Coupon duplicated successfully')
                        ->success()
                        ->body("New coupon code: {$newCoupon->code}")
                        ->send();
                        
                    return redirect()->route('filament.admin.resources.coupons.edit', $newCoupon);
                }),
                
            Actions\Action::make('toggle_status')
                ->label(fn () => $this->record->is_active ? 'Deactivate' : 'Activate')
                ->icon(fn () => $this->record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                ->color(fn () => $this->record->is_active ? 'danger' : 'success')
                ->action(function () {
                    $this->record->update(['is_active' => !$this->record->is_active]);
                    
                    $status = $this->record->is_active ? 'activated' : 'deactivated';
                    
                    Notification::make()
                        ->title("Coupon {$status} successfully")
                        ->success()
                        ->send();
                })
                ->requiresConfirmation(),
                
            Actions\Action::make('reset_usage')
                ->label('Reset Usage Count')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->action(function () {
                    $oldCount = $this->record->used_count;
                    $this->record->update(['used_count' => 0]);
                    
                    Notification::make()
                        ->title('Usage count reset successfully')
                        ->success()
                        ->body("Reset from {$oldCount} to 0")
                        ->send();
                })
                ->requiresConfirmation()
                ->visible(fn () => $this->record->used_count > 0),
                
            Actions\DeleteAction::make()
                ->before(function () {
                    // Check if coupon has been used
                    if ($this->record->used_count > 0) {
                        Notification::make()
                            ->title('Cannot delete used coupon')
                            ->danger()
                            ->body('This coupon has been used in orders and cannot be deleted.')
                            ->send();
                            
                        $this->halt();
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure code is uppercase
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        // Prevent changing type if coupon has been used
        if ($this->record->used_count > 0 && $data['type'] !== $this->record->type) {
            Notification::make()
                ->title('Cannot change coupon type')
                ->danger()
                ->body('Cannot change type of a coupon that has already been used.')
                ->send();
                
            $data['type'] = $this->record->type;
        }

        // Set default values based on type
        if ($data['type'] === 'free_shipping') {
            $data['value'] = 0;
        }

        // Validate value based on type
        if ($data['type'] === 'percentage' && $data['value'] > 100) {
            $data['value'] = 100;
        }

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Coupon updated successfully!';
    }
}