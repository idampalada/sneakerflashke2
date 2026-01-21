<?php

namespace App\Filament\Admin\Resources\KomerceOrderResource\Pages;

use App\Filament\Admin\Resources\KomerceOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListKomerceOrders extends ListRecords
{
    protected static string $resource = KomerceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh')
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->redirect(request()->header('Referer'))),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Orders')
                ->modifyQueryUsing(fn (Builder $query) => $query)
                ->badge(fn () => $this->getModel()::count()),

            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn () => $this->getModel()::where('status', 'pending')->count())
                ->badgeColor('warning'),

            'paid' => Tab::make('Paid Orders')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid'))
                ->badge(fn () => $this->getModel()::where('status', 'paid')->count())
                ->badgeColor('success'),

            'processing' => Tab::make('Processing')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'processing'))
                ->badge(fn () => $this->getModel()::where('status', 'processing')->count())
                ->badgeColor('primary'),

            'shipped' => Tab::make('Shipped')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'shipped'))
                ->badge(fn () => $this->getModel()::where('status', 'shipped')->count())
                ->badgeColor('info'),

            'delivered' => Tab::make('Delivered')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'delivered'))
                ->badge(fn () => $this->getModel()::where('status', 'delivered')->count())
                ->badgeColor('success'),

            'cancelled' => Tab::make('Cancelled')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'cancelled'))
                ->badge(fn () => $this->getModel()::where('status', 'cancelled')->count())
                ->badgeColor('danger'),

            'komerce_created' => Tab::make('In Komerce')
                ->modifyQueryUsing(function (Builder $query) {
                    return $query->where('status', 'paid')
                        ->whereRaw("meta_data->>'komerce_order_id' IS NOT NULL");
                })
                ->badge(function () {
                    return $this->getModel()::where('status', 'paid')
                        ->whereRaw("meta_data->>'komerce_order_id' IS NOT NULL")
                        ->count();
                })
                ->badgeColor('info'),

            'pickup_requested' => Tab::make('Pickup Requested')
                ->modifyQueryUsing(function (Builder $query) {
                    return $query->where('status', 'paid')
                        ->whereRaw("meta_data->'komerce_pickup' IS NOT NULL");
                })
                ->badge(function () {
                    return $this->getModel()::where('status', 'paid')
                        ->whereRaw("meta_data->'komerce_pickup' IS NOT NULL")
                        ->count();
                })
                ->badgeColor('warning'),

            'with_tracking' => Tab::make('Has Tracking')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('tracking_number'))
                ->badge(fn () => $this->getModel()::whereNotNull('tracking_number')->count())
                ->badgeColor('primary'),
        ];
    }


    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }

    protected function getTablePollingInterval(): ?string
    {
        return '30s'; // Auto refresh every 30 seconds
    }
}