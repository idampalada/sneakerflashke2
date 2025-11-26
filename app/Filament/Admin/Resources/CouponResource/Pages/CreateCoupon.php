<?php
// File: app/Filament/Admin/Resources/CouponResource/Pages/CreateCoupon.php

namespace App\Filament\Admin\Resources\CouponResource\Pages;

use App\Filament\Admin\Resources\CouponResource;
use App\Models\Coupon;
use Filament\Resources\Pages\CreateRecord;
use Filament\Actions;

class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_code')
                ->label('Generate Code')
                ->icon('heroicon-o-sparkles')
                ->action(function () {
                    $code = Coupon::generateUniqueCode('SAVE', 6);
                    $this->form->fill(['code' => $code]);
                })
                ->color('info'),
                
            Actions\Action::make('quick_templates')
                ->label('Quick Templates')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->modalContent(view('filament.admin.coupons.templates'))
                ->modalActions([
                    Actions\Action::make('welcome_discount')
                        ->label('Welcome Discount')
                        ->action(function () {
                            $this->form->fill([
                                'code' => Coupon::generateUniqueCode('WELCOME', 4),
                                'name' => 'New Customer Welcome Discount',
                                'description' => 'Special discount for new customers',
                                'type' => 'percentage',
                                'value' => 10,
                                'minimum_amount' => 100000,
                                'usage_limit' => 1000,
                                'is_active' => true,
                                'expires_at' => now()->addMonths(3),
                            ]);
                            $this->closeActionModal();
                        }),
                        
                    Actions\Action::make('free_shipping')
                        ->label('Free Shipping')
                        ->action(function () {
                            $this->form->fill([
                                'code' => Coupon::generateUniqueCode('SHIP', 6),
                                'name' => 'Free Shipping Promotion',
                                'description' => 'Get free shipping on your order',
                                'type' => 'free_shipping',
                                'value' => 0,
                                'minimum_amount' => 250000,
                                'is_active' => true,
                                'expires_at' => now()->addMonth(),
                            ]);
                            $this->closeActionModal();
                        }),
                        
                    Actions\Action::make('flash_sale')
                        ->label('Flash Sale')
                        ->action(function () {
                            $this->form->fill([
                                'code' => Coupon::generateUniqueCode('FLASH', 4),
                                'name' => '24 Hour Flash Sale',
                                'description' => 'Limited time flash sale discount',
                                'type' => 'percentage',
                                'value' => 25,
                                'maximum_discount' => 500000,
                                'usage_limit' => 500,
                                'is_active' => true,
                                'starts_at' => now(),
                                'expires_at' => now()->addDay(),
                            ]);
                            $this->closeActionModal();
                        }),
                ])
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure code is uppercase
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
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

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Coupon created successfully!';
    }
}