<?php

namespace App\Filament\Admin\Resources\VoucherResource\Pages;

use App\Filament\Admin\Resources\VoucherResource;
use App\Services\VoucherSyncService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListVouchers extends ListRecords
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Voucher')
                ->icon('heroicon-o-plus'),
                
            Actions\Action::make('sync_vouchers')
                ->label('Sync from Spreadsheet')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->action(function () {
                    try {
                        $syncService = new VoucherSyncService();
                        $result = $syncService->syncFromSpreadsheet();
                        
                        Notification::make()
                            ->title('Voucher Sync Completed!')
                            ->body("Successfully processed {$result['processed']} vouchers with {$result['errors']} errors.")
                            ->success()
                            ->duration(5000)
                            ->send();
                            
                        // Refresh the page to show new data
                        $this->redirect(static::getUrl());
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Sync Failed')
                            ->body('Error: ' . $e->getMessage())
                            ->danger()
                            ->duration(10000)
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Sync Vouchers from Google Spreadsheet')
                ->modalDescription('This will fetch the latest voucher data from your Google Spreadsheet. Existing vouchers will be updated and new ones will be created.')
                ->modalSubmitActionLabel('Start Sync'),

            Actions\Action::make('open_spreadsheet')
                ->label('Open Spreadsheet')
                ->icon('heroicon-o-document-text')
                ->color('secondary')
                ->url('https://docs.google.com/spreadsheets/d/' . config('google-sheets.voucher.spreadsheet_id'))
                ->openUrlInNewTab(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Vouchers'),
            
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('is_active', true)
                          ->where('start_date', '<=', now())
                          ->where('end_date', '>=', now())
                          ->whereRaw('quota > total_used'))
                ->badge(fn () => 
                    static::getModel()::where('is_active', true)
                                     ->where('start_date', '<=', now())
                                     ->where('end_date', '>=', now())
                                     ->whereRaw('quota > total_used')
                                     ->count()),
            
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where('start_date', '>', now()))
                ->badge(fn () => 
                    static::getModel()::where('start_date', '>', now())->count()),
            
            'expired' => Tab::make('Expired')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->where(function ($q) {
                        $q->where('end_date', '<', now())
                          ->orWhere('is_active', false);
                    }))
                ->badge(fn () => 
                    static::getModel()::where(function ($q) {
                        $q->where('end_date', '<', now())
                          ->orWhere('is_active', false);
                    })->count()),
        ];
    }
}