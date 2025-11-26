<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\VoucherUsageResource\Pages;
use App\Models\VoucherUsage;
use App\Models\Voucher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class VoucherUsageResource extends Resource
{
    protected static ?string $model = VoucherUsage::class;
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Shop';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'Voucher Usage';
    protected static ?string $modelLabel = 'Voucher Usage';
    protected static ?string $pluralModelLabel = 'Voucher Usage';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('voucher_id')
                    ->label('Voucher')
                    ->relationship('voucher', 'voucher_code')
                    ->searchable()
                    ->required(),

                Forms\Components\TextInput::make('customer_id')
                    ->label('Customer ID')
                    ->required(),

                Forms\Components\TextInput::make('customer_email')
                    ->label('Customer Email')
                    ->email()
                    ->required(),

                Forms\Components\TextInput::make('order_id')
                    ->label('Order ID')
                    ->required(),

                Forms\Components\TextInput::make('discount_amount')
                    ->label('Discount Amount')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),

                Forms\Components\TextInput::make('order_total')
                    ->label('Order Total')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),

                Forms\Components\DateTimePicker::make('used_at')
                    ->label('Used At')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('voucher.voucher_code')
                    ->label('Voucher Code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('voucher.name_voucher')
                    ->label('Voucher Name')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('order_id')
                    ->label('Order ID')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('order_total')
                    ->label('Order Total')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('used_at')
                    ->label('Used At')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('voucher_id')
                    ->label('Voucher')
                    ->relationship('voucher', 'voucher_code')
                    ->searchable(),

                Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('used_from')
                            ->label('Used From'),
                        Forms\Components\DatePicker::make('used_until')
                            ->label('Used Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['used_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('used_at', '>=', $date),
                            )
                            ->when(
                                $data['used_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('used_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('used_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVoucherUsage::route('/'),
            'view' => Pages\ViewVoucherUsage::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereDate('used_at', today())->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'info';
    }

    public static function canCreate(): bool
    {
        return false; // Usage records are created automatically
    }
}
    