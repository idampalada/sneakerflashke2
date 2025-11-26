<?php
// File: app/Filament/Admin/Resources/CouponResource.php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\CouponResource\Pages;
use App\Models\Coupon;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\CheckboxList;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'E-Commerce';
    protected static ?int $navigationSort = 4;
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Coupon Details')
                    ->tabs([
                        Tabs\Tab::make('Basic Information')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('code')
                                            ->label('Coupon Code')
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(50)
                                            ->placeholder('e.g., SAVE20, WELCOME10')
                                            ->helperText('Unique code customers will enter. Will be converted to uppercase.')
                                            ->afterStateUpdated(fn ($state, $set) => $set('code', strtoupper($state))),

                                        TextInput::make('name')
                                            ->label('Coupon Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('e.g., Welcome Discount, Flash Sale'),
                                    ]),

                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(3)
                                    ->placeholder('Brief description of the coupon offer')
                                    ->columnSpanFull(),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true)
                                    ->helperText('Only active coupons can be used by customers'),
                            ]),

                        Tabs\Tab::make('Discount Settings')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('type')
                                            ->label('Discount Type')
                                            ->options([
                                                'percentage' => 'Percentage Off',
                                                'fixed_amount' => 'Fixed Amount Off',
                                                'free_shipping' => 'Free Shipping',
                                            ])
                                            ->required()
                                            ->reactive()
                                            ->afterStateUpdated(function ($state, $set) {
                                                if ($state === 'free_shipping') {
                                                    $set('value', 0);
                                                }
                                            }),

                                        TextInput::make('value')
                                            ->label(function (callable $get) {
                                                return match ($get('type')) {
                                                    'percentage' => 'Percentage (%)',
                                                    'fixed_amount' => 'Amount (Rp)',
                                                    'free_shipping' => 'Value (auto-set to 0)',
                                                    default => 'Value',
                                                };
                                            })
                                            ->numeric()
                                            ->required()
                                            ->minValue(0)
                                            ->disabled(fn (callable $get) => $get('type') === 'free_shipping')
                                            ->rules([
                                                fn (callable $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                                    if ($get('type') === 'percentage' && $value > 100) {
                                                        $fail('Percentage cannot exceed 100%');
                                                    }
                                                },
                                            ]),
                                    ]),

                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('minimum_amount')
                                            ->label('Minimum Order Amount (Rp)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->placeholder('0 = No minimum')
                                            ->helperText('Minimum cart value required to use this coupon'),

                                        TextInput::make('maximum_discount')
                                            ->label('Maximum Discount Amount (Rp)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->placeholder('0 = No maximum')
                                            ->helperText('Maximum discount amount (for percentage coupons)')
                                            ->visible(fn (callable $get) => $get('type') === 'percentage'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Usage Limits & Dates')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('usage_limit')
                                            ->label('Usage Limit')
                                            ->numeric()
                                            ->minValue(1)
                                            ->placeholder('Leave empty for unlimited')
                                            ->helperText('Total number of times this coupon can be used'),

                                        TextInput::make('used_count')
                                            ->label('Times Used')
                                            ->numeric()
                                            ->default(0)
                                            ->disabled()
                                            ->helperText('Current usage count (read-only)'),
                                    ]),

                                Grid::make(2)
                                    ->schema([
                                        DateTimePicker::make('starts_at')
                                            ->label('Start Date')
                                            ->placeholder('Leave empty for immediate activation')
                                            ->helperText('When this coupon becomes active'),

                                        DateTimePicker::make('expires_at')
                                            ->label('Expiry Date')
                                            ->placeholder('Leave empty for no expiration')
                                            ->helperText('When this coupon expires')
                                            ->after('starts_at'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Product & Category Restrictions')
                            ->schema([
                                Section::make('Applicable Products')
                                    ->description('Leave empty to apply to all products')
                                    ->schema([
                                        CheckboxList::make('applicable_products')
                                            ->label('Specific Products')
                                            ->options(Product::where('is_active', true)->pluck('name', 'id')->toArray())
                                            ->columns(2)
                                            ->searchable()
                                            ->bulkToggleable(),
                                    ]),

                                Section::make('Applicable Categories')
                                    ->description('Leave empty to apply to all categories')
                                    ->schema([
                                        CheckboxList::make('applicable_categories')
                                            ->label('Specific Categories')
                                            ->options(Category::where('is_active', true)->pluck('name', 'id')->toArray())
                                            ->columns(2)
                                            ->searchable()
                                            ->bulkToggleable(),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'success' => 'percentage',
                        'primary' => 'fixed_amount',
                        'warning' => 'free_shipping',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'percentage' => 'Percentage',
                        'fixed_amount' => 'Fixed Amount',
                        'free_shipping' => 'Free Shipping',
                        default => $state,
                    }),

                TextColumn::make('formatted_value')
                    ->label('Value')
                    ->sortable('value'),

                TextColumn::make('usage_progress')
                    ->label('Usage')
                    ->formatStateUsing(function (Coupon $record): string {
                        if (!$record->usage_limit) {
                            return "{$record->used_count} (Unlimited)";
                        }
                        $percentage = round(($record->used_count / $record->usage_limit) * 100, 1);
                        return "{$record->used_count}/{$record->usage_limit} ({$percentage}%)";
                    }),

                BadgeColumn::make('status_label')
                    ->label('Status')
                    ->colors([
                        'success' => 'Active',
                        'warning' => 'Scheduled',
                        'danger' => ['Expired', 'Used Up'],
                        'secondary' => 'Inactive',
                    ]),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->placeholder('Never'),

                BooleanColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'percentage' => 'Percentage Off',
                        'fixed_amount' => 'Fixed Amount Off',
                        'free_shipping' => 'Free Shipping',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'scheduled' => 'Scheduled',
                        'expired' => 'Expired',
                        'used_up' => 'Used Up',
                        'inactive' => 'Inactive',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!$data['value']) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'active' => $query->valid(),
                            'scheduled' => $query->where('is_active', true)->where('starts_at', '>', now()),
                            'expired' => $query->expired(),
                            'used_up' => $query->whereNotNull('usage_limit')->whereRaw('used_count >= usage_limit'),
                            'inactive' => $query->where('is_active', false),
                            default => $query,
                        };
                    }),

                Filter::make('expiring_soon')
                    ->label('Expiring Soon (7 days)')
                    ->query(fn (Builder $query): Builder => $query->expiring(7)),
            ])
            ->actions([
                Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (Coupon $record) {
                        $newCoupon = $record->replicate();
                        $newCoupon->code = Coupon::generateUniqueCode($record->code . '_', 4);
                        $newCoupon->name = $record->name . ' (Copy)';
                        $newCoupon->used_count = 0;
                        $newCoupon->save();

                        return redirect()->route('filament.admin.resources.coupons.edit', $newCoupon);
                    }),

                Action::make('toggle_status')
                    ->label(fn (Coupon $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Coupon $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (Coupon $record) => $record->is_active ? 'danger' : 'success')
                    ->action(fn (Coupon $record) => $record->update(['is_active' => !$record->is_active]))
                    ->requiresConfirmation(),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Coupon $record) {
                        if ($record->used_count > 0) {
                            throw new \Exception('Cannot delete a coupon that has been used in orders.');
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->used_count > 0) {
                                    throw new \Exception('Cannot delete coupons that have been used in orders.');
                                }
                            }
                        }),

                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-pause')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::valid()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}