<?php

namespace App\Filament\Admin\Resources\VoucherResource\Pages;

use App\Filament\Admin\Resources\VoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewVoucher extends ViewRecord
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            
            Actions\Action::make('toggle_active')
                ->label(fn () => $this->record->is_active ? 'Deactivate' : 'Activate')
                ->icon(fn () => $this->record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                ->color(fn () => $this->record->is_active ? 'danger' : 'success')
                ->action(function () {
                    $this->record->update(['is_active' => !$this->record->is_active]);
                    $this->refreshFormData(['is_active']);
                })
                ->requiresConfirmation(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Voucher Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('voucher_code')
                            ->label('Voucher Code')
                            ->badge()
                            ->copyable(),
                        Infolists\Components\TextEntry::make('name_voucher')
                            ->label('Voucher Name'),
                        Infolists\Components\TextEntry::make('current_status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'pending' => 'warning',
                                'expired' => 'danger',
                                'quota_full' => 'secondary',
                                'inactive' => 'gray',
                                default => 'secondary',
                            }),
                    ])->columns(3),

                Infolists\Components\Section::make('Usage Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_used')
                            ->label('Times Used'),
                        Infolists\Components\TextEntry::make('quota')
                            ->label('Total Quota'),
                        Infolists\Components\TextEntry::make('remaining_quota')
                            ->label('Remaining')
                            ->formatStateUsing(fn () => max(0, $this->record->quota - $this->record->total_used)),
                        Infolists\Components\TextEntry::make('usage_percentage')
                            ->label('Usage %')
                            ->formatStateUsing(fn () => 
                                $this->record->quota > 0 
                                    ? round(($this->record->total_used / $this->record->quota) * 100, 1) . '%'
                                    : '0%'),
                    ])->columns(4),
            ]);
    }
}