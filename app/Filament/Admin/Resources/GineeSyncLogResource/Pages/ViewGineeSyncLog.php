<?php

namespace App\Filament\Admin\Resources\GineeSyncLogResource\Pages;

use App\Filament\Admin\Resources\GineeSyncLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewGineeSyncLog extends ViewRecord
{
    protected static string $resource = GineeSyncLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_session_logs')
                ->label('View Session Logs')
                ->icon('heroicon-o-queue-list')
                ->color('info')
                ->url(fn ($record) => 
                    static::$resource::getUrl('index', [
                        'tableFilters' => [
                            'session_id' => $record->session_id
                        ]
                    ])
                )
                ->visible(fn ($record) => !empty($record->session_id)),

            Actions\Action::make('retry_operation')
                ->label('Retry Operation')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Retry Failed Operation')
                ->modalDescription('This will retry the failed sync/push operation for this SKU.')
                ->action(function ($record) {
                    // Implement retry logic here
                    \Filament\Notifications\Notification::make()
                        ->title('🔄 Retry Operation')
                        ->body('Retry functionality coming soon!')
                        ->info()
                        ->send();
                })
                ->visible(fn ($record) => $record->status === 'failed'),
        ];
    }
}
