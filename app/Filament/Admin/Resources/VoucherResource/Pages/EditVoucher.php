<?php

// =====================================
// VOUCHER PAGES - EDIT (COMPLETE)
// File: app/Filament/Admin/Resources/VoucherResource/Pages/EditVoucher.php
// =====================================

namespace App\Filament\Admin\Resources\VoucherResource\Pages;

use App\Filament\Admin\Resources\VoucherResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditVoucher extends EditRecord
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label('View Details')
                ->icon('heroicon-o-eye'),
                
            Actions\Action::make('toggle_active')
                ->label(fn () => $this->record->is_active ? 'Deactivate' : 'Activate')
                ->icon(fn () => $this->record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                ->color(fn () => $this->record->is_active ? 'danger' : 'success')
                ->action(function () {
                    $this->record->update(['is_active' => !$this->record->is_active]);
                    
                    $status = $this->record->is_active ? 'activated' : 'deactivated';
                    
                    Notification::make()
                        ->title("Voucher {$status}")
                        ->body("Voucher {$this->record->voucher_code} has been {$status}")
                        ->success()
                        ->send();
                        
                    // Refresh the form to show updated status
                    $this->refreshSpecificFormFields(['is_active']);
                })
                ->requiresConfirmation()
                ->modalHeading(fn () => $this->record->is_active ? 'Deactivate Voucher' : 'Activate Voucher')
                ->modalDescription(fn () => $this->record->is_active 
                    ? 'Are you sure you want to deactivate this voucher? Customers will no longer be able to use it.'
                    : 'Are you sure you want to activate this voucher? Customers will be able to use it immediately.'),

            Actions\Action::make('test_voucher')
                ->label('Test Voucher')
                ->icon('heroicon-o-beaker')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\TextInput::make('test_amount')
                        ->label('Test Order Amount (Rp)')
                        ->numeric()
                        ->default(100000)
                        ->required()
                        ->helperText('Enter an order amount to test if this voucher would be valid'),
                        
                    \Filament\Forms\Components\TextInput::make('test_customer_id')
                        ->label('Test Customer ID')
                        ->default('test_customer_' . time())
                        ->helperText('Leave as default or enter a specific customer ID to test'),
                ])
                ->action(function (array $data) {
                    $validation = $this->record->isValidForUser(
                        $data['test_customer_id'], 
                        $data['test_amount']
                    );
                    
                    if ($validation['valid']) {
                        Notification::make()
                            ->title('✅ Voucher is Valid!')
                            ->body("Discount would be: Rp " . number_format($validation['discount'], 0, ',', '.'))
                            ->success()
                            ->duration(8000)
                            ->send();
                    } else {
                        Notification::make()
                            ->title('❌ Voucher is Invalid')
                            ->body($validation['message'])
                            ->warning()
                            ->duration(8000)
                            ->send();
                    }
                })
                ->modalHeading('Test Voucher Validation')
                ->modalDescription('This will test if the voucher would be valid for a customer with the specified order amount.')
                ->modalSubmitActionLabel('Test Voucher'),

            Actions\Action::make('duplicate')
                ->label('Duplicate Voucher')
                ->icon('heroicon-o-document-duplicate')
                ->color('secondary')
                ->action(function () {
                    $newVoucherData = $this->record->toArray();
                    
                    // Remove unique fields and reset counters
                    unset($newVoucherData['id']);
                    unset($newVoucherData['created_at']);
                    unset($newVoucherData['updated_at']);
                    
                    $newVoucherData['voucher_code'] = $this->record->voucher_code . '_COPY_' . time();
                    $newVoucherData['name_voucher'] = $this->record->name_voucher . ' (Copy)';
                    $newVoucherData['total_used'] = 0;
                    $newVoucherData['sync_status'] = 'pending';
                    $newVoucherData['spreadsheet_row_id'] = null;
                    
                    $newVoucher = \App\Models\Voucher::create($newVoucherData);
                    
                    Notification::make()
                        ->title('Voucher Duplicated')
                        ->body("New voucher created with code: {$newVoucher->voucher_code}")
                        ->success()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('view')
                                ->button()
                                ->url(VoucherResource::getUrl('edit', ['record' => $newVoucher]))
                                ->label('Edit New Voucher'),
                        ])
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('Duplicate Voucher')
                ->modalDescription('This will create a copy of this voucher with a new code. You can then modify the copy as needed.')
                ->modalSubmitActionLabel('Create Duplicate'),

            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Voucher')
                ->modalDescription('Are you sure you want to delete this voucher? This action cannot be undone. All usage history will be preserved.')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Voucher deleted')
                        ->body('The voucher has been deleted successfully.'),
                ),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure voucher code is uppercase and trimmed
        if (isset($data['voucher_code'])) {
            $data['voucher_code'] = strtoupper(trim($data['voucher_code']));
        }

        // Trim other text fields
        if (isset($data['name_voucher'])) {
            $data['name_voucher'] = trim($data['name_voucher']);
        }

        if (isset($data['value'])) {
            $data['value'] = trim($data['value']);
        }

        // Mark as pending sync if important fields changed
        $importantFields = [
            'voucher_code', 'name_voucher', 'voucher_type', 'value', 
            'quota', 'min_purchase', 'discount_max', 'start_date', 
            'end_date', 'category_customer', 'code_product'
        ];
        
        foreach ($importantFields as $field) {
            if (isset($data[$field]) && $data[$field] !== $this->record->getOriginal($field)) {
                $data['sync_status'] = 'pending';
                break;
            }
        }

        // Validate voucher type and value consistency
        if (isset($data['voucher_type']) && isset($data['value'])) {
            if ($data['voucher_type'] === 'NOMINAL' && !str_contains($data['value'], 'Rp')) {
                // Add Rp prefix if it's nominal but doesn't have it
                if (is_numeric(str_replace(['.', ','], '', $data['value']))) {
                    $data['value'] = 'Rp' . number_format((float) str_replace(['.', ','], '', $data['value']), 0, ',', '.');
                }
            } elseif ($data['voucher_type'] === 'PERCENT' && !str_contains($data['value'], '%')) {
                // Add % suffix if it's percentage but doesn't have it
                if (is_numeric($data['value'])) {
                    $data['value'] = $data['value'] . '%';
                }
            }
        }

        // Ensure discount_max is set for percentage vouchers
        if (isset($data['voucher_type']) && $data['voucher_type'] === 'PERCENT') {
            if (empty($data['discount_max']) || $data['discount_max'] <= 0) {
                $data['discount_max'] = 1000000; // Default max 1 million
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Send notification about the update
        Notification::make()
            ->title('Voucher Updated')
            ->body("Voucher {$this->record->voucher_code} has been updated successfully")
            ->success()
            ->send();

        // If sync status was changed to pending, suggest running sync
        if ($this->record->sync_status === 'pending') {
            Notification::make()
                ->title('Sync Recommended')
                ->body('This voucher has pending changes. Consider syncing to update the spreadsheet.')
                ->warning()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('sync_now')
                        ->button()
                        ->url('/admin/voucher-sync')
                        ->label('Go to Sync Page'),
                ])
                ->persistent()
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getSavedNotification(): ?\Filament\Notifications\Notification
    {
        return null; // We handle notifications in afterSave()
    }

    // Custom method to refresh specific form fields
    protected function refreshSpecificFormFields(array $fields): void
    {
        $this->record->refresh();
        
        foreach ($fields as $field) {
            $this->data[$field] = $this->record->{$field};
        }
    }
}