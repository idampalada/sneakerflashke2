<?php

namespace App\Filament\Admin\Resources\BlackFridayResource\Pages;

use App\Filament\Admin\Resources\BlackFridayResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewBlackFridayProduct extends ViewRecord
{
    protected static string $resource = BlackFridayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
