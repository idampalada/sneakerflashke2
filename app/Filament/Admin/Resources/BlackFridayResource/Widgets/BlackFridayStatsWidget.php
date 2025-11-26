<?php
// File: app/Filament/Resources/BlackFridayResource/Widgets/BlackFridayStatsWidget.php

namespace App\Filament\Admin\Resources\BlackFridayResource\Widgets;

use App\Models\BlackFridayProduct;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BlackFridayStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalProducts = BlackFridayProduct::count();
        $activeProducts = BlackFridayProduct::where('is_active', true)->count();
        $flashSaleProducts = BlackFridayProduct::where('is_flash_sale', true)->where('is_active', true)->count();
        $outOfStockProducts = BlackFridayProduct::where('stock_quantity', 0)->count();
        $totalStock = BlackFridayProduct::sum('stock_quantity');
        
        // Calculate average discount
        $avgDiscount = BlackFridayProduct::whereNotNull('discount_percentage')
            ->where('discount_percentage', '>', 0)
            ->avg('discount_percentage');

        // Calculate total potential savings
        $totalSavings = BlackFridayProduct::whereNotNull('original_price')
            ->where('original_price', '>', 0)
            ->selectRaw('SUM((original_price - price) * stock_quantity) as total_savings')
            ->value('total_savings');

        return [
            Stat::make('Total Products', $totalProducts)
                ->description('All Black Friday products')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Active Products', $activeProducts)
                ->description('Currently on sale')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Flash Sale Items', $flashSaleProducts)
                ->description('Special flash deals')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('warning'),

            Stat::make('Out of Stock', $outOfStockProducts)
                ->description('Need restocking')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            Stat::make('Total Stock', number_format($totalStock))
                ->description('Items in inventory')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('info'),

            Stat::make('Avg Discount', $avgDiscount ? round($avgDiscount, 1) . '%' : '0%')
                ->description('Average discount rate')
                ->descriptionIcon('heroicon-m-tag')
                ->color('success'),

            Stat::make('Total Savings', $totalSavings ? 'Rp ' . number_format($totalSavings, 0, ',', '.') : 'Rp 0')
                ->description('Customer savings potential')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
        ];
    }

    protected function getColumns(): int
    {
        return 4; // Display 4 stats per row
    }
}