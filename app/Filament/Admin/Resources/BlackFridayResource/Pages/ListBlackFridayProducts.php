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
            // ⭐ SMART SYNC: CREATE/UPDATE/DELETE Black Friday products
            Actions\Action::make('smart_sync')
                ->label('🧠 Smart Sync')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Smart Sync Black Friday Products')
                ->modalDescription('This will intelligently sync products from Google Sheets: CREATE new products, UPDATE existing ones, and DELETE products that are no longer in the spreadsheet. Only BLACKFRIDAY products will be affected - other product types will remain untouched.')
                ->modalSubmitActionLabel('Start Smart Sync')
                ->tooltip('Sync from data_blackfriday Google Sheets: CREATE/UPDATE/DELETE')
                ->action(function () {
                    try {
                        $syncService = new BlackFridayGoogleSheetsSync();
                        
                        // ⭐ USE SMART SYNC instead of regular sync
                        $result = $syncService->smartSync();
                        
                        if ($result['success']) {
                            $message = "Smart Sync completed successfully!\n\n";
                            $message .= "📊 RESULTS:\n";
                            $message .= "• Created: {$result['created']} new products\n";
                            $message .= "• Updated: {$result['updated']} existing products\n";
                            $message .= "• Deleted: {$result['deleted']} obsolete products\n";
                            $message .= "• Total processed: {$result['synced']} products\n";
                            $message .= "• Final count: {$result['final_count']} Black Friday products\n";
                            
                            if ($result['errors'] > 0) {
                                $message .= "• Errors: {$result['errors']} (check logs)";
                            }
                            
                            Notification::make()
                                ->title('🧠 Smart Sync Success!')
                                ->body($message)
                                ->success()
                                ->duration(8000)
                                ->send();
                        } else {
                            throw new Exception($result['error']);
                        }
                    } catch (Exception $e) {
                        Notification::make()
                            ->title('🧠 Smart Sync Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->duration(8000)
                            ->send();
                    }
                }),

            // ⭐ REGULAR SYNC: CREATE/UPDATE only (no delete)
            Actions\Action::make('regular_sync')
                ->label('🖤 Regular Sync')
                ->icon('heroicon-o-cloud-arrow-down')
                ->color('warning')
                ->tooltip('Sync from Google Sheets: CREATE/UPDATE only (no delete)')
                ->action(function () {
                    try {
                        $syncService = new BlackFridayGoogleSheetsSync();
                        $result = $syncService->syncFromGoogleSheets();
                        
                        if ($result['success']) {
                            Notification::make()
                                ->title('🖤 Regular Sync Success!')
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
                            ->title('🖤 Regular Sync Failed')
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
        $onSaleProducts = $this->getModel()::where('product_type', 'BLACKFRIDAY')
            ->whereNotNull('sale_price')
            ->whereColumn('sale_price', '<', 'price')
            ->count();
        
        return "Total: {$totalProducts} | Active: {$activeProducts} | On Sale: {$onSaleProducts} | Source: data_blackfriday Google Sheets";
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            // You can add widgets here for statistics if needed
        ];
    }
}