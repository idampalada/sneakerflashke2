<?php

namespace App\Filament\Admin\Resources\VoucherUsageResource\Pages;

use App\Filament\Admin\Resources\VoucherUsageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewVoucherUsage extends ViewRecord
{
    protected static string $resource = VoucherUsageResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Usage Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('voucher.voucher_code')
                            ->label('Voucher Code')
                            ->badge(),
                        Infolists\Components\TextEntry::make('voucher.name_voucher')
                            ->label('Voucher Name'),
                        Infolists\Components\TextEntry::make('customer_email')
                            ->label('Customer Email'),
                        Infolists\Components\TextEntry::make('order_id')
                            ->label('Order ID')
                            ->copyable(),
                    ])->columns(2),

                Infolists\Components\Section::make('Financial Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('order_total')
                            ->label('Order Total')
                            ->money('IDR'),
                        Infolists\Components\TextEntry::make('discount_amount')
                            ->label('Discount Applied')
                            ->money('IDR'),
                        Infolists\Components\TextEntry::make('final_amount')
                            ->label('Final Amount')
                            ->formatStateUsing(fn () => 
                                'Rp ' . number_format($this->record->order_total - $this->record->discount_amount, 0, ',', '.'))
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('discount_percentage')
                            ->label('Discount %')
                            ->formatStateUsing(fn () => 
                                $this->record->order_total > 0 
                                    ? round(($this->record->discount_amount / $this->record->order_total) * 100, 1) . '%'
                                    : '0%'),
                    ])->columns(2),

                Infolists\Components\Section::make('Timing')
                    ->schema([
                        Infolists\Components\TextEntry::make('used_at')
                            ->label('Used At')
                            ->dateTime('d/m/Y H:i:s'),
                        Infolists\Components\TextEntry::make('voucher.start_date')
                            ->label('Voucher Valid From')
                            ->dateTime('d/m/Y H:i:s'),
                        Infolists\Components\TextEntry::make('voucher.end_date')
                            ->label('Voucher Valid Until')
                            ->dateTime('d/m/Y H:i:s'),
                    ])->columns(3),
            ]);
    }
}