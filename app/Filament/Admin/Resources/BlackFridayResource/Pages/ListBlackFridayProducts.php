<?php

namespace App\Filament\Admin\Resources\BlackFridayResource\Pages;

use App\Filament\Admin\Resources\BlackFridayResource;
use App\Services\BlackFridayGoogleSheetsSync;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use Exception;

class ListBlackFridayProducts extends ListRecords
{
    protected static string $resource = BlackFridayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Smart Sync dari Google Sheets ke Products table
            Actions\Action::make('smart_sync')
                ->label('🖤 Smart Sync')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->tooltip('Sync from data_blackfriday Google Sheets to Products table')
                ->action(function () {
                    try {
                        $syncService = new BlackFridayGoogleSheetsSync();
                        $result = $syncService->syncFromGoogleSheets();
                        
                        if ($result['success']) {
                            Notification::make()
                                ->title('🖤 Black Friday Smart Sync Success!')
                                ->body("Synced {$result['synced']} products to Products table" . 
                                      ($result['errors'] > 0 ? " with {$result['errors']} errors" : ""))
                                ->success()
                                ->duration(5000)
                                ->send();
                        } else {
                            throw new Exception($result['error']);
                        }
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('🖤 Smart Sync Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->duration(8000)
                            ->send();
                    }
                }),

            // Create New Product
            Actions\CreateAction::make()
                ->label('➕ Add Product')
                ->icon('heroicon-o-plus')
                ->color('primary'),
        ];
    }

    public function getTitle(): string
    {
        return '🖤 Black Friday Products (from Products table)';
    }

    public function getSubheading(): string
    {
        $totalProducts = $this->getModel()::where('product_type', 'BLACKFRIDAY')->count();
        $activeProducts = $this->getModel()::where('product_type', 'BLACKFRIDAY')->where('is_active', true)->count();
        
        return "Total: {$totalProducts} | Active: {$activeProducts} | Source: Products table with BLACKFRIDAY type";
    }
}
