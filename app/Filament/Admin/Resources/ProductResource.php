<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ProductResource\Pages;
use App\Imports\ProductsImport;
use App\Exports\ProductTemplateExport;
use App\Services\GoogleSheetsSync;
use App\Models\Product;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Shop';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        $schema = [
            // Basic Information Section
            Forms\Components\Section::make('Basic Information')
                ->description('Essential product details that will appear on your website')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Product Name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $context, $state, callable $set) {
                            if ($context === 'create') {
                                $set('slug', Str::slug($state));
                            }
                        })
                        ->placeholder('e.g., Nike Air Jordan 1 Retro High')
                        ->helperText('This will be the main product title shown to customers'),

                    Forms\Components\TextInput::make('slug')
                        ->label('URL Slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(Product::class, 'slug', ignoreRecord: true)
                        ->helperText('Auto-generated from product name. This creates the product URL: /products/your-slug'),

                    Forms\Components\Select::make('category_id')
                        ->label('Category')
                        ->options(function () {
                            return Category::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray();
                        })
                        ->required()
                        ->placeholder('Select a category')
                        ->native(true)
                        ->helperText('Main category for product organization and filtering'),

                    Forms\Components\TextInput::make('brand')
                        ->label('Brand')
                        ->maxLength(255)
                        ->placeholder('e.g., Nike, Adidas, Puma')
                        ->helperText('Brand name for filtering in BRAND menu')
                        ->datalist([
                            'Nike',
                            'Adidas', 
                            'Puma',
                            'Converse',
                            'Vans',
                            'New Balance',
                            'Jordan',
                            'Reebok',
                            'ASICS',
                            'Under Armour',
                            'Skechers',
                            'Fila',
                            'DC Shoes',
                            'Timberland',
                        ]),
                ])->columns(2),

            // ⭐ SKU Information Section - From Excel Columns
            Forms\Components\Section::make('SKU Information')
                ->description('SKU Parent groups similar products, individual SKU identifies specific variants')
                ->schema([
                    Forms\Components\TextInput::make('sku_parent')
                        ->label('SKU Parent (Product Family)')
                        ->maxLength(255)
                        ->placeholder('e.g., AIR-FORCE-1-TRIPLE-WHITE')
                        ->helperText('Parent SKU that groups similar products together (same design, different sizes)')
                        ->datalist(function () {
                            return Product::whereNotNull('sku_parent')
                                ->distinct('sku_parent')
                                ->pluck('sku_parent')
                                ->take(10)
                                ->toArray();
                        }),

                    Forms\Components\TextInput::make('sku')
                        ->label('SKU (Individual Product)')
                        ->maxLength(255)
                        ->unique(Product::class, 'sku', ignoreRecord: true)
                        ->placeholder('Auto-generated if empty')
                        ->helperText('Unique identifier for this specific product variant'),
                ])->columns(2),

            // Product Images Section
            Forms\Components\Section::make('Product Images')
                ->description('Upload high-quality product images. First image becomes the featured image.')
                ->schema([
                    Forms\Components\FileUpload::make('images')
    ->label('Product Images')
    ->multiple()
    ->image()
    ->imageEditor()
    ->imageEditorAspectRatios([
        '1:1',
        '4:3', 
        '16:9',
    ])
    ->directory('products')
    ->visibility('public')
    ->maxFiles(10)
    ->reorderable()
    // 🔥 CRITICAL FIXES:
    ->preserveFilenames()
    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
    ->loadStateFromRelationshipsUsing(function ($component, $state, $record) {
        // 🎯 PRESERVE existing images saat edit
        if ($record && $record->exists) {
            $existingImages = $record->images ?? [];
            if (!empty($existingImages) && is_array($existingImages)) {
                $component->state($existingImages);
            }
        }
        return $state;
    })
    ->saveUploadedFileUsing(function ($component, $file, $record) {
        // 🎯 CUSTOM upload handling
        $filename = 'product_' . time() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('products', $filename, 'public');
        return $path;
    })
    ->imagePreviewHeight('250')
    ->uploadingMessage('Uploading images...')
    ->helperText('Upload up to 10 images. First image will be the main featured image. Existing images will be preserved when editing.')
    ->columnSpanFull(),
                ]),

            // Product Description Section
            Forms\Components\Section::make('Product Description')
                ->description('Detailed information about your product')
                ->schema([
                    Forms\Components\Textarea::make('short_description')
                        ->label('Short Description')
                        ->maxLength(500)
                        ->rows(3)
                        ->placeholder('Brief product summary...')
                        ->helperText('Brief description for product cards (max 500 characters)'),

                    Forms\Components\RichEditor::make('description')
                        ->label('Full Product Description')
                        ->required()
                        ->columnSpanFull()
                        ->placeholder('Detailed product information, features, materials, etc...')
                        ->helperText('Detailed product description with formatting.')
                        ->toolbarButtons([
                            'bold',
                            'italic',
                            'underline',
                            'strike',
                            'bulletList',
                            'orderedList',
                            'h2',
                            'h3',
                            'link',
                            'blockquote',
                        ]),
                ]),

            // ⭐ Pricing Section - From Excel Columns (price, sale_price)
            Forms\Components\Section::make('Pricing & Sales')
                ->description('Set product pricing. Sale price enables SALE menu display.')
                ->schema([
                    Forms\Components\TextInput::make('price')
                        ->label('Regular Price')
                        ->required()
                        ->numeric()
                        ->prefix('Rp')
                        ->step(1000)
                        ->placeholder('1000000')
                        ->helperText('Base price of the product'),

                    Forms\Components\TextInput::make('sale_price')
                        ->label('Sale Price (Optional)')
                        ->numeric()
                        ->prefix('Rp')
                        ->step(1000)
                        ->placeholder('800000')
                        ->helperText('Set this to show product in SALE menu and display discount badge'),
                ])->columns(2),

            // ⭐ Inventory Section - From Excel Columns (stock_quantity, weight)
            Forms\Components\Section::make('Inventory & Specifications')
                ->description('Stock levels and product specifications from Excel columns')
                ->schema([
                    Forms\Components\TextInput::make('stock_quantity')
                        ->label('Stock Quantity')
                        ->required()
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->helperText('Current stock level from Excel'),

                    Forms\Components\TextInput::make('weight')
                        ->label('Weight (kg)')
                        ->numeric()
                        ->step(0.1)
                        ->placeholder('0.5')
                        ->helperText('Product weight from Excel'),

                    // ⭐ Excel Column: length (mapped from 'lengh' - typo in sheets)
                    Forms\Components\TextInput::make('length')
                        ->label('Length (cm)')
                        ->numeric()
                        ->step(0.1)
                        ->placeholder('30.0')
                        ->helperText('Product length from Excel'),

                    // ⭐ Excel Column: width (mapped from 'wide')
                    Forms\Components\TextInput::make('width')
                        ->label('Width (cm)')
                        ->numeric()
                        ->step(0.1)
                        ->placeholder('20.0')
                        ->helperText('Product width from Excel'),

                    // ⭐ Excel Column: height (mapped from 'high')
                    Forms\Components\TextInput::make('height')
                        ->label('Height (cm)')
                        ->numeric()
                        ->step(0.1)
                        ->placeholder('15.0')
                        ->helperText('Product height from Excel'),
                ])->columns(3),

            // ⭐ Product Classification - From Excel Category Column
            Forms\Components\Section::make('Product Classification')
                ->description('Gender and product type parsed from Excel category column')
                ->schema([
                    Forms\Components\CheckboxList::make('gender_target')
                        ->label('Target Gender')
                        ->options([
                            'mens' => "👨 Men's",
                            'womens' => "👩 Women's", 
                            'kids' => '👶 Kids',
                            'unisex' => '🌐 Unisex',
                        ])
                        ->columns(4)
                        ->helperText('Parsed from Excel category column (e.g., "mens,Footwear,Lifestyle")')
                        ->required(),

                    Forms\Components\Select::make('product_type')
                        ->label('Product Type')
                        ->options([
                            // ⭐ UPDATED: Footwear Types
                            'running' => '🏃 Running',
                            'basketball' => '🏀 Basketball',
                            'tennis' => '🎾 Tennis',
                            'badminton' => '🏸 Badminton',
                            'lifestyle_casual' => '🚶 Lifestyle/Casual',
                            'sneakers' => '👟 Sneakers',
                            'training' => '💪 Training',
                            'formal' => '👔 Formal',
                            'sandals' => '🩴 Sandals',
                            'boots' => '🥾 Boots',
                            
                            // ⭐ UPDATED: Apparel
                            'apparel' => '👕 Apparel',
                            
                            // ⭐ UPDATED: Accessories
                            'caps' => '🧢 Caps & Hats',
                            'bags' => '👜 Bags',
                            'accessories' => '🎒 Accessories',
                        ])
                        ->placeholder('Select product type')
                        ->helperText('Parsed from Excel category column'),

                    // ⭐ Excel Column: available_sizes
                    Forms\Components\TagsInput::make('available_sizes')
                        ->label('Available Sizes')
                        ->placeholder('Add size')
                        ->helperText('Available sizes from Excel (e.g., 40, 41, 42, 43)')
                        ->columnSpanFull(),
                ])->columns(2),

            // ⭐ Sale Management - From Excel Columns
            Forms\Components\Section::make('Sale Management')
                ->description('Sale settings from Excel columns')
                ->schema([
                    // ⭐ Excel Column: sale_show
                    Forms\Components\Toggle::make('is_featured_sale')
                        ->label('Featured in Sale (sale_show from Excel)')
                        ->helperText('Show prominently in SALE menu (from Excel sale_show column)')
                        ->default(false),

                    // ⭐ Excel Column: sale_start_date
                    Forms\Components\DatePicker::make('sale_start_date')
                        ->label('Sale Start Date')
                        ->helperText('When the sale begins (from Excel)'),

                    // ⭐ Excel Column: sale_end_date
                    Forms\Components\DatePicker::make('sale_end_date')
                        ->label('Sale End Date')  
                        ->helperText('When the sale ends (from Excel)'),
                ])->columns(3),

            // Status & Visibility Section
            Forms\Components\Section::make('Product Status & Visibility')
                ->description('Control how this product appears on your website')
                ->schema([
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active Product')
                        ->default(true)
                        ->helperText('When enabled, product is visible on website'),

                    Forms\Components\Toggle::make('is_featured')
                        ->label('Featured Product')
                        ->default(false)
                        ->helperText('Featured products appear in "Featured Products" section'),

                    Forms\Components\DateTimePicker::make('published_at')
                        ->label('Publish Date')
                        ->default(now())
                        ->helperText('When this product becomes available'),
                ])->columns(3),
        ];

        return $form->schema($schema);
    }

    public static function table(Table $table): Table
    {
        $columns = [
            // Image Column
            Tables\Columns\ImageColumn::make('featured_image')
                ->label('Image')
                ->getStateUsing(function (Product $record): ?string {
                    return $record->featured_image;
                })
                ->size(60)
                ->circular(),

            // Basic Product Info
            Tables\Columns\TextColumn::make('id')
                ->label('ID')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('name')
                ->label('Product Name')
                ->searchable()
                ->sortable()
                ->wrap()
                ->limit(30)
                ->tooltip(function (Product $record): ?string {
                    return $record->name;
                }),

            Tables\Columns\TextColumn::make('brand')
                ->label('Brand')
                ->searchable()
                ->sortable()
                ->badge()
                ->color('info')
                ->formatStateUsing(fn ($state) => $state ?? 'No Brand'),

            // ⭐ EXCEL COLUMN: product_type (from category parsing) - UPDATED OPTIONS
            Tables\Columns\TextColumn::make('product_type')
                ->label('Product Type')
                ->badge()
                ->formatStateUsing(function ($state) {
                    return match($state) {
                        // ⭐ UPDATED: Footwear
                        'running' => '🏃 Running',
                        'basketball' => '🏀 Basketball',
                        'tennis' => '🎾 Tennis',
                        'badminton' => '🏸 Badminton',
                        'lifestyle_casual' => '🚶 Lifestyle',
                        'sneakers' => '👟 Sneakers',
                        'training' => '💪 Training',
                        'formal' => '👔 Formal',
                        'sandals' => '🩴 Sandals',
                        'boots' => '🥾 Boots',
                        
                        // ⭐ UPDATED: Apparel & Accessories
                        'apparel' => '👕 Apparel',
                        'caps' => '🧢 Caps',
                        'bags' => '👜 Bags',
                        'accessories' => '🎒 Accessories',
                        
                        default => $state ?? 'Unknown'
                    };
                })
                ->color(fn ($state) => match($state) {
                    'running' => 'success',
                    'basketball' => 'warning',
                    'tennis' => 'info',
                    'badminton' => 'primary',
                    'training' => 'danger',
                    'lifestyle_casual' => 'info',
                    'sneakers' => 'primary',
                    'apparel' => 'gray',
                    'caps' => 'purple',
                    'bags' => 'orange',
                    'accessories' => 'pink',
                    default => 'secondary'
                })
                ->sortable()
                ->searchable(),

            // ⭐ EXCEL COLUMN: sku_parent
            Tables\Columns\TextColumn::make('sku_parent')
                ->label('SKU Parent')
                ->searchable()
                ->sortable()
                ->copyable()
                ->badge()
                ->color('warning')
                ->formatStateUsing(fn ($state) => $state ?? 'No SKU Parent')
                ->tooltip(function (Product $record): ?string {
                    return "SKU Parent: " . ($record->sku_parent ?? 'Not set');
                })
                ->limit(20)
                ->toggleable(),

            // ⭐ EXCEL COLUMN: sku (individual)
            Tables\Columns\TextColumn::make('sku')
                ->label('SKU')
                ->searchable()
                ->sortable()
                ->copyable()
                ->badge()
                ->color('primary')
                ->formatStateUsing(fn ($state) => $state ?? 'No SKU')
                ->tooltip(function (Product $record): ?string {
                    return "Individual SKU: " . ($record->sku ?? 'Not set');
                })
                ->limit(20)
                ->toggleable(),

            // ⭐ EXCEL COLUMN: gender_target (from category parsing)
            Tables\Columns\TextColumn::make('gender_target')
                ->label('Gender')
                ->badge()
                ->formatStateUsing(function ($state) {
                    if (!$state) return 'No Gender';
                    
                    if (is_array($state)) {
                        $genders = [];
                        foreach ($state as $gender) {
                            $genders[] = match($gender) {
                                'mens' => '👨 Men\'s',
                                'womens' => '👩 Women\'s',
                                'kids' => '👶 Kids',
                                'unisex' => '🌐 Unisex',
                                default => $gender
                            };
                        }
                        return implode(', ', $genders);
                    }
                    
                    return match($state) {
                        'mens' => '👨 Men\'s',
                        'womens' => '👩 Women\'s',
                        'kids' => '👶 Kids',
                        'unisex' => '🌐 Unisex',
                        default => $state
                    };
                })
                ->color(fn ($state) => is_array($state) && count($state) > 1 ? 'warning' : 'info')
                ->searchable(),

            // ⭐ EXCEL COLUMN: available_sizes
            Tables\Columns\TextColumn::make('available_sizes')
                ->label('Sizes')
                ->badge()
                ->formatStateUsing(function ($state) {
                    if (!$state) return 'No Sizes';
                    
                    if (is_array($state)) {
                        $count = count($state);
                        if ($count <= 3) {
                            return implode(', ', $state);
                        }
                        return $count . ' sizes: ' . implode(', ', array_slice($state, 0, 2)) . '...';
                    }
                    
                    return $state;
                })
                ->color('secondary')
                ->tooltip(function (Product $record): ?string {
                    if (is_array($record->available_sizes)) {
                        return 'Available sizes: ' . implode(', ', $record->available_sizes);
                    }
                    return 'No sizes available';
                }),

            // ⭐ EXCEL COLUMN: price
            Tables\Columns\TextColumn::make('price')
                ->label('Price')
                ->money('IDR')
                ->sortable()
                ->alignEnd(),

            // ⭐ EXCEL COLUMN: sale_price
            Tables\Columns\TextColumn::make('sale_price')
                ->label('Sale Price')
                ->money('IDR')
                ->sortable()
                ->alignEnd()
                ->color('danger')
                ->weight('bold')
                ->formatStateUsing(function ($state, Product $record) {
                    if (!$state) return '-';
                    $discount = round((($record->price - $state) / $record->price) * 100);
                    return "Rp " . number_format($state, 0, ',', '.') . " (-{$discount}%)";
                }),

            // ⭐ EXCEL COLUMN: stock_quantity
            Tables\Columns\TextColumn::make('stock_quantity')
                ->label('Stock')
                ->sortable()
                ->alignEnd()
                ->color(fn ($state) => match(true) {
                    $state == 0 => 'danger',
                    $state < 10 => 'warning', 
                    default => 'success'
                })
                ->formatStateUsing(fn ($state) => $state == 0 ? 'Out of Stock' : $state),

            // ⭐ EXCEL COLUMN: weight
            Tables\Columns\TextColumn::make('weight')
                ->label('Weight (kg)')
                ->sortable()
                ->alignEnd()
                ->formatStateUsing(fn ($state) => $state ? $state . ' kg' : '-')
                ->toggleable(isToggledHiddenByDefault: true),

            // ⭐ EXCEL COLUMNS: Dimensions (length, width, height)
            Tables\Columns\TextColumn::make('dimensions_display')
                ->label('Dimensions (L×W×H)')
                ->getStateUsing(function (Product $record): string {
                    $length = $record->length ?? '-';
                    $width = $record->width ?? '-';
                    $height = $record->height ?? '-';
                    return "{$length}×{$width}×{$height} cm";
                })
                ->toggleable(isToggledHiddenByDefault: true),

            // ⭐ EXCEL COLUMN: sale_show (mapped to is_featured_sale)
            Tables\Columns\IconColumn::make('is_featured_sale')
                ->label('Featured Sale')
                ->boolean()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            // ⭐ EXCEL COLUMNS: Sale Dates
            Tables\Columns\TextColumn::make('sale_start_date')
                ->label('Sale Start')
                ->date('d M Y')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('sale_end_date')
                ->label('Sale End')
                ->date('d M Y')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            // Status Columns
            Tables\Columns\IconColumn::make('is_active')
                ->label('Active')
                ->boolean()
                ->sortable(),

            Tables\Columns\IconColumn::make('is_featured')
                ->label('Featured')
                ->boolean()
                ->sortable(),

            // Timestamps
            Tables\Columns\TextColumn::make('created_at')
                ->label('Created')
                ->dateTime('d M Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            Tables\Columns\TextColumn::make('updated_at')
                ->label('Last Updated')
                ->dateTime('d M Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];

        return $table
            ->columns($columns)
            ->filters([
                SelectFilter::make('brand')
                    ->options(function () {
                        return Product::query()
                            ->whereNotNull('brand')
                            ->distinct()
                            ->pluck('brand', 'brand')
                            ->toArray();
                    })
                    ->multiple(),

                // ⭐ MAIN FILTER: Product Type (UPDATED OPTIONS)
                SelectFilter::make('product_type')
                    ->label('Product Type (from Excel)')
                    ->options([
                        // ⭐ UPDATED: Footwear
                        'running' => '🏃 Running',
                        'basketball' => '🏀 Basketball', 
                        'tennis' => '🎾 Tennis',
                        'badminton' => '🏸 Badminton',
                        'lifestyle_casual' => '🚶 Lifestyle/Casual',
                        'sneakers' => '👟 Sneakers',
                        'training' => '💪 Training',
                        'formal' => '👔 Formal',
                        'sandals' => '🩴 Sandals',
                        'boots' => '🥾 Boots',
                        
                        // ⭐ UPDATED: Apparel & Accessories
                        'apparel' => '👕 Apparel',
                        'caps' => '🧢 Caps & Hats',
                        'bags' => '👜 Bags', 
                        'accessories' => '🎒 Accessories',
                    ])
                    ->multiple(),

                // ⭐ FILTER: Gender Target (from Excel category)
                SelectFilter::make('gender_target')
                    ->label('Gender Target')
                    ->options([
                        'mens' => '👨 Men\'s',
                        'womens' => '👩 Women\'s', 
                        'kids' => '👶 Kids',
                        'unisex' => '🌐 Unisex',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!empty($data['value'])) {
                            return $query->whereJsonContains('gender_target', $data['value']);
                        }
                        return $query;
                    }),

                // ⭐ FILTER: SKU Parent
                Filter::make('has_sku_parent')
                    ->label('Has SKU Parent')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('sku_parent')),

                Filter::make('missing_sku_parent')
                    ->label('Missing SKU Parent')
                    ->query(fn (Builder $query): Builder => $query->whereNull('sku_parent')),

                // ⭐ FILTER: Sale Status
                Filter::make('on_sale')
                    ->label('On Sale')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('sale_price')),

                Filter::make('featured_sale')
                    ->label('Featured Sale')
                    ->query(fn (Builder $query): Builder => $query->where('is_featured_sale', true)),

                // Stock Filters
                Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(fn (Builder $query): Builder => $query->where('stock_quantity', 0)),

                Filter::make('low_stock')
                    ->label('Low Stock (< 10)')
                    ->query(fn (Builder $query): Builder => $query->where('stock_quantity', '<', 10)->where('stock_quantity', '>', 0)),

                Filter::make('is_featured')
                    ->label('Featured Products')
                    ->query(fn (Builder $query): Builder => $query->where('is_featured', true)),

                // ⭐ FILTER: Same SKU Parent Products
                Filter::make('duplicate_sku_parents')
                    ->label('Duplicate SKU Parents')
                    ->query(function (Builder $query): Builder {
                        return $query->whereIn('sku_parent', function ($subQuery) {
                            $subQuery->select('sku_parent')
                                ->from('products')
                                ->whereNotNull('sku_parent')
                                ->groupBy('sku_parent')
                                ->havingRaw('COUNT(*) > 1');
                        });
                    }),
            ])
->headerActions([
    
    // 📊 GOOGLE SHEETS SYNC GROUP
    Tables\Actions\ActionGroup::make([
        
        // 🧠 SMART SYNC - Primary sync method
        Tables\Actions\Action::make('smart_sync_google_sheets')
            ->label('🧠 Smart Sync')
            ->icon('heroicon-o-cloud-arrow-down')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Smart Sync Products from Google Sheets')
            ->modalDescription('This will intelligently sync products: update existing, create new, and DELETE products not in spreadsheet anymore.')
            ->modalSubmitActionLabel('Start Smart Sync')
            ->form([
                Forms\Components\Placeholder::make('warning')
                    ->label('⚠️ Important Warning')
                    ->content(function () {
                        $currentCount = Product::count();
                        return "Current products in database: {$currentCount}. This smart sync will DELETE products that are no longer in the Google Sheets.";
                    }),

                Forms\Components\Placeholder::make('spreadsheet_info')
                    ->label('📊 Spreadsheet Preview')
                    ->content(function () {
                        try {
                            $service = new GoogleSheetsSync();
                            $preview = $service->previewData(3);
                            
                            if ($preview['success']) {
                                $skus = array_unique(array_column($preview['data'], 'sku'));
                                $existingSkus = Product::pluck('sku')->toArray();
                                $toDelete = array_diff($existingSkus, $skus);
                                $toCreate = array_diff($skus, $existingSkus);
                                $toUpdate = array_intersect($existingSkus, $skus);
                                
                                return "
                                    <div style='background: #f0f8ff; padding: 15px; border-radius: 8px;'>
                                        <h4>📈 Sync Preview</h4>
                                        <p><strong>➕ To CREATE:</strong> " . count($toCreate) . "</p>
                                        <p><strong>🔄 To UPDATE:</strong> " . count($toUpdate) . "</p>
                                        <p><strong>🗑️ To DELETE:</strong> " . count($toDelete) . "</p>
                                    </div>
                                ";
                            }
                            return "Failed to preview spreadsheet";
                        } catch (Exception $e) {
                            return "Error: {$e->getMessage()}";
                        }
                    }),

                Forms\Components\Toggle::make('confirm_delete')
                    ->label('✅ I understand products will be deleted')
                    ->required(),
            ])
            ->action(function (array $data) {
                if (!($data['confirm_delete'] ?? false)) {
                    Notification::make()
                        ->title('❌ Confirmation Required')
                        ->body('You must confirm that you understand products will be deleted.')
                        ->danger()
                        ->send();
                    return;
                }

                try {
                    $syncService = new GoogleSheetsSync();
                    $result = $syncService->syncProducts([
                        'sync_strategy' => 'smart_individual_sku',
                    ]);

                    if ($result['success']) {
                        $stats = $result['stats'];
                        Notification::make()
                            ->title('🎉 Smart Sync Successful!')
                            ->body("✅ Created: {$stats['created']}, 🔄 Updated: {$stats['updated']}, 🗑️ Deleted: {$stats['deleted']}")
                            ->success()
                            ->duration(15000)
                            ->send();
                    } else {
                        throw new Exception($result['message']);
                    }
                } catch (Exception $e) {
                    Notification::make()
                        ->title('❌ Smart Sync Failed')
                        ->body('Error: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            }),

        // 🔄 SAFE MODE SYNC - No delete
        Tables\Actions\Action::make('safe_sync_google_sheets')
            ->label('🛡️ Safe Sync (No Delete)')
            ->icon('heroicon-o-shield-check')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Safe Sync - No Products Will Be Deleted')
            ->modalDescription('This will only create new and update existing products. No products will be deleted.')
            ->form([
                Forms\Components\Placeholder::make('info')
                    ->label('ℹ️ Safe Sync Information')
                    ->content('This sync method is safe - it will not delete any existing products. It will only create new products and update existing ones based on SKU matching.'),
            ])
            ->action(function () {
                try {
                    $syncService = new GoogleSheetsSync();
                    $result = $syncService->syncProductsSafeMode(['sync_strategy' => 'safe_mode_no_delete']);

                    if ($result['success']) {
                        $stats = $result['stats'];
                        Notification::make()
                            ->title('✅ Safe Sync Successful!')
                            ->body("Created: {$stats['created']}, Updated: {$stats['updated']} products. No products deleted.")
                            ->success()
                            ->send();
                    } else {
                        throw new Exception($result['message']);
                    }
                } catch (Exception $e) {
                    Notification::make()
                        ->title('❌ Safe Sync Failed')
                        ->body('Error: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            }),

        // 👁️ PREVIEW DATA
        Tables\Actions\Action::make('preview_data')
            ->label('👁️ Preview Data')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->action(function () {
                try {
                    $syncService = new GoogleSheetsSync();
                    $result = $syncService->previewData(5);
                    
                    if ($result['success']) {
                        $message = "Found {$result['total_rows']} total rows. Preview:\n\n";
                        foreach ($result['data'] as $index => $row) {
                            $message .= ($index + 1) . ". {$row['name']} ({$row['brand']})\n";
                            $message .= "   SKU: {$row['sku']} | Price: Rp " . number_format($row['price']) . "\n\n";
                        }
                        
                        Notification::make()
                            ->title('📊 Data Preview')
                            ->body($message)
                            ->info()
                            ->duration(20000)
                            ->send();
                    } else {
                        throw new Exception($result['message']);
                    }
                } catch (Exception $e) {
                    Notification::make()
                        ->title('❌ Preview Failed')
                        ->body('Error: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            }),

    ])->label('📊 Google Sheets')
      ->icon('heroicon-o-table-cells')
      ->color('success')
      ->button(),
    
    // 🔄 GINEE SYNC GROUP
Tables\Actions\ActionGroup::make([
    
    // 🎯 INDIVIDUAL STOCK UPDATE - Enhanced Version
    Tables\Actions\Action::make('individual_stock_update')
        ->label('🎯 Update Stock (Individual)')
        ->icon('heroicon-o-pencil-square')
        ->color('warning')
        ->requiresConfirmation()
        ->modalHeading('Individual Product Stock Sync')
        ->modalDescription('Select a product and choose sync direction for individual processing.')
        ->form([
            Forms\Components\Section::make('Product Selection')
                ->schema([
                    Forms\Components\Select::make('selected_product_id')
                        ->label('Select Product')
                        ->placeholder('Choose a product to sync...')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->options(function () {
                            return \App\Models\Product::whereNotNull('sku')
                                ->where('sku', '!=', '')
                                ->with('gineeMappings')
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(function ($product) {
                                    $mapping = $product->gineeMappings->first();
                                    $gineeInfo = $mapping ? " | MSKU: {$mapping->ginee_master_sku}" : ' | ⚠️ No mapping';
                                    return [$product->id => "{$product->name} (SKU: {$product->sku}) - Stock: {$product->stock_quantity}{$gineeInfo}"];
                                });
                        })
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                $product = \App\Models\Product::with('gineeMappings')->find($state);
                                if ($product) {
                                    $set('current_stock', $product->stock_quantity ?? 0);
                                    $set('current_sku', $product->sku);
                                    $set('current_name', $product->name);
                                    $mapping = $product->gineeMappings->first();
                                    $set('has_mapping', $mapping ? true : false);
                                    $set('ginee_msku', $mapping?->ginee_master_sku ?? '');
                                    $set('last_sync', $product->ginee_last_sync?->diffForHumans() ?? 'Never');
                                }
                            }
                        }),
                ]),
                
            Forms\Components\Section::make('Current Product Information')
                ->schema([
                    Forms\Components\Placeholder::make('product_info')
                        ->label('Product Details')
                        ->content(function (callable $get) {
                            $productId = $get('selected_product_id');
                            if (!$productId) {
                                return 'Please select a product first.';
                            }
                            
                            $product = \App\Models\Product::with('gineeMappings')->find($productId);
                            if (!$product) {
                                return 'Product not found.';
                            }
                            
                            $mapping = $product->gineeMappings->first();
                            $syncStatus = $mapping ? '✅ Mapped' : '❌ Not mapped';
                            $gineeStock = $mapping?->stock_quantity_ginee ?? 'N/A';
                            
                            return "📦 **{$product->name}**\n" .
                                   "🏷️ SKU: `{$product->sku}`\n" .
                                   "📊 Local Stock: **{$product->stock_quantity}**\n" .
                                   "🎯 Ginee MSKU: " . ($mapping?->ginee_master_sku ?? 'Not mapped') . "\n" .
                                   "📈 Ginee Stock: **{$gineeStock}**\n" .
                                   "🔄 Mapping Status: {$syncStatus}\n" .
                                   "⏰ Last Sync: " . ($product->ginee_last_sync ? $product->ginee_last_sync->format('d/m/Y H:i') : 'Never');
                        }),
                        
                    Forms\Components\Hidden::make('current_stock'),
                    Forms\Components\Hidden::make('current_sku'),
                    Forms\Components\Hidden::make('current_name'),
                    Forms\Components\Hidden::make('has_mapping'),
                    Forms\Components\Hidden::make('ginee_msku'),
                    Forms\Components\Hidden::make('last_sync'),
                ])
                ->visible(fn (callable $get) => $get('selected_product_id')),
                
            Forms\Components\Section::make('Sync Options')
                ->schema([
                    Forms\Components\Select::make('sync_direction')
                        ->label('Sync Direction')
                        ->options([
                            'from_ginee' => '📥 FROM Ginee → Local (Get current stock from Ginee)',
                            'to_ginee' => '📤 TO Ginee ← Local (Push local stock to Ginee)',
                            'bidirectional' => '🔄 Bidirectional (Sync both ways)',
                            'manual_update' => '✏️ Manual Update (Set specific stock amount)',
                        ])
                        ->default('from_ginee')
                        ->required()
                        ->reactive()
                        ->helperText('Choose sync direction based on your needs'),
                        
                    Forms\Components\TextInput::make('manual_stock_amount')
                        ->label('Set Stock Amount')
                        ->numeric()
                        ->minValue(0)
                        ->placeholder('Enter stock quantity to set...')
                        ->helperText('This will update local stock and push to Ginee')
                        ->visible(fn (callable $get) => $get('sync_direction') === 'manual_update'),
                        
                    Forms\Components\Toggle::make('dry_run')
                        ->label('🧪 Dry Run (Preview Only)')
                        ->default(true)
                        ->helperText('Enable to preview changes without making actual updates')
                        ->reactive(),
                        
                    Forms\Components\Toggle::make('force_update')
                        ->label('🔄 Force Update')
                        ->default(false)
                        ->helperText('Force update even if stock appears to be already in sync')
                        ->visible(fn (callable $get) => in_array($get('sync_direction'), ['to_ginee', 'bidirectional'])),
                ])
                ->visible(fn (callable $get) => $get('selected_product_id') && $get('has_mapping')),
                
            Forms\Components\Section::make('⚠️ Setup Required')
                ->schema([
                    Forms\Components\Placeholder::make('no_mapping_warning')
                        ->content('This product is not mapped to Ginee. Please set up Ginee mapping first before syncing.')
                        ->columnSpanFull(),
                        
                    Forms\Components\TextInput::make('setup_ginee_msku')
                        ->label('Ginee Master SKU')
                        ->placeholder('Enter Ginee Master SKU...')
                        ->helperText('Get this from Ginee OMS'),
                        
                    Forms\Components\Toggle::make('create_mapping')
                        ->label('Create Ginee Mapping')
                        ->default(false)
                        ->helperText('Create mapping and then perform sync'),
                ])
                ->visible(fn (callable $get) => $get('selected_product_id') && !$get('has_mapping'))
                ->collapsed(false),
        ])
        ->action(function (array $data) {
            try {
                $productId = $data['selected_product_id'];
                $syncDirection = $data['sync_direction'];
                $dryRun = $data['dry_run'] ?? true;
                $forceUpdate = $data['force_update'] ?? false;
                $manualStock = $data['manual_stock_amount'] ?? null;
                
                $product = \App\Models\Product::with('gineeMappings')->findOrFail($productId);
                $mapping = $product->gineeMappings->first();
                
                // Handle mapping creation if needed
                if (!$mapping && ($data['create_mapping'] ?? false) && !empty($data['setup_ginee_msku'])) {
                    $mapping = $product->gineeMappings()->create([
                        'ginee_master_sku' => $data['setup_ginee_msku'],
                        'sync_enabled' => true,
                        'stock_sync_enabled' => true,
                    ]);
                }
                
                if (!$mapping) {
                    throw new \Exception('Product is not mapped to Ginee. Please set up mapping first.');
                }
                
                $syncService = new \App\Services\GineeStockSyncService();
                $results = [];
                
                // Handle manual stock update
                if ($syncDirection === 'manual_update' && $manualStock !== null) {
                    $oldStock = $product->stock_quantity;
                    if (!$dryRun) {
                        $product->update(['stock_quantity' => $manualStock]);
                    }
                    $results[] = ($dryRun ? '🧪 Would update' : '✅ Updated') . " local stock: {$oldStock} → {$manualStock}";
                    
                    // Also push to Ginee
                    $pushResult = $syncService->pushSingleSkuToGinee($product->sku, $dryRun, true);
                    $results[] = ($pushResult['success'] ? '✅' : '❌') . " Push to Ginee: " . $pushResult['message'];
                    
                } else {
                    // Handle other sync directions
                    switch ($syncDirection) {
                        case 'from_ginee':
                            $result = $syncService->syncSingleSku($product->sku, $dryRun);
                            $results[] = ($result['success'] ? '✅' : '❌') . " FROM Ginee: " . $result['message'];
                            break;
                            
                        case 'to_ginee':
                            $result = $syncService->pushSingleSkuToGinee($product->sku, $dryRun, $forceUpdate);
                            $results[] = ($result['success'] ? '✅' : '❌') . " TO Ginee: " . $result['message'];
                            break;
                            
                        case 'bidirectional':
                            $syncResult = $syncService->syncSingleSku($product->sku, $dryRun);
                            $results[] = ($syncResult['success'] ? '✅' : '❌') . " FROM Ginee: " . $syncResult['message'];
                            
                            $pushResult = $syncService->pushSingleSkuToGinee($product->sku, $dryRun, $forceUpdate);
                            $results[] = ($pushResult['success'] ? '✅' : '❌') . " TO Ginee: " . $pushResult['message'];
                            break;
                    }
                }
                
                $message = ($dryRun ? '🧪 PREVIEW RESULTS\n\n' : '✅ SYNC COMPLETED\n\n') . implode("\n", $results);
                
                Notification::make()
                    ->title($dryRun ? '🧪 Individual Sync Preview' : '✅ Individual Sync Completed')
                    ->body($message)
                    ->success()
                    ->duration(15000)
                    ->send();
                    
            } catch (\Exception $e) {
                Notification::make()
                    ->title('❌ Individual Sync Failed')
                    ->body('Error: ' . $e->getMessage())
                    ->danger()
                    ->send();
            }
        }),

    // 📥 SYNC STOCK FROM GINEE - Enhanced
    Tables\Actions\Action::make('sync_stock_from_ginee')
    ->label('📥 Sync from Ginee')
    ->icon('heroicon-o-arrow-down-circle')
    ->color('info')
    ->requiresConfirmation()
    ->modalHeading('Sync Stock from Ginee to Local Database')
    ->modalDescription('This will update local stock quantities with current stock from Ginee. All operations will be logged.')
    ->form([
        Forms\Components\Section::make('Sync Information')
            ->schema([
                Forms\Components\Placeholder::make('sync_info')
                    ->content('This will fetch current stock from Ginee Warehouse Inventory API (READ-ONLY) and update your local database. All products will be processed regardless of mappings.'),
            ]),
            
        Forms\Components\Section::make('Options')
            ->schema([
                Forms\Components\Toggle::make('dry_run')
                    ->label('🧪 Dry Run (Preview Only)')
                    ->default(true)
                    ->helperText('Enable to preview changes without updating database'),
                    
                // ✅ HILANGKAN OPTION "Only Mapped Products" 
                // Forms\Components\Toggle::make('only_mapped')
                //     ->label('📋 Only Mapped Products')
                //     ->default(true)
                //     ->helperText('Sync only products that have Ginee mappings'),
                    
                Forms\Components\TextInput::make('batch_size')
                    ->label('Batch Size')
                    ->numeric()
                    ->default(100)
                    ->minValue(1)
                    ->maxValue(2000)
                    ->helperText('Number of products to process per batch (Max: 2000)'),
            ]),
    ])
    ->action(function (array $data) {
        try {
            $dryRun = $data['dry_run'] ?? true;
            // $onlyMapped = $data['only_mapped'] ?? true; // ✅ HILANGKAN INI
            $batchSize = $data['batch_size'] ?? 100;
            
            $syncService = new \App\Services\GineeStockSyncService();
            
            $result = $syncService->syncStockFromGinee([
                'dry_run' => $dryRun,
                // 'only_active' => $onlyMapped, // ✅ HILANGKAN INI
                'batch_size' => $batchSize
            ]);
            
            if ($result['success']) {
                $data_result = $result['data'];
                
                $message = ($dryRun ? '🧪 DRY RUN - ' : '') . "Sync completed!\n\n";
                $message .= "✅ Successful: {$data_result['successful']}\n";
                $message .= "❌ Failed: {$data_result['failed']}\n";
                $message .= "🔍 Not Found: {$data_result['not_found']}\n";
                $message .= "🔗 No Mapping: {$data_result['no_mapping']}\n";
                $message .= "📊 Total Processed: {$data_result['total_requested']}";
                
                if ($data_result['failed'] > 0) {
                    $message .= "\n\nFirst few errors:\n" . 
                               implode("\n", array_slice($data_result['errors'], 0, 3));
                }
                
                $message .= "\n\n🔍 Session ID: {$data_result['session_id']}";
                
                Notification::make()
                    ->title($dryRun ? '🧪 Sync Preview Completed' : '✅ Sync FROM Ginee Completed')
                    ->body($message)
                    ->success()
                    ->duration(25000)
                    ->send();
            } else {
                throw new \Exception($result['message'] ?? 'Sync failed');
            }
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('❌ Sync FROM Ginee Failed')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }),
        
    // 📤 PUSH STOCK TO GINEE - Enhanced
    Tables\Actions\Action::make('push_stock_to_ginee')
        ->label('📤 Push to Ginee')
        ->icon('heroicon-o-arrow-up-circle')
        ->color('warning')
        ->requiresConfirmation()
        ->modalHeading('Push Local Stock to Ginee')
        ->modalDescription('This will update Ginee stock with your local stock quantities. All operations will be logged.')
        ->form([
            Forms\Components\Section::make('Push Information')
                ->schema([
                    Forms\Components\Placeholder::make('push_info')
                        ->content('This will send your local stock quantities to Ginee. Only products with Ginee mappings and enabled sync will be processed.'),
                ]),
                
            Forms\Components\Section::make('Options')
                ->schema([
                    Forms\Components\Toggle::make('dry_run')
                        ->label('🧪 Dry Run (Preview Only)')
                        ->default(true)
                        ->helperText('Enable to preview changes without updating Ginee'),
                        
                    Forms\Components\Toggle::make('force_update')
                        ->label('🔄 Force Update All Products')
                        ->default(false)
                        ->helperText('Push all products regardless of last sync time'),
                        
                    Forms\Components\TextInput::make('batch_size')
                        ->label('Batch Size')
                        ->numeric()
                        ->default(10)
                        ->minValue(1)
                        ->maxValue(50)
                        ->helperText('Number of products to process per batch'),
                ]),
        ])
        ->action(function (array $data) {
            try {
                $dryRun = $data['dry_run'] ?? true;
                $forceUpdate = $data['force_update'] ?? false;
                $batchSize = $data['batch_size'] ?? 10;
                
                // Get products to push
                $query = \App\Models\Product::whereHas('gineeMappings', function($q) {
                    $q->where('sync_enabled', true)->where('stock_sync_enabled', true);
                });
                
                if (!$forceUpdate) {
                    $query->where('updated_at', '>', now()->subHours(24));
                }
                
                $products = $query->limit($batchSize)->get();
                
                if ($products->isEmpty()) {
                    Notification::make()
                        ->title('ℹ️ No Products to Push')
                        ->body('No products found that need stock updates. Try enabling "Force Update" option.')
                        ->info()
                        ->send();
                    return;
                }
                
                $syncService = new \App\Services\GineeStockSyncService();
                $skus = $products->pluck('sku')->toArray();
                
                $result = $syncService->pushMultipleSkusIndividually($skus, [
                    'dry_run' => $dryRun,
                    'batch_size' => min($batchSize, 20),
                    'force_update' => $forceUpdate
                ]);
                
                if ($result['success']) {
                    $data_result = $result['data'];
                    
                    $message = ($dryRun ? '🧪 DRY RUN - ' : '') . "Push completed!\n\n";
                    $message .= "✅ Successful: {$data_result['successful']}\n";
                    $message .= "❌ Failed: {$data_result['failed']}\n";
                    $message .= "⏭️ Skipped: {$data_result['skipped']}\n";
                    $message .= "📊 Total Requested: {$data_result['total_requested']}";
                    
                    Notification::make()
                        ->title($dryRun ? '🧪 Push Preview Completed' : '✅ Push Completed')
                        ->body($message)
                        ->success()
                        ->duration(15000)
                        ->send();
                } else {
                    throw new \Exception('Push operation failed');
                }

            } catch (\Exception $e) {
                Notification::make()
                    ->title('❌ Stock Push Error')
                    ->body('Error: ' . $e->getMessage())
                    ->danger()
                    ->send();
            }
        }),
        
    // 🔄 BIDIRECTIONAL SYNC
    Tables\Actions\Action::make('bidirectional_sync')
        ->label('🔄 Bidirectional Sync')
        ->icon('heroicon-o-arrow-path')
        ->color('success')
        ->requiresConfirmation()
        ->modalHeading('Bidirectional Stock Synchronization')
        ->modalDescription('This will first sync FROM Ginee, then push any local changes TO Ginee.')
        ->form([
            Forms\Components\Section::make('Sync Information')
                ->schema([
                    Forms\Components\Placeholder::make('bidirectional_info')
                        ->content('This performs a two-phase sync:
                        
1. 📥 First, sync latest stock FROM Ginee to local database
2. ⏳ Wait 2 seconds for API rate limiting  
3. 📤 Then, push any local changes TO Ginee

This ensures both systems are fully synchronized.'),
                ]),
                
            Forms\Components\Section::make('Options')
                ->schema([
                    Forms\Components\Toggle::make('dry_run')
                        ->label('🧪 Dry Run (Preview Only)')
                        ->default(true)
                        ->helperText('Enable to preview changes without making actual updates'),
                        
                    Forms\Components\TextInput::make('batch_size')
                        ->label('Batch Size')
                        ->numeric()
                        ->default(15)
                        ->minValue(1)
                        ->maxValue(30)
                        ->helperText('Smaller batches recommended for bidirectional sync'),
                ]),
        ])
        ->action(function (array $data) {
            try {
                $dryRun = $data['dry_run'] ?? true;
                $batchSize = $data['batch_size'] ?? 15;
                
                // Get mapped products
                $products = \App\Models\Product::whereHas('gineeMappings', function($q) {
                    $q->where('sync_enabled', true)->where('stock_sync_enabled', true);
                })->limit($batchSize)->get();
                
                if ($products->isEmpty()) {
                    Notification::make()
                        ->title('ℹ️ No Products Found')
                        ->body('No products with enabled Ginee mappings found for sync.')
                        ->info()
                        ->send();
                    return;
                }
                
                $syncService = new \App\Services\GineeStockSyncService();
                $skus = $products->pluck('sku')->toArray();
                
                $result = $syncService->bidirectionalSyncMultipleSkus($skus, [
                    'dry_run' => $dryRun,
                    'batch_size' => $batchSize
                ]);
                
                if ($result['success']) {
                    $data_result = $result['data'];
                    $summary = $data_result['summary'];
                    
                    $message = ($dryRun ? '🧪 PREVIEW - ' : '') . "Bidirectional sync completed!\n\n";
                    $message .= "📥 FROM Ginee:\n";
                    $message .= "  ✅ Successful: {$summary['sync_successful']}\n";
                    $message .= "  ❌ Failed: {$summary['sync_failed']}\n\n";
                    $message .= "📤 TO Ginee:\n";
                    $message .= "  ✅ Successful: {$summary['push_successful']}\n";
                    $message .= "  ❌ Failed: {$summary['push_failed']}\n";
                    $message .= "  ⏭️ Skipped: {$summary['push_skipped']}";
                    
                    Notification::make()
                        ->title($dryRun ? '🧪 Bidirectional Preview' : '✅ Bidirectional Sync Completed')
                        ->body($message)
                        ->success()
                        ->duration(20000)
                        ->send();
                } else {
                    throw new \Exception('Bidirectional sync failed');
                }

            } catch (\Exception $e) {
                Notification::make()
                    ->title('❌ Bidirectional Sync Error')
                    ->body('Error: ' . $e->getMessage())
                    ->danger()
                    ->send();
            }
        }),
        
    // 📋 VIEW GINEE LOGS
    Tables\Actions\Action::make('view_ginee_logs')
        ->label('📋 View Sync Logs')
        ->icon('heroicon-o-document-text')
        ->color('gray')
        ->url('/admin/ginee-sync-logs')
        ->openUrlInNewTab(),
        
])->label('🔄 Ginee Sync')
  ->icon('heroicon-o-arrow-path-rounded-square')
  ->color('warning')
  ->button(),
    
    // 🚀 WORKFLOWS GROUP
    Tables\Actions\ActionGroup::make([
        
        // 🚀 COMPLETE WORKFLOW
        Tables\Actions\Action::make('complete_safe_workflow')
            ->label('🚀 Complete Workflow')
            ->icon('heroicon-o-cog-6-tooth')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Safe Complete Sync: Products + Stock')
            ->modalDescription('This will run: 1) Safe sync products from Google Sheets, 2) Safe sync stock from Ginee (READ-ONLY).')
            ->form([
                Forms\Components\Placeholder::make('workflow_info')
                    ->label('🔄 Safe Workflow Steps')
                    ->content('1. Safe sync products from Google Sheets (no delete)\n2. Safe sync stock from Ginee (READ-ONLY Master Products API)\n\nCompletely safe - no risk to existing data.'),
                Forms\Components\Toggle::make('dry_run')
                    ->label('Dry Run (Preview Only)')
                    ->default(true),
            ])
            ->action(function (array $data) {
                try {
                    $dryRun = $data['dry_run'] ?? true;
                    $mode = $dryRun ? 'DRY RUN - ' : '';
                    
                    // Step 1: Safe sync products from Google Sheets
                    $googleSyncService = new GoogleSheetsSync();
                    $productResult = $googleSyncService->syncProductsSafeMode([
                        'sync_strategy' => 'safe_mode_no_delete',
                        'dry_run' => $dryRun
                    ]);

                    if (!$productResult['success']) {
                        throw new Exception('Product sync failed: ' . $productResult['message']);
                    }

                    // Step 2: Safe sync stock from Ginee (limited for testing)
                    $products = \App\Models\Product::whereNotNull('sku')
                        ->where('sku', '!=', '')
                        ->limit(5)
                        ->get();
                    
                    $stockSyncCount = 0;
                    $ginee = new \App\Services\GineeClient();
                    
                    foreach ($products as $product) {
                        $result = $ginee->getMasterProducts([
                            'page' => 0,
                            'size' => 5,
                            'sku' => $product->sku
                        ]);
                        
                        if (($result['code'] ?? null) === 'SUCCESS') {
                            $stockSyncCount++;
                        }
                    }

                    $productStats = $productResult['stats'];
                    
                    Notification::make()
                        ->title("✅ {$mode}Safe Workflow Completed!")
                        ->body("Products - Created: {$productStats['created']}, Updated: {$productStats['updated']}\nStock - Checked: {$stockSyncCount} products safely")
                        ->success()
                        ->duration(15000)
                        ->send();

                } catch (Exception $e) {
                    Notification::make()
                        ->title('❌ Safe Workflow Failed')
                        ->body('Error: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            }),
            
        // ⚙️ ADVANCED SETTINGS
        Tables\Actions\Action::make('advanced_settings')
            ->label('⚙️ Advanced Settings')
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('gray')
            ->modalHeading('Advanced Sync Settings')
            ->modalDescription('Configure advanced synchronization options')
            ->form([
                Forms\Components\Section::make('Sync Configuration')
                    ->schema([
                        Forms\Components\Toggle::make('auto_sync_enabled')
                            ->label('Enable Auto Sync')
                            ->helperText('Automatically sync when products are updated'),
                        Forms\Components\TextInput::make('sync_interval')
                            ->label('Sync Interval (minutes)')
                            ->numeric()
                            ->default(30),
                        Forms\Components\Select::make('default_batch_size')
                            ->label('Default Batch Size')
                            ->options([
                                '5' => '5 products',
                                '10' => '10 products',
                                '20' => '20 products',
                                '50' => '50 products',
                            ])
                            ->default('10'),
                        Forms\Components\Toggle::make('enable_logging')
                            ->label('Enable Detailed Logging')
                            ->default(true),
                    ]),
                Forms\Components\Section::make('Notification Settings')
                    ->schema([
                        Forms\Components\Toggle::make('notify_on_success')
                            ->label('Notify on Successful Syncs')
                            ->default(true),
                        Forms\Components\Toggle::make('notify_on_errors')
                            ->label('Notify on Errors')
                            ->default(true),
                    ]),
            ])
            ->action(function (array $data) {
                // Save settings to cache
                cache()->put('ginee_sync_settings', $data, now()->addDays(30));
                
                Notification::make()
                    ->title('⚙️ Settings Saved')
                    ->body('Advanced sync settings have been updated successfully')
                    ->success()
                    ->send();
            }),
            
    ])->label('🚀 Workflows')
      ->icon('heroicon-o-cog-6-tooth')
      ->color('primary')
      ->button(),
    
    // 🔧 TOOLS GROUP
    Tables\Actions\ActionGroup::make([
        
        // 🔗 TEST CONNECTION
        Tables\Actions\Action::make('test_connection')
            ->label('🔗 Test Connection')
            ->icon('heroicon-o-signal')
            ->color('gray')
            ->action(function () {
                try {
                    $syncService = new GoogleSheetsSync();
                    $result = $syncService->testConnection();
                    
                    if ($result['success']) {
                        Notification::make()
                            ->title('✅ Connection Successful')
                            ->body('Successfully connected to Google Sheets')
                            ->success()
                            ->send();
                    } else {
                        throw new Exception($result['message']);
                    }
                } catch (Exception $e) {
                    Notification::make()
                        ->title('❌ Test Failed')
                        ->body('Error: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            }),

        // ⚡ ANALYZE SKU PATTERNS
        Tables\Actions\Action::make('analyze_sku_patterns')
            ->label('⚡ Analyze SKU Patterns')
            ->icon('heroicon-o-chart-bar')
            ->color('info')
            ->action(function () {
                try {
                    $skuParentCounts = Product::selectRaw('sku_parent, COUNT(*) as count')
                        ->whereNotNull('sku_parent')
                        ->groupBy('sku_parent')
                        ->orderBy('count', 'desc')
                        ->limit(10)
                        ->get();
                        
                    $message = "SKU Parent Analysis (Top 10):\n\n";
                    foreach ($skuParentCounts as $item) {
                        $message .= "• {$item->sku_parent}: {$item->count} variants\n";
                    }
                    
                    $totalSkuParents = Product::whereNotNull('sku_parent')->distinct('sku_parent')->count();
                    $totalProducts = Product::count();
                    $message .= "\nSummary:\n";
                    $message .= "• Total SKU Parents: {$totalSkuParents}\n";
                    $message .= "• Total Products: {$totalProducts}\n";
                    $message .= "• Avg variants per SKU: " . round($totalProducts / max($totalSkuParents, 1), 1);
                    
                    Notification::make()
                        ->title('📊 SKU Pattern Analysis')
                        ->body($message)
                        ->info()
                        ->duration(15000)
                        ->send();
                        
                } catch (Exception $e) {
                    Notification::make()
                        ->title('❌ Analysis Failed')
                        ->body('Error: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            }),

        // 🔍 ANALYZE PRODUCT TYPES  
        Tables\Actions\Action::make('analyze_product_types')
            ->label('🔍 Analyze Product Types')
            ->icon('heroicon-o-document-magnifying-glass')
            ->color('warning')
            ->action(function () {
                try {
                    $syncService = new GoogleSheetsSync();
                    
                    if (!method_exists($syncService, 'analyzeProductTypes')) {
                        Notification::make()
                            ->title('❌ Method Not Found')
                            ->body('analyzeProductTypes method not found. Please update GoogleSheetsSync.')
                            ->danger()
                            ->send();
                        return;
                    }
                    
                    $analysis = $syncService->analyzeProductTypes();
                    
                    if ($analysis['success']) {
                        $message = "Product Type Analysis:\n\n";
                        $message .= "📊 Total rows: {$analysis['total_rows']}\n";
                        $message .= "🔢 Unique types: {$analysis['unique_product_types']}\n\n";
                        
                        $message .= "🏷️ Distribution:\n";
                        foreach ($analysis['product_type_distribution'] as $type => $count) {
                            $emoji = match($type) {
                                'running' => '🏃',
                                'basketball' => '🏀',
                                'tennis' => '🎾',
                                'badminton' => '🏸',
                                'training' => '💪',
                                default => '•'
                            };
                            $message .= "  {$emoji} {$type}: {$count}\n";
                        }
                        
                        Notification::make()
                            ->title('🔍 Product Type Analysis')
                            ->body($message)
                            ->info()
                            ->duration(20000)
                            ->send();
                    } else {
                        throw new Exception($analysis['message']);
                    }
                } catch (Exception $e) {
                    Notification::make()
                        ->title('❌ Analysis Failed')
                        ->body('Error: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            }),
            
    ])->label('🔧 Tools')
      ->icon('heroicon-o-wrench-screwdriver')
      ->color('gray')
      ->button(),

])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->color('info'),
                    
                Tables\Actions\EditAction::make()
                    ->color('warning'),
                    
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Product')
                    ->modalDescription('Are you sure you want to delete this product? This action cannot be undone.')
                    ->color('danger'),

                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-m-document-duplicate')
                    ->color('success')
                    ->action(function (Product $record) {
                        $newProduct = $record->replicate();
                        $newProduct->name = $record->name . ' (Copy)';
                        $newProduct->sku = $record->sku . '-copy-' . time();
                        $newProduct->slug = Product::generateUniqueSlug($newProduct->name);
                        $newProduct->save();

                        Notification::make()
                            ->title('✅ Product Duplicated')
                            ->body("Product '{$newProduct->name}' has been created")
                            ->success()
                            ->send();
                    }),

                // ⭐ NEW: Show All Excel Data Action
                Tables\Actions\Action::make('show_excel_data')
                    ->label('Excel Data')
                    ->icon('heroicon-m-table-cells')
                    ->color('info')
                    ->modalHeading(fn (Product $record) => 'All Excel Data: ' . $record->name)
                    ->modalContent(function (Product $record) {
                        $relatedProducts = Product::where('sku_parent', $record->sku_parent)
                            ->where('id', '!=', $record->id)
                            ->get(['id', 'name', 'sku', 'available_sizes', 'stock_quantity']);
                            
                        $content = "<div style='font-family: monospace; font-size: 12px;'>";
                        $content .= "<h4>📋 Complete Excel Column Data</h4>";
                        $content .= "<table style='width: 100%; border-collapse: collapse;'>";
                        
                        // Excel columns data
                        $excelData = [
                            'Product Name' => $record->name,
                            'Brand' => $record->brand ?? 'Not set',
                            'SKU Parent' => $record->sku_parent ?? 'Not set',
                            'Individual SKU' => $record->sku ?? 'Not set',
                            'Price' => 'Rp ' . number_format($record->price ?? 0),
                            'Sale Price' => $record->sale_price ? 'Rp ' . number_format($record->sale_price) : 'Not set',
                            'Stock Quantity' => $record->stock_quantity ?? 0,
                            'Weight (kg)' => $record->weight ?? 'Not set',
                            'Length (cm)' => $record->length ?? 'Not set',
                            'Width (cm)' => $record->width ?? 'Not set',
                            'Height (cm)' => $record->height ?? 'Not set',
                            'Available Sizes' => is_array($record->available_sizes) ? implode(', ', $record->available_sizes) : 'No sizes',
                            'Gender Target' => is_array($record->gender_target) ? implode(', ', $record->gender_target) : 'No gender',
                            'Product Type (from Excel)' => $record->product_type ?? 'Not set',
                            'Featured Sale' => $record->is_featured_sale ? 'Yes' : 'No',
                            'Sale Start Date' => $record->sale_start_date ?? 'Not set',
                            'Sale End Date' => $record->sale_end_date ?? 'Not set',
                            'Images Count' => is_array($record->images) ? count($record->images) : 0,
                            'Active' => $record->is_active ? 'Yes' : 'No',
                            'Featured' => $record->is_featured ? 'Yes' : 'No',
                            'Created' => $record->created_at->format('d M Y H:i'),
                            'Last Updated' => $record->updated_at->format('d M Y H:i'),
                        ];
                        
                        foreach ($excelData as $label => $value) {
                            $content .= "<tr>";
                            $content .= "<td style='border: 1px solid #ddd; padding: 8px; background: #f5f5f5; font-weight: bold; width: 30%;'>{$label}</td>";
                            $content .= "<td style='border: 1px solid #ddd; padding: 8px; width: 70%;'>{$value}</td>";
                            $content .= "</tr>";
                        }
                        $content .= "</table>";
                        
                        if ($record->images && is_array($record->images) && count($record->images) > 0) {
                            $content .= "<h4>🖼️ Images from Excel</h4>";
                            foreach ($record->images as $index => $imageUrl) {
                                $content .= "<p><strong>Image " . ($index + 1) . ":</strong> <a href='{$imageUrl}' target='_blank'>" . substr($imageUrl, 0, 60) . "...</a></p>";
                            }
                        }
                        
                        if ($relatedProducts->count() > 0) {
                            $content .= "<h4>🔗 Related Products (Same SKU Parent)</h4>";
                            $content .= "<table style='width: 100%; border-collapse: collapse;'>";
                            $content .= "<tr style='background: #f5f5f5;'>";
                            $content .= "<th style='border: 1px solid #ddd; padding: 8px;'>ID</th>";
                            $content .= "<th style='border: 1px solid #ddd; padding: 8px;'>Name</th>";
                            $content .= "<th style='border: 1px solid #ddd; padding: 8px;'>SKU</th>";
                            $content .= "<th style='border: 1px solid #ddd; padding: 8px;'>Size</th>";
                            $content .= "<th style='border: 1px solid #ddd; padding: 8px;'>Stock</th>";
                            $content .= "</tr>";
                            
                            foreach ($relatedProducts as $related) {
                                $content .= "<tr>";
                                $content .= "<td style='border: 1px solid #ddd; padding: 8px;'>{$related->id}</td>";
                                $content .= "<td style='border: 1px solid #ddd; padding: 8px;'>" . substr($related->name, 0, 40) . "...</td>";
                                $content .= "<td style='border: 1px solid #ddd; padding: 8px;'>{$related->sku}</td>";
                                $content .= "<td style='border: 1px solid #ddd; padding: 8px;'>" . (is_array($related->available_sizes) ? implode(', ', $related->available_sizes) : 'No sizes') . "</td>";
                                $content .= "<td style='border: 1px solid #ddd; padding: 8px;'>{$related->stock_quantity}</td>";
                                $content .= "</tr>";
                            }
                            $content .= "</table>";
                            $content .= "<p><small>Total variants for this SKU Parent: " . ($relatedProducts->count() + 1) . "</small></p>";
                        }
                        
                        $content .= "</div>";
                        return new \Illuminate\Support\HtmlString($content);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
           ->bulkActions([
    Tables\Actions\BulkActionGroup::make([
        // === GINEE INDIVIDUAL SYNC ACTIONS ===
        
        // 📥 Sync Selected Products FROM Ginee
        Tables\Actions\BulkAction::make('sync_from_ginee_individual')
            ->label('📥 Sync FROM Ginee (Selected)')
            ->icon('heroicon-o-arrow-down-circle')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Sync Selected Products FROM Ginee')
            ->modalDescription(function ($records) {
                $count = $records->count();
                // Fix: Handle null relationships safely
                $mappedCount = $records->filter(function($r) {
                    return $r->gineeMappings && $r->gineeMappings->isNotEmpty();
                })->count();
                $skus = $records->pluck('sku')->take(5)->join(', ');
                $more = $records->count() > 5 ? ' and ' . ($records->count() - 5) . ' more...' : '';
                
                return "This will fetch stock data from Ginee for {$count} selected products ({$mappedCount} mapped): {$skus}{$more}";
            })
            ->form([
                Forms\Components\Section::make('Sync Options')
                    ->schema([
                        Forms\Components\Toggle::make('dry_run')
                            ->label('🧪 Dry Run (Preview Only)')
                            ->default(true)
                            ->helperText('Enable to preview changes without updating database'),
                            
                        Forms\Components\Toggle::make('only_mapped')
                            ->label('📋 Only Mapped Products')
                            ->default(true)
                            ->helperText('Sync only products that have Ginee mappings'),
                            
                        Forms\Components\TextInput::make('batch_size')
                            ->label('Batch Size')
                            ->numeric()
                            ->default(20)
                            ->minValue(1)
                            ->maxValue(50)
                            ->helperText('Number of products to process per batch'),
                    ]),
            ])
            ->action(function (Collection $records, array $data) {
                try {
                    $syncService = new \App\Services\GineeStockSyncService();
                    $onlyMapped = $data['only_mapped'] ?? true;
                    
                    // Filter records safely, handle null relationships
                    if ($onlyMapped) {
                        $records = $records->filter(function($r) {
                            return $r->gineeMappings && $r->gineeMappings->isNotEmpty();
                        });
                    }
                    
                    $skus = $records->pluck('sku')->filter()->toArray(); // filter out nulls
                    
                    if (empty($skus)) {
                        Notification::make()
                            ->title('ℹ️ No Products to Sync')
                            ->body('No products found with Ginee mappings.')
                            ->info()
                            ->send();
                        return;
                    }
                    
                    $result = $syncService->syncMultipleSkusIndividually($skus, [
                        'dry_run' => $data['dry_run'] ?? true,
                        'batch_size' => $data['batch_size'] ?? 20
                    ]);
                    
                    if ($result['success']) {
                        $data_result = $result['data'];
                        
                        $message = ($data['dry_run'] ? '🧪 DRY RUN - ' : '') . "Sync completed!\n\n";
                        $message .= "✅ Successful: {$data_result['successful']}\n";
                        $message .= "❌ Failed: {$data_result['failed']}\n";
                        $message .= "🔍 Not Found: {$data_result['not_found']}\n";
                        $message .= "🔗 No Mapping: {$data_result['no_mapping']}\n";
                        $message .= "📊 Total Processed: {$data_result['total_requested']}";
                        
                        if ($data_result['failed'] > 0) {
                            $message .= "\n\nFirst few errors:\n" . 
                                       implode("\n", array_slice($data_result['errors'], 0, 3));
                        }
                        
                        $message .= "\n\n🔍 Session ID: {$data_result['session_id']}";
                        
                        Notification::make()
                            ->title($data['dry_run'] ? '🧪 Sync Preview Completed' : '✅ Sync Completed')
                            ->body($message)
                            ->success()
                            ->duration(20000)
                            ->send();
                    }
                        
                } catch (Exception $e) {
                    Notification::make()
                        ->title('❌ Sync Failed')
                        ->body('Error: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            }),

        // 📤 Push Selected Products TO Ginee  
        Tables\Actions\BulkAction::make('push_to_ginee_individual')
            ->label('📤 Push TO Ginee (Selected)')
            ->icon('heroicon-o-arrow-up-circle')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Push Selected Products TO Ginee')
            ->modalDescription(function ($records) {
                $count = $records->count();
                // Handle null relationships safely
                $mappedCount = $records->filter(function($r) {
                    return $r->gineeMappings && $r->gineeMappings->isNotEmpty();
                })->count();
                $totalStock = $records->sum('stock_quantity');
                $products = $records->take(3)->map(fn($p) => "{$p->name} ({$p->sku})")->join(', ');
                $more = $records->count() > 3 ? ' and ' . ($records->count() - 3) . ' more...' : '';
                
                return "This will push local stock to Ginee for {$count} products ({$mappedCount} mapped, total stock: {$totalStock}): {$products}{$more}";
            })
            ->form([
                Forms\Components\Section::make('Push Options')
                    ->schema([
                        Forms\Components\Toggle::make('dry_run')
                            ->label('🧪 Dry Run (Preview Only)')
                            ->default(true)
                            ->helperText('Enable to preview changes without updating Ginee'),
                            
                        Forms\Components\Toggle::make('force_update')
                            ->label('🔄 Force Update')
                            ->default(false)
                            ->helperText('Update even if stock appears to be in sync'),
                            
                        Forms\Components\Toggle::make('only_mapped')
                            ->label('📋 Only Mapped Products')
                            ->default(true)
                            ->helperText('Push only products that have Ginee mappings'),
                            
                        Forms\Components\TextInput::make('batch_size')
                            ->label('Batch Size')
                            ->numeric()
                            ->default(20)
                            ->minValue(1)
                            ->maxValue(50)
                            ->helperText('Number of products to process per batch'),
                    ]),
            ])
            ->action(function (Collection $records, array $data) {
                try {
                    $syncService = new \App\Services\GineeStockSyncService();
                    $onlyMapped = $data['only_mapped'] ?? true;
                    
                    // Filter records safely, handle null relationships
                    if ($onlyMapped) {
                        $records = $records->filter(function($r) {
                            return $r->gineeMappings && $r->gineeMappings->isNotEmpty();
                        });
                    }
                    
                    $skus = $records->pluck('sku')->filter()->toArray(); // filter out nulls
                    
                    if (empty($skus)) {
                        Notification::make()
                            ->title('ℹ️ No Products to Push')
                            ->body('No products found with Ginee mappings.')
                            ->info()
                            ->send();
                        return;
                    }
                    
                    $result = $syncService->pushMultipleSkusIndividually($skus, [
                        'dry_run' => $data['dry_run'] ?? true,
                        'batch_size' => $data['batch_size'] ?? 20,
                        'force_update' => $data['force_update'] ?? false
                    ]);
                    
                    if ($result['success']) {
                        $data_result = $result['data'];
                        
                        $message = ($data['dry_run'] ? '🧪 DRY RUN - ' : '') . "Push completed!\n\n";
                        $message .= "✅ Successful: {$data_result['successful']}\n";
                        $message .= "❌ Failed: {$data_result['failed']}\n";
                        $message .= "⏭️ Skipped: {$data_result['skipped']}\n";
                        $message .= "🔍 Not Found: {$data_result['not_found']}\n";
                        $message .= "🔗 No Mapping: {$data_result['no_mapping']}\n";
                        $message .= "📊 Total Processed: {$data_result['total_requested']}";
                        
                        if ($data_result['failed'] > 0) {
                            $message .= "\n\nFirst few errors:\n" . 
                                       implode("\n", array_slice($data_result['errors'], 0, 3));
                        }
                        
                        $message .= "\n\n🔍 Session ID: {$data_result['session_id']}";
                        
                        Notification::make()
                            ->title($data['dry_run'] ? '🧪 Push Preview Completed' : '✅ Push Completed')
                            ->body($message)
                            ->success()
                            ->duration(20000)
                            ->send();
                    }
                        
                } catch (Exception $e) {
                    Notification::make()
                        ->title('❌ Push Failed')
                        ->body('Error: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            }),

        // 🔄 Bidirectional Sync (Both directions)
        Tables\Actions\BulkAction::make('bidirectional_sync_individual')
            ->label('🔄 Bidirectional Sync (Selected)')
            ->icon('heroicon-o-arrow-path')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Bidirectional Sync for Selected Products')
            ->modalDescription(function ($records) {
                $count = $records->count();
                // Handle null relationships safely
                $mappedCount = $records->filter(function($r) {
                    return $r->gineeMappings && $r->gineeMappings->isNotEmpty();
                })->count();
                return "This will first sync FROM Ginee, then push any local changes TO Ginee for {$count} products ({$mappedCount} mapped).";
            })
            ->form([
                Forms\Components\Section::make('Bidirectional Sync Options')
                    ->schema([
                        Forms\Components\Toggle::make('dry_run')
                            ->label('🧪 Dry Run (Preview Only)')
                            ->default(true)
                            ->helperText('Enable to preview changes without updating anything'),
                            
                        Forms\Components\Toggle::make('only_mapped')
                            ->label('📋 Only Mapped Products')
                            ->default(true)
                            ->helperText('Sync only products that have Ginee mappings'),
                            
                        Forms\Components\TextInput::make('batch_size')
                            ->label('Batch Size')
                            ->numeric()
                            ->default(15)
                            ->minValue(1)
                            ->maxValue(30)
                            ->helperText('Smaller batches recommended for bidirectional sync'),
                    ]),
                    
                Forms\Components\Section::make('Process Information')
                    ->schema([
                        Forms\Components\Placeholder::make('process_info')
                            ->content('🔄 Bidirectional sync process:

1. 📥 Sync all selected products FROM Ginee
2. ⏳ Wait 2 seconds for API rate limiting  
3. 📤 Push all selected products TO Ginee

This ensures maximum data accuracy between both systems.')
                    ])
                    ->collapsed()
                    ->collapsible(),
            ])
            ->action(function (Collection $records, array $data) {
                try {
                    $syncService = new \App\Services\GineeStockSyncService();
                    $onlyMapped = $data['only_mapped'] ?? true;
                    
                    // Filter records safely, handle null relationships
                    if ($onlyMapped) {
                        $records = $records->filter(function($r) {
                            return $r->gineeMappings && $r->gineeMappings->isNotEmpty();
                        });
                    }
                    
                    $skus = $records->pluck('sku')->filter()->toArray(); // filter out nulls
                    
                    if (empty($skus)) {
                        Notification::make()
                            ->title('ℹ️ No Products to Sync')
                            ->body('No products found with Ginee mappings.')
                            ->info()
                            ->send();
                        return;
                    }
                    
                    $result = $syncService->bidirectionalSyncMultipleSkus($skus, [
                        'dry_run' => $data['dry_run'] ?? true,
                        'batch_size' => $data['batch_size'] ?? 15
                    ]);
                    
                    if ($result['success']) {
                        $data_result = $result['data'];
                        $summary = $data_result['summary'];
                        
                        $message = ($data['dry_run'] ? '🧪 PREVIEW - ' : '') . "Bidirectional sync completed!\n\n";
                        $message .= "📥 FROM Ginee Phase:\n";
                        $message .= "  ✅ Successful: {$summary['sync_successful']}\n";
                        $message .= "  ❌ Failed: {$summary['sync_failed']}\n\n";
                        $message .= "📤 TO Ginee Phase:\n";
                        $message .= "  ✅ Successful: {$summary['push_successful']}\n";
                        $message .= "  ❌ Failed: {$summary['push_failed']}\n";
                        $message .= "  ⏭️ Skipped: {$summary['push_skipped']}\n";
                        $message .= "📊 Total Requested: {$summary['total_requested']}";
                        
                        $message .= "\n\n🔍 Session ID: {$data_result['session_id']}";
                        
                        Notification::make()
                            ->title($data['dry_run'] ? '🧪 Bidirectional Preview' : '✅ Bidirectional Sync Completed')
                            ->body($message)
                            ->success()
                            ->duration(25000)
                            ->send();
                    }
                        
                } catch (Exception $e) {
                    Notification::make()
                        ->title('❌ Bidirectional Sync Failed')
                        ->body('Error: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            }),

    // Standard delete action
    Tables\Actions\DeleteBulkAction::make()
        ->requiresConfirmation()
        ->modalHeading('Delete Selected Products')
        ->modalDescription('Are you sure you want to delete these products? This action cannot be undone.')
        ->color('danger'),
]),  // <- This was missing the closing bracket and parenthesis
            ])  // <- This closes the entire bulkActions method
            ->defaultSort('created_at', 'desc');  // <- Add this if you want default sorting
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
            'view' => Pages\ViewProduct::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $count = static::getModel()::count();
        return match(true) {
            $count > 100 => 'success',
            $count > 50 => 'warning',
            $count > 0 => 'info',
            default => 'danger'
        };
    }
    
    
}