<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BlackFridayResource\Pages;
use App\Models\Product;
use App\Services\BlackFridayGoogleSheetsSync;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;

class BlackFridayResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = '🖤 Black Friday';

    protected static ?string $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'black-friday-products';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('product_type', 'BLACKFRIDAY')
            ->where('is_active', true)
            ->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('product_type', 'BLACKFRIDAY');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Black Friday Product Information')
                    ->schema([
                        Forms\Components\Hidden::make('product_type')
                            ->default('BLACKFRIDAY'),

                        Forms\Components\TextInput::make('brand')
                            ->required()
                            ->placeholder('e.g. NIKE, ADIDAS, PUMA'),

                        Forms\Components\TextInput::make('related_product')
                            ->placeholder('Related product code'),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->placeholder('Full product name'),

                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->placeholder('Product description'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pricing & Black Friday Deals')
                    ->schema([
                        Forms\Components\TextInput::make('original_price')
                            ->label('Original Price')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->placeholder('Original price before discount'),

                        Forms\Components\TextInput::make('price')
                            ->label('Sale Price')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->placeholder('Discounted Black Friday price'),

                        Forms\Components\TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('Unique product SKU'),

                        Forms\Components\TextInput::make('stock_quantity')
                            ->label('Stock Quantity')
                            ->required()
                            ->numeric()
                            ->default(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Black Friday Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured'),

                        Forms\Components\Toggle::make('is_sale')
                            ->label('Flash Sale')
                            ->default(true),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('Image')
                    ->circular()
                    ->size(50),

                Tables\Columns\TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('brand')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('original_price')
                    ->label('Original Price')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Sale Price')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->badge()
                    ->color(fn (string $state): string => match (true) {
                        $state == 0 => 'danger',
                        $state <= 5 => 'warning',
                        default => 'success',
                    }),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Featured'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlackFridayProducts::route('/'),
            'create' => Pages\CreateBlackFridayProduct::route('/create'),
            'edit' => Pages\EditBlackFridayProduct::route('/{record}/edit'),
            'view' => Pages\ViewBlackFridayProduct::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'brand', 'sku'];
    }
}