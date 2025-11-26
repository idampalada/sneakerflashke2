<?php
// File: app/Filament/Resources/BlackFridayResource/Widgets/LowStockWidget.php

namespace App\Filament\Admin\Resources\BlackFridayResource\Widgets;

use App\Models\BlackFridayProduct;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static ?string $heading = 'Low Stock Alert';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BlackFridayProduct::where('is_active', true)
                    ->where('stock_quantity', '<=', 5)
                    ->where('stock_quantity', '>', 0)
                    ->orderBy('stock_quantity', 'asc')
            )
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('Image')
                    ->circular()
                    ->size(40),

                Tables\Columns\TextColumn::make('name')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('brand')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('sku')
                    ->searchable(),

                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->badge()
                    ->color(fn ($state) => $state <= 2 ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('price')
                    ->money('IDR'),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (BlackFridayProduct $record): string => 
                        route('filament.admin.resources.black-friday.edit', $record)),
            ])
            ->emptyStateHeading('No Low Stock Items')
            ->emptyStateDescription('All Black Friday products have sufficient stock.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->striped();
    }
}