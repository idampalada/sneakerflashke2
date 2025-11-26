<?php
// File: app/Filament/Resources/BlackFridayResource/Widgets/FlashSaleWidget.php

namespace App\Filament\Admin\Resources\BlackFridayResource\Widgets;

use App\Models\BlackFridayProduct;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class FlashSaleWidget extends BaseWidget
{
    protected static ?string $heading = 'Flash Sale Items';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BlackFridayProduct::where('is_flash_sale', true)
                    ->where('is_active', true)
                    ->orderBy('sale_end_date', 'asc')
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

                Tables\Columns\TextColumn::make('discount_percentage')
                    ->label('Discount')
                    ->formatStateUsing(fn ($state) => "-{$state}%")
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('limited_stock')
                    ->label('Flash Stock')
                    ->badge()
                    ->color(fn ($state) => $state <= 5 ? 'danger' : 'warning'),

                Tables\Columns\TextColumn::make('sale_end_date')
                    ->label('Ends')
                    ->since()
                    ->color('warning'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (BlackFridayProduct $record): string => 
                        route('filament.admin.resources.black-friday.edit', $record)),
            ])
            ->emptyStateHeading('No Flash Sale Items')
            ->emptyStateDescription('Create flash sale products to boost sales.')
            ->emptyStateIcon('heroicon-o-bolt')
            ->striped();
    }
}




