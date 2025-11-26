<?php
// File: app/Filament/Admin/Resources/CouponResource/Pages/ListCoupons.php

namespace App\Filament\Admin\Resources\CouponResource\Pages;

use App\Filament\Admin\Resources\CouponResource;
use App\Models\Coupon;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Coupon'),
                
            Actions\Action::make('cleanup_expired')
                ->label('Cleanup Expired')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->action(function () {
                    $count = Coupon::where('expires_at', '<', now())
                                  ->where('is_active', true)
                                  ->update(['is_active' => false]);
                    
                    $this->notify('success', "Deactivated {$count} expired coupons");
                })
                ->requiresConfirmation()
                ->modalDescription('This will deactivate all expired coupons. This action cannot be undone.'),
                
            Actions\Action::make('generate_report')
                ->label('Download Report')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->action(function () {
                    // Generate and download coupon usage report
                    return response()->streamDownload(function () {
                        $coupons = Coupon::with('orders')
                            ->orderBy('created_at', 'desc')
                            ->get();
                            
                        $csv = fopen('php://output', 'w');
                        
                        // Header
                        fputcsv($csv, [
                            'Code', 'Name', 'Type', 'Value', 'Min Amount', 
                            'Usage Limit', 'Used Count', 'Total Discount Given',
                            'Revenue Generated', 'Status', 'Created', 'Expires'
                        ]);
                        
                        // Data
                        foreach ($coupons as $coupon) {
                            $stats = $coupon->stats;
                            fputcsv($csv, [
                                $coupon->code,
                                $coupon->name,
                                $coupon->type,
                                $coupon->value,
                                $coupon->minimum_amount ?? 0,
                                $coupon->usage_limit ?? 'Unlimited',
                                $coupon->used_count,
                                $stats['total_discount_given'],
                                $stats['revenue_impact'],
                                $coupon->status_label,
                                $coupon->created_at->format('Y-m-d'),
                                $coupon->expires_at?->format('Y-m-d') ?? 'Never'
                            ]);
                        }
                        
                        fclose($csv);
                    }, 'coupons-report-' . now()->format('Y-m-d') . '.csv');
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Coupons')
                ->badge(Coupon::count()),
                
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->valid())
                ->badge(Coupon::valid()->count())
                ->badgeColor('success'),
                
            'scheduled' => Tab::make('Scheduled')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('is_active', true)
                          ->where('starts_at', '>', now())
                )
                ->badge(Coupon::where('is_active', true)
                             ->where('starts_at', '>', now())
                             ->count())
                ->badgeColor('warning'),
                
            'expired' => Tab::make('Expired')
                ->modifyQueryUsing(fn (Builder $query) => $query->expired())
                ->badge(Coupon::expired()->count())
                ->badgeColor('danger'),
                
            'used_up' => Tab::make('Used Up')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereNotNull('usage_limit')
                          ->whereRaw('used_count >= usage_limit')
                )
                ->badge(Coupon::whereNotNull('usage_limit')
                             ->whereRaw('used_count >= usage_limit')
                             ->count())
                ->badgeColor('gray'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CouponResource\Widgets\CouponStatsWidget::class,
        ];
    }
}
