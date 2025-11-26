<?php
// File: app/Filament/Admin/Resources/CouponResource/Widgets/CouponStatsWidget.php

namespace App\Filament\Admin\Resources\CouponResource\Widgets;

use App\Models\Coupon;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CouponStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Get basic coupon statistics
        $totalCoupons = Coupon::count();
        $activeCoupons = Coupon::valid()->count();
        $expiredCoupons = Coupon::expired()->count();
        $usedUpCoupons = Coupon::whereNotNull('usage_limit')
                               ->whereRaw('used_count >= usage_limit')
                               ->count();

        // Get usage statistics
        $totalUsage = Coupon::sum('used_count');
        $totalDiscountGiven = Order::whereNotNull('coupon_id')->sum('discount_amount');
        
        // Get this month's statistics
        $thisMonthUsage = Order::whereNotNull('coupon_id')
                               ->whereYear('created_at', now()->year)
                               ->whereMonth('created_at', now()->month)
                               ->count();
        
        $thisMonthDiscount = Order::whereNotNull('coupon_id')
                                  ->whereYear('created_at', now()->year)
                                  ->whereMonth('created_at', now()->month)
                                  ->sum('discount_amount');

        // Calculate trends (compare with last month)
        $lastMonthUsage = Order::whereNotNull('coupon_id')
                               ->whereYear('created_at', now()->subMonth()->year)
                               ->whereMonth('created_at', now()->subMonth()->month)
                               ->count();

        $usageTrend = $lastMonthUsage > 0 
            ? round((($thisMonthUsage - $lastMonthUsage) / $lastMonthUsage) * 100, 1)
            : ($thisMonthUsage > 0 ? 100 : 0);

        return [
            Stat::make('Total Coupons', $totalCoupons)
                ->description('All coupons in system')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary'),

            Stat::make('Active Coupons', $activeCoupons)
                ->description("$expiredCoupons expired, $usedUpCoupons used up")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Total Usage', $totalUsage)
                ->description('All-time coupon redemptions')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info'),

            Stat::make('Total Discount Given', 'Rp ' . number_format($totalDiscountGiven, 0, ',', '.'))
                ->description('Total savings provided to customers')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('warning'),

            Stat::make('This Month Usage', $thisMonthUsage)
                ->description($usageTrend >= 0 ? "+{$usageTrend}% from last month" : "{$usageTrend}% from last month")
                ->descriptionIcon($usageTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($usageTrend >= 0 ? 'success' : 'danger'),

            Stat::make('This Month Discount', 'Rp ' . number_format($thisMonthDiscount, 0, ',', '.'))
                ->description('Discount given this month')
                ->descriptionIcon('heroicon-m-gift')
                ->color('success'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}