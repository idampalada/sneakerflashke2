<?php

namespace App\Filament\Admin\Resources\VoucherUsageResource\Pages;

use App\Filament\Admin\Resources\VoucherUsageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListVoucherUsage extends ListRecords
{
    protected static string $resource = VoucherUsageResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Usage'),
            
            'today' => Tab::make('Today')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereDate('used_at', today()))
                ->badge(fn () => 
                    static::getModel()::whereDate('used_at', today())->count()),
            
            'this_week' => Tab::make('This Week')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereBetween('used_at', [now()->startOfWeek(), now()->endOfWeek()]))
                ->badge(fn () => 
                    static::getModel()::whereBetween('used_at', [now()->startOfWeek(), now()->endOfWeek()])->count()),
            
            'this_month' => Tab::make('This Month')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereMonth('used_at', now()->month)
                          ->whereYear('used_at', now()->year))
                ->badge(fn () => 
                    static::getModel()::whereMonth('used_at', now()->month)
                                     ->whereYear('used_at', now()->year)
                                     ->count()),
        ];
    }
}