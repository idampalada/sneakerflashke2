<?php

namespace App\Filament\Admin\Resources\GineeSyncLogResource\Pages;

use App\Filament\Admin\Resources\GineeSyncLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGineeSyncLog extends EditRecord
{
    protected static string $resource = GineeSyncLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
