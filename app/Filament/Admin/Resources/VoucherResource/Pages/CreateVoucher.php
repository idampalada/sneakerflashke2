<?php

namespace App\Filament\Admin\Resources\VoucherResource\Pages;

use App\Filament\Admin\Resources\VoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateVoucher extends CreateRecord
{
    protected static string $resource = VoucherResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure voucher code is uppercase
        if (isset($data['voucher_code'])) {
            $data['voucher_code'] = strtoupper(trim($data['voucher_code']));
        }

        // Set default values
        $data['total_used'] = 0;
        $data['is_active'] = $data['is_active'] ?? true;
        $data['sync_status'] = 'synced';

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}