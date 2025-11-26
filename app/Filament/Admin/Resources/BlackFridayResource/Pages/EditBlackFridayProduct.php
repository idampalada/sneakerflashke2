<?php

namespace App\Filament\Admin\Resources\BlackFridayResource\Pages;

use App\Filament\Admin\Resources\BlackFridayResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBlackFridayProduct extends EditRecord
{
    protected static string $resource = BlackFridayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
