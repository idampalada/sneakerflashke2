<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction as ExcelExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;


class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Order Information')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->required()
                            ->maxLength(100)
                            ->disabled(),
                        
                        Forms\Components\Select::make('user_id')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->label('User (if registered)'),
                            
                        Forms\Components\TextInput::make('customer_name')
                            ->required()
                            ->maxLength(255)
                            ->label('Customer Name'),
                            
                        Forms\Components\TextInput::make('customer_email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->label('Customer Email'),
                            
                        Forms\Components\TextInput::make('customer_phone')
                            ->tel()
                            ->maxLength(20)
                            ->label('Customer Phone'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Order Status')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->required()
                            ->options([
                                'pending' => '⏳ Pending',
                                'paid' => '✅ Paid',
                                'processing' => '🔄 Processing',
                                'shipped' => '🚚 Shipped',
                                'delivered' => '📦 Delivered',
                                'cancelled' => '❌ Cancelled',
                                'refund' => '💰 Refund',
                            ])
                            ->default('pending')
                            ->selectablePlaceholder(false)
                            ->native(false)
                            ->helperText('Single status field for order lifecycle'),
                            
                        Forms\Components\TextInput::make('payment_method')
                            ->maxLength(255)
                            ->label('Payment Method')
                            ->disabled(),
                            
                        Forms\Components\Placeholder::make('status_info')
                            ->label('Status Information')
                            ->content(function ($record) {
                                if (!$record) return 'New order';
                                
                                $statusInfo = [
                                    'pending' => 'Order created, awaiting payment (for online) or processing (for COD)',
                                    'paid' => 'Payment received, ready for processing',
                                    'processing' => 'Order is being prepared for shipment',
                                    'shipped' => 'Order has been shipped to customer',
                                    'delivered' => 'Order has been delivered successfully',
                                    'cancelled' => 'Order has been cancelled',
                                    'refund' => 'Order has been refunded'
                                ];
                                
                                return $statusInfo[$record->status] ?? 'Unknown status';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->step(0.01)
                            ->disabled(),
                            
                        Forms\Components\TextInput::make('tax_amount')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->step(0.01)
                            ->default(0)
                            ->disabled(),
                            
                        Forms\Components\TextInput::make('shipping_cost')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->step(0.01)
                            ->default(0),
                            
                        Forms\Components\TextInput::make('discount_amount')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->step(0.01)
                            ->default(0),
                            
                        Forms\Components\TextInput::make('total_amount')
                            ->required()
                            ->numeric()
                            ->prefix('Rp')
                            ->step(0.01)
                            ->disabled(),
                            
                        Forms\Components\TextInput::make('currency')
                            ->required()
                            ->maxLength(3)
                            ->default('IDR')
                            ->disabled(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Customer Addresses')
                    ->schema([
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Placeholder::make('shipping_address_display')
                                    ->label('Shipping Address')
                                    ->content(function ($record) {
                                        if (!$record) return 'No shipping address';
                                        
                                        $parts = [];
                                        
                                        if ($record->shipping_address) {
                                            if (is_array($record->shipping_address)) {
                                                $parts[] = implode(', ', $record->shipping_address);
                                            } else {
                                                $parts[] = $record->shipping_address;
                                            }
                                        }
                                        
                                        if ($record->shipping_destination_label) {
                                            $parts[] = $record->shipping_destination_label;
                                        }
                                        
                                        if ($record->shipping_postal_code) {
                                            $parts[] = 'Postal Code: ' . $record->shipping_postal_code;
                                        }
                                        
                                        return implode("\n", $parts) ?: 'No shipping address';
                                    })
                                    ->extraAttributes(['style' => 'white-space: pre-line; background: #f9fafb; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb;']),
                            ])
                            ->columnSpan(1),
                            
                        Forms\Components\Group::make()
                            ->schema([
                                Forms\Components\Placeholder::make('billing_address_display')
                                    ->label('Billing Address')
                                    ->content(function ($record) {
                                        if (!$record || !$record->billing_address) {
                                            return 'Same as shipping address';
                                        }
                                        
                                        if (is_array($record->billing_address)) {
                                            return implode("\n", $record->billing_address);
                                        }
                                        
                                        return $record->billing_address;
                                    })
                                    ->extraAttributes(['style' => 'white-space: pre-line; background: #f9fafb; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb;']),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Shipping & Tracking')
                    ->schema([
                        Forms\Components\TextInput::make('tracking_number')
                            ->maxLength(255)
                            ->label('Tracking Number')
                            ->placeholder('Enter tracking number when shipped'),
                            
                        Forms\Components\DateTimePicker::make('shipped_at')
                            ->label('Shipped At')
                            ->placeholder('Set when order is shipped'),
                            
                        Forms\Components\DateTimePicker::make('delivered_at')
                            ->label('Delivered At')
                            ->placeholder('Set when order is delivered'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Order Notes')
                            ->rows(3)
                            ->placeholder('Any special notes for this order...')
                            ->columnSpanFull(),
                            
                        Forms\Components\Placeholder::make('payment_info')
                            ->label('Payment Information')
                            ->content(function ($record) {
                                if (!$record) return 'No payment information';
                                
                                $info = [];
                                $info[] = "Payment Method: " . strtoupper(str_replace('_', ' ', $record->payment_method ?? 'Not set'));
                                
                                if ($record->snap_token) {
                                    $info[] = "Midtrans Token: " . substr($record->snap_token, 0, 20) . '...';
                                }
                                
                                if ($record->payment_response) {
                                    $response = is_array($record->payment_response) 
                                        ? $record->payment_response 
                                        : json_decode($record->payment_response, true);
                                    
                                    if ($response) {
                                        $info[] = "Transaction Status: " . ($response['transaction_status'] ?? 'Unknown');
                                        $info[] = "Payment Type: " . ($response['payment_type'] ?? 'Unknown');
                                        
                                        if (isset($response['transaction_time'])) {
                                            $info[] = "Transaction Time: " . $response['transaction_time'];
                                        }
                                    }
                                }
                                
                                return implode("\n", $info);
                            })
                            ->extraAttributes(['style' => 'white-space: pre-line; background: #f9fafb; padding: 12px; border-radius: 6px; border: 1px solid #e5e7eb;'])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Order Items')
                    ->schema([
                        Forms\Components\Placeholder::make('order_items_display')
                            ->label('Products Ordered')
                            ->content(function ($record) {
                                if (!$record) return 'No items';
                                
                                $orderItems = $record->orderItems()->with('product')->get();
                                
                                if ($orderItems->isEmpty()) {
                                    return 'No items found';
                                }
                                
                                $itemsDisplay = [];
                                
                                foreach ($orderItems as $item) {
                                    $itemInfo = [];
                                    
                                    $itemInfo[] = "📦 " . $item->product_name;
                                    $itemInfo[] = "   SKU: " . ($item->product_sku ?: 'N/A');
                                    $itemInfo[] = "   Quantity: " . $item->quantity;
                                    $itemInfo[] = "   Price: Rp " . number_format($item->product_price, 0, ',', '.');
                                    $itemInfo[] = "   Total: Rp " . number_format($item->total_price, 0, ',', '.');
                                    
                                    if ($item->product) {
                                        $product = $item->product;
                                        
                                        if ($product->available_sizes) {
                                            $sizes = is_array($product->available_sizes) 
                                                ? $product->available_sizes 
                                                : json_decode($product->available_sizes, true);
                                            
                                            if ($sizes && !empty($sizes)) {
                                                $itemInfo[] = "   Available Sizes: " . implode(', ', $sizes);
                                            }
                                        }
                                        
                                        if ($product->brand) {
                                            $itemInfo[] = "   Brand: " . $product->brand;
                                        }
                                        
                                        if ($product->available_colors) {
                                            $colors = is_array($product->available_colors) 
                                                ? $product->available_colors 
                                                : json_decode($product->available_colors, true);
                                            
                                            if ($colors && !empty($colors)) {
                                                $itemInfo[] = "   Available Colors: " . implode(', ', $colors);
                                            }
                                        }
                                        
                                        $itemInfo[] = "   Current Stock: " . $product->stock_quantity;
                                        
                                        if ($product->category) {
                                            $itemInfo[] = "   Category: " . $product->category->name;
                                        }
                                    } else {
                                        $itemInfo[] = "   ⚠️ Product no longer available in database";
                                    }
                                    
                                    $itemsDisplay[] = implode("\n", $itemInfo);
                                }
                                
                                return implode("\n\n" . str_repeat('─', 50) . "\n\n", $itemsDisplay);
                            })
                            ->extraAttributes(['style' => 'white-space: pre-line; background: #f9fafb; padding: 16px; border-radius: 8px; border: 1px solid #e5e7eb; font-family: monospace; max-height: 400px; overflow-y: auto;'])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            // ✅ OPTIMASI: Eager Loading dengan error handling
            ->modifyQueryUsing(function (Builder $query) {
                return $query
                    ->with([
                        'orderItems' => function ($query) {
                            $query->select('id', 'order_id', 'product_id', 'product_name', 'product_sku')
                                  ->with(['product' => function ($q) {
                                      $q->select('id', 'available_sizes');
                                  }])
                                  ->limit(1);
                        }
                    ]);
            })
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->label('Order #')
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('customer_name')
                    ->searchable()
                    ->sortable()
                    ->label('Customer'),
                    
                Tables\Columns\TextColumn::make('customer_email')
                    ->searchable()
                    ->label('Email')
                    ->limit(30)
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('customer_phone')
                    ->searchable()
                    ->label('Phone')
                    ->copyable()
                    ->placeholder('—'),
                    
                Tables\Columns\TextColumn::make('shipping_address')
                    ->label('Shipping Address')
                    ->limit(50)
                    ->wrap()
                    ->getStateUsing(function (Order $record) {
                        if (!$record->shipping_address) {
                            return '-';
                        }
                        
                        // Jika shipping_address adalah JSON array
                        if (is_array($record->shipping_address)) {
                            return implode(', ', $record->shipping_address);
                        }
                        
                        // Jika string biasa
                        return $record->shipping_address;
                    })
                    ->searchable(false)
                    ->tooltip(function (Order $record) {
                        if (!$record->shipping_address) {
                            return null;
                        }
                        
                        if (is_array($record->shipping_address)) {
                            return implode("\n", $record->shipping_address);
                        }
                        
                        return $record->shipping_address;
                    }),

                // ✅ OPTIMIZED: Produk + Size (dengan eager loading optimal)
                Tables\Columns\TextColumn::make('product_name_first')
                    ->label('Produk')
                    ->getStateUsing(function (Order $record) {
                        // Gunakan relasi yang sudah di-eager load
                        $item = $record->orderItems->first();
                        if (!$item) {
                            return '-';
                        }

                        $name = $item->product_name;

                        // Ambil size dari product jika ada
                        if ($item->product && $item->product->available_sizes) {
                            $sizes = is_array($item->product->available_sizes)
                                ? $item->product->available_sizes
                                : json_decode($item->product->available_sizes, true);

                            if (is_array($sizes) && count($sizes) > 0) {
                                // Ambil size pertama saja
                                $name .= ' - Size ' . $sizes[0];
                            }
                        }

                        return $name;
                    })
                    ->limit(60)
                    ->wrap()
                    ->searchable(false), // Disable search untuk performa

                // ✅ OPTIMIZED: SKU
                Tables\Columns\TextColumn::make('product_sku_first')
                    ->label('SKU')
                    ->getStateUsing(function (Order $record) {
                        return optional($record->orderItems->first())->product_sku ?? '-';
                    })
                    ->limit(30)
                    ->wrap()
                    ->searchable(false),

                // ✅ OPTIMIZED: Shipping dengan cache regex
                Tables\Columns\TextColumn::make('shipping')
                    ->label('Shipping')
                    ->getStateUsing(function (Order $record) {
                        if (empty($record->notes)) {
                            return 'N/A';
                        }

                        if (preg_match('/Shipping:\s*(\w+)\s+(\w+)/i', $record->notes, $matches)) {
                            return $matches[1] . ' - ' . $matches[2];
                        }

                        return 'N/A';
                    })
                    ->searchable(false),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => ['paid', 'delivered'],
                        'primary' => 'processing',
                        'info' => 'shipped',
                        'danger' => 'cancelled',
                        'secondary' => 'refund',
                    ])
                    ->icons([
                        'heroicon-o-clock' => 'pending',
                        'heroicon-o-check-circle' => 'paid',
                        'heroicon-o-cog-6-tooth' => 'processing',
                        'heroicon-o-truck' => 'shipped',
                        'heroicon-o-inbox-stack' => 'delivered',
                        'heroicon-o-x-circle' => 'cancelled',
                        'heroicon-o-banknotes' => 'refund',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'pending' => '⏳ Pending',
                        'paid' => '✅ Paid',
                        'processing' => '🔄 Processing',
                        'shipped' => '🚚 Shipped',
                        'delivered' => '📦 Delivered',
                        'cancelled' => '❌ Cancelled',
                        'refund' => '💰 Refund',
                        default => ucfirst($state)
                    })
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('IDR')
                    ->sortable()
                    ->label('Total')
                    ->weight('bold'),
                    
                Tables\Columns\TextColumn::make('payment_method')
                    ->searchable()
                    ->label('Payment')
                    ->formatStateUsing(fn (string $state): string => strtoupper(str_replace('_', ' ', $state)))
                    ->badge(),
                    
                Tables\Columns\TextColumn::make('tracking_number')
                    ->searchable()
                    ->placeholder('—')
                    ->label('Tracking')
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->label('Order Date'),
                    
                // ✅ OPTIMIZED: Items count dengan withCount
                Tables\Columns\TextColumn::make('order_items_count')
                    ->counts('orderItems')
                    ->label('Items')
                    ->badge()
                    ->color('primary'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => '⏳ Pending',
                        'paid' => '✅ Paid',
                        'processing' => '🔄 Processing',
                        'shipped' => '🚚 Shipped',
                        'delivered' => '📦 Delivered',
                        'cancelled' => '❌ Cancelled',
                        'refund' => '💰 Refund',
                    ]),
                    
                Tables\Filters\SelectFilter::make('payment_method')
                    ->options([
                        'bank_transfer' => 'Bank Transfer',
                        'credit_card' => 'Credit Card',
                        'ewallet' => 'E-Wallet',
                        'cod' => 'Cash on Delivery',
                    ]),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([
                ExcelExportAction::make('export_paid')
                    ->label('Export Paid')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->exports([
                        ExcelExport::make('paid-orders')
                            ->fromTable()
                            ->modifyQueryUsing(
                                fn (Builder $query) => $query->where('status', 'paid')
                            )
                            ->askForFilename()
                            ->askForWriterType()
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('print_order')
                    ->label('Print Order')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Order $record) => route('orders.print', ['orderNumber' => $record->order_number]), true)
                    ->openUrlInNewTab(),
                
                Tables\Actions\Action::make('mark_paid')
                    ->label('Mark Paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record) => $record->status === 'pending')
                    ->action(function (Order $record) {
                        $record->update(['status' => 'paid']);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Mark Order as Paid')
                    ->modalDescription('This will mark the order as paid and ready for processing.'),
                    
                Tables\Actions\Action::make('start_processing')
                    ->label('Start Processing')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('primary')
                    ->visible(fn (Order $record) => $record->status === 'paid')
                    ->action(function (Order $record) {
                        $record->update(['status' => 'processing']);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Start Order Processing')
                    ->modalDescription('This will mark the order as being processed.'),
                    
                Tables\Actions\Action::make('mark_shipped')
                    ->label('Mark Shipped')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn (Order $record) => $record->status === 'processing')
                    ->form([
                        Forms\Components\TextInput::make('tracking_number')
                            ->label('Tracking Number')
                            ->required()
                            ->placeholder('Enter tracking number'),
                    ])
                    ->action(function (Order $record, array $data) {
                        $record->update([
                            'status' => 'shipped',
                            'tracking_number' => $data['tracking_number'],
                            'shipped_at' => now(),
                        ]);
                    })
                    ->modalHeading('Mark Order as Shipped'),
                    
                Tables\Actions\Action::make('mark_delivered')
                    ->label('Mark Delivered')
                    ->icon('heroicon-o-inbox-stack')
                    ->color('success')
                    ->visible(fn (Order $record) => $record->status === 'shipped')
                    ->action(function (Order $record) {
                        $record->update([
                            'status' => 'delivered',
                            'delivered_at' => now(),
                        ]);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Mark Order as Delivered')
                    ->modalDescription('This will mark the order as successfully delivered to the customer.'),
                    
                Tables\Actions\Action::make('cancel_order')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Order $record) => in_array($record->status, ['pending', 'paid', 'processing']))
                    ->form([
                        Forms\Components\Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->placeholder('Please provide a reason for cancellation...'),
                    ])
                    ->action(function (Order $record, array $data) {
                        foreach ($record->orderItems as $item) {
                            $product = \App\Models\Product::find($item->product_id);
                            if ($product) {
                                $product->increment('stock_quantity', $item->quantity);
                            }
                        }
                        
                        $note = "[" . now()->format('Y-m-d H:i:s') . "] Order cancelled: " . $data['cancellation_reason'];
                        $existingNotes = $record->notes ? $record->notes . "\n" : '';
                        
                        $record->update([
                            'status' => 'cancelled',
                            'notes' => $existingNotes . $note
                        ]);
                    })
                    ->modalHeading('Cancel Order')
                    ->modalDescription('This will cancel the order and restore stock quantities.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                    
                    Tables\Actions\BulkAction::make('bulk_mark_paid')
                        ->label('Mark as Paid')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(function($record) {
                                if ($record->status === 'pending') {
                                    $record->update(['status' => 'paid']);
                                }
                            });
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                        
                    Tables\Actions\BulkAction::make('bulk_start_processing')
                        ->label('Start Processing')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->color('primary')
                        ->action(function ($records) {
                            $records->each(function($record) {
                                if ($record->status === 'paid') {
                                    $record->update(['status' => 'processing']);
                                }
                            });
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                        
                    Tables\Actions\BulkAction::make('bulk_cancel')
                        ->label('Cancel Orders')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            Forms\Components\Textarea::make('cancellation_reason')
                                ->label('Cancellation Reason')
                                ->required()
                                ->placeholder('Please provide a reason for bulk cancellation...'),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each(function($record) use ($data) {
                                if (in_array($record->status, ['pending', 'paid', 'processing'])) {
                                    foreach ($record->orderItems as $item) {
                                        $product = \App\Models\Product::find($item->product_id);
                                        if ($product) {
                                            $product->increment('stock_quantity', $item->quantity);
                                        }
                                    }
                                    
                                    $note = "[" . now()->format('Y-m-d H:i:s') . "] Bulk cancellation: " . $data['cancellation_reason'];
                                    $existingNotes = $record->notes ? $record->notes . "\n" : '';
                                    
                                    $record->update([
                                        'status' => 'cancelled',
                                        'notes' => $existingNotes . $note
                                    ]);
                                }
                            });
                        })
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->poll(null);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $pendingCount = static::getModel()::where('status', 'pending')->count();
        $paidCount = static::getModel()::where('status', 'paid')->count();
        
        $totalActionRequired = $pendingCount + $paidCount;
        
        return $totalActionRequired > 0 ? (string) $totalActionRequired : null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        $pendingCount = static::getModel()::where('status', 'pending')->count();
        $paidCount = static::getModel()::where('status', 'paid')->count();
        
        if ($pendingCount > 0) {
            return 'warning';
        } elseif ($paidCount > 0) {
            return 'success';
        }
        
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $pendingCount = static::getModel()::where('status', 'pending')->count();
        $paidCount = static::getModel()::where('status', 'paid')->count();
        
        $tooltips = [];
        
        if ($pendingCount > 0) {
            $tooltips[] = "{$pendingCount} pending order" . ($pendingCount > 1 ? 's' : '');
        }
        
        if ($paidCount > 0) {
            $tooltips[] = "{$paidCount} paid order" . ($paidCount > 1 ? 's' : '') . " ready for processing";
        }
        
        return empty($tooltips) ? null : implode(', ', $tooltips);
    }

    public static function getStatusStats(): array
    {
        return static::getModel()::selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();
    }

    public static function getTodayOrdersCount(): int
    {
        return static::getModel()::whereDate('created_at', today())->count();
    }

    public static function getTodayRevenue(): float
    {
        return static::getModel()::whereDate('created_at', today())
                    ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
                    ->sum('total_amount');
    }
}