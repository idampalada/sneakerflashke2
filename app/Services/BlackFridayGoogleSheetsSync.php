<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

class BlackFridayGoogleSheetsSync
{
    protected $spreadsheetId = '1TJi2-UpmvtnjXWfGG7htw-fLKW-eRI8ERzuJsR-5kcg';
    protected $sheetName = 'data_blackfriday';
    protected $blackFridayCategoryId = null;
    
    /**
     * Get or create Black Friday category
     */
    protected function getBlackFridayCategoryId()
    {
        if ($this->blackFridayCategoryId) {
            return $this->blackFridayCategoryId;
        }
        
        // Try to find existing Black Friday category
        $category = Category::where('slug', 'black-friday')
            ->orWhere('name', 'Black Friday')
            ->first();
        
        if (!$category) {
            // Create Black Friday category if it doesn't exist
            $category = Category::create([
                'name' => 'Black Friday',
                'slug' => 'black-friday',
                'description' => 'Black Friday Special Deals',
                'is_active' => true,
                'sort_order' => 99,
                'icon' => '🖤',
                'meta_title' => 'Black Friday Deals',
                'meta_description' => 'Special Black Friday deals and discounts'
            ]);
            
            Log::info('🖤 Created Black Friday category', ['id' => $category->id]);
        }
        
        $this->blackFridayCategoryId = $category->id;
        return $category->id;
    }
    
    /**
     * ⭐ SMART SYNC: CREATE/UPDATE/DELETE Black Friday products
     */
    public function smartSync()
    {
        try {
            Log::info('🧠 Starting SMART SYNC for Black Friday products');
            
            // 1. Get all SKUs from Google Sheets
            $spreadsheetSkus = $this->getSkusFromSpreadsheet();
            
            // 2. Get all existing Black Friday SKUs from database
            $existingSkus = Product::where('product_type', 'BLACKFRIDAY')
                ->pluck('sku')
                ->toArray();
            
            // 3. Perform regular sync (CREATE/UPDATE)
            $syncResult = $this->syncFromGoogleSheets();
            
            if (!$syncResult['success']) {
                throw new Exception($syncResult['error']);
            }
            
            // 4. DELETE products that are NOT in spreadsheet anymore
            $skusToDelete = array_diff($existingSkus, $spreadsheetSkus);
            $deletedCount = 0;
            
            if (!empty($skusToDelete)) {
                Log::info('🗑️ Deleting Black Friday products not in spreadsheet', [
                    'skus_to_delete' => $skusToDelete,
                    'count' => count($skusToDelete)
                ]);
                
                foreach ($skusToDelete as $sku) {
                    $product = Product::where('sku', $sku)
                        ->where('product_type', 'BLACKFRIDAY')
                        ->first();
                    
                    if ($product) {
                        Log::info("🗑️ Deleting Black Friday product: {$product->name} (SKU: {$sku})");
                        $product->delete();
                        $deletedCount++;
                    }
                }
            }
            
            // 5. Calculate final stats
            $finalResult = [
                'success' => true,
                'synced' => $syncResult['synced'],
                'created' => $this->calculateCreatedProducts($existingSkus, $spreadsheetSkus),
                'updated' => $syncResult['synced'] - $this->calculateCreatedProducts($existingSkus, $spreadsheetSkus),
                'deleted' => $deletedCount,
                'errors' => $syncResult['errors'],
                'total_processed' => $syncResult['total_processed'],
                'spreadsheet_skus' => count($spreadsheetSkus),
                'existing_skus_before' => count($existingSkus),
                'final_count' => Product::where('product_type', 'BLACKFRIDAY')->count()
            ];
            
            Log::info('🧠 SMART SYNC completed successfully', $finalResult);
            
            return $finalResult;
            
        } catch (Exception $e) {
            Log::error('🧠 SMART SYNC failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get all SKUs from Google Sheets
     */
    protected function getSkusFromSpreadsheet()
    {
        try {
            $gid = '582214489';
            $exportUrl = "https://docs.google.com/spreadsheets/d/{$this->spreadsheetId}/export?format=csv&gid={$gid}";
            
            $response = Http::get($exportUrl);
            
            if (!$response->successful()) {
                throw new Exception("Failed to fetch spreadsheet data: " . $response->status());
            }
            
            $records = $this->parseCSV($response->body());
            
            if (count($records) < 2) {
                return [];
            }
            
            $header = $records[0];
            $skus = [];
            
            for ($lineNumber = 1; $lineNumber < count($records); $lineNumber++) {
                $record = $records[$lineNumber];
                
                if (empty(array_filter($record))) {
                    continue;
                }
                
                if ($this->isProductDataRow($record, $header)) {
                    $recordData = array_combine($header, $record);
                    $sku = $this->sanitizeString($recordData['sku'] ?? '');
                    
                    if (!empty($sku)) {
                        $skus[] = $sku;
                    }
                }
            }
            
            Log::info('📋 Retrieved SKUs from spreadsheet', [
                'count' => count($skus),
                'skus' => array_slice($skus, 0, 10) // Log first 10 for verification
            ]);
            
            return $skus;
            
        } catch (Exception $e) {
            Log::error('📋 Failed to get SKUs from spreadsheet', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Calculate how many products were created (new SKUs)
     */
    protected function calculateCreatedProducts($existingSkus, $spreadsheetSkus)
    {
        $newSkus = array_diff($spreadsheetSkus, $existingSkus);
        return count($newSkus);
    }
    
    /**
     * Regular sync method (CREATE/UPDATE only)
     */
    public function syncFromGoogleSheets()
    {
        try {
            $gid = '582214489'; // data_blackfriday sheet GID
            $exportUrl = "https://docs.google.com/spreadsheets/d/{$this->spreadsheetId}/export?format=csv&gid={$gid}";
            
            Log::info('🖤 Starting Black Friday sync to Products table', [
                'url' => $exportUrl,
                'timestamp' => now()->toIso8601String()
            ]);
            
            // Fetch CSV data
            $response = Http::get($exportUrl);
            
            if (!$response->successful()) {
                throw new Exception("Failed to fetch spreadsheet data: " . $response->status());
            }
            
            $csvData = $response->body();
            
            if (empty($csvData)) {
                throw new Exception("No data received from spreadsheet");
            }
            
            // Parse CSV
            $records = $this->parseCSV($csvData);
            
            if (count($records) < 2) {
                throw new Exception("No data found in spreadsheet");
            }
            
            $header = $records[0];
            Log::info('🖤 CSV Header found', ['header' => $header]);
            
            $syncCount = 0;
            $errorCount = 0;
            $errors = [];
            
            // Process each row starting from row 2 (skip header)
            for ($lineNumber = 1; $lineNumber < count($records); $lineNumber++) {
                try {
                    $record = $records[$lineNumber];
                    
                    // Skip empty rows
                    if (empty(array_filter($record))) {
                        continue;
                    }
                    
                    // Check if this row has product data
                    if ($this->isProductDataRow($record, $header)) {
                        $recordData = array_combine($header, $record);
                        
                        if (!$recordData) {
                            throw new Exception("Failed to combine header with data");
                        }
                        
                        $this->processRecord($recordData, $lineNumber + 2);
                        $syncCount++;
                    }
                    
                } catch (Exception $e) {
                    $errorCount++;
                    $error = [
                        'line' => $lineNumber + 2,
                        'error' => $e->getMessage()
                    ];
                    $errors[] = $error;
                    
                    Log::error('🖤 Error processing Black Friday record', $error);
                }
            }
            
            Log::info('🖤 Black Friday sync completed', [
                'synced' => $syncCount,
                'errors' => $errorCount
            ]);
            
            return [
                'success' => true,
                'synced' => $syncCount,
                'errors' => $errorCount,
                'total_processed' => count($records) - 1 // Exclude header
            ];
            
        } catch (Exception $e) {
            Log::error('🖤 Black Friday sync failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    protected function parseCSV($csvData)
    {
        $records = [];
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $csvData);
        rewind($handle);
        
        while (($row = fgetcsv($handle)) !== false) {
            $records[] = $row;
        }
        
        fclose($handle);
        return $records;
    }
    
    protected function isProductDataRow($record, $header)
    {
        $skuIndex = array_search('sku', $header);
        $priceIndex = array_search('price', $header);
        
        if ($skuIndex === false || $priceIndex === false) {
            return false;
        }
        
        if (empty($record[$skuIndex] ?? '') || !is_numeric($record[$priceIndex] ?? '')) {
            return false;
        }
        
        $firstCol = strtolower($record[0] ?? '');
        $descriptionIndicators = [
            'sneakers flash berdiri',
            'pastikan untuk memeriksa',
            'untuk memastikan',
            'operasional pengiriman',
            'senin s/d',
            'sabtu dan minggu',
            'libur pada',
            'catatan: harap'
        ];
        
        foreach ($descriptionIndicators as $indicator) {
            if (strpos($firstCol, $indicator) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    protected function processRecord($record, $lineNumber)
    {
        // Validate required fields
        $requiredFields = ['name', 'brand', 'sku'];
        foreach ($requiredFields as $field) {
            if (empty($record[$field] ?? '')) {
                throw new Exception("Required field '{$field}' is empty");
            }
        }
        
        // ⭐ SESUAI MODEL: price = harga original, sale_price = harga diskon
        $originalPrice = $this->sanitizeFloat($record['price'] ?? 0);          // Harga asli (3599000)
        $salePrice = $this->sanitizeFloat($record['sale_price'] ?? 0);         // Harga Black Friday (400000)
        
        // Validasi: pastikan sale_price lebih kecil dari price
        if ($salePrice <= 0 || $salePrice >= $originalPrice) {
            // Jika sale_price tidak valid, set null (tidak ada diskon)
            $salePrice = null;
        }
        
        // Build available sizes array
        $availableSizes = [];
        $sizesString = $this->sanitizeString($record['available_sizes'] ?? '');
        if (!empty($sizesString)) {
            $availableSizes = [$sizesString];
        }
        
        // Build images array
        $images = [];
        for ($i = 1; $i <= 5; $i++) {
            $imageUrl = $this->sanitizeString($record["images_$i"] ?? '');
            if (!empty($imageUrl)) {
                $images[] = $imageUrl;
            }
        }
        
        // Build specifications array
        $specifications = [
            'weight' => $this->sanitizeFloat($record['weight'] ?? 0),
            'length' => $this->sanitizeFloat($record['lengh'] ?? 0), // Note: 'lengh' typo from sheet
            'width' => $this->sanitizeFloat($record['wide'] ?? 0),
            'height' => $this->sanitizeFloat($record['high'] ?? 0),
        ];
        
        // ⭐ SESUAI FILLABLE MODEL: Menggunakan field yang ada di model
        $productData = [
            'product_type' => 'BLACKFRIDAY',  // Always BLACKFRIDAY
            'category_id' => $this->getBlackFridayCategoryId(),
            'name' => $this->sanitizeString($record['name']),
            'brand' => $this->sanitizeString($record['brand']),
            'description' => $this->sanitizeString($record['description'] ?? ''),
            'sku' => $this->sanitizeString($record['sku']),
            'sku_parent' => $this->sanitizeString($record['sku_parent'] ?? ''),
            
            // ⭐ PRICING SESUAI MODEL: price = original, sale_price = discount
            'price' => $originalPrice,        // Harga asli/original (3599000)
            'sale_price' => $salePrice,       // Harga diskon Black Friday (400000) atau null
            
            'available_sizes' => $availableSizes,
            'stock_quantity' => $this->sanitizeInt($record['stock_quantity'] ?? 1),
            'images' => $images,
            'specifications' => $specifications,
            
            // Standard fields
            'is_active' => true,
            'is_featured' => false,
            'weight' => $specifications['weight'],
            'length' => $specifications['length'], 
            'width' => $specifications['width'],
            'height' => $specifications['height'],
        ];
        
        // Generate unique slug if needed
        $slug = Str::slug($productData['name']);
        $counter = 1;
        while (Product::where('slug', $slug)->where('sku', '!=', $productData['sku'])->exists()) {
            $slug = Str::slug($productData['name']) . '-' . $counter;
            $counter++;
        }
        $productData['slug'] = $slug;
        
        // ⭐ ENHANCED LOGGING: Show pricing details
        $discountAmount = $salePrice ? ($originalPrice - $salePrice) : 0;
        $discountPercent = $salePrice && $originalPrice > 0 ? 
                          round((($originalPrice - $salePrice) / $originalPrice) * 100) : 0;
        
        Log::info("🖤 Processing Black Friday product", [
            'line' => $lineNumber,
            'sku' => $productData['sku'],
            'name' => $productData['name'],
            'price' => $productData['price'],           // Original price (3599000)
            'sale_price' => $productData['sale_price'], // Black Friday price (400000)
            'discount_amount' => $discountAmount,        // 3199000
            'discount_percent' => $discountPercent,      // 89%
            'available_sizes' => $productData['available_sizes'] ?? null
        ]);
        
        // ⭐ UPDATE/CREATE PRODUCT
        $existingProduct = Product::where('sku', $productData['sku'])->first();
        
        if ($existingProduct) {
            // UPDATE existing product
            $existingProduct->update($productData);
            Log::info("✅ Updated Black Friday product", [
                'id' => $existingProduct->id,
                'sku' => $productData['sku'],
                'price' => $productData['price'],
                'sale_price' => $productData['sale_price'],
                'discount_percent' => $discountPercent
            ]);
        } else {
            // CREATE new product
            $newProduct = Product::create($productData);
            Log::info("🆕 Created Black Friday product", [
                'id' => $newProduct->id,
                'sku' => $productData['sku'],
                'price' => $productData['price'],
                'sale_price' => $productData['sale_price'],
                'discount_percent' => $discountPercent
            ]);
        }
    }
    
    protected function sanitizeString($value)
    {
        return trim(strip_tags($value ?? ''));
    }
    
    protected function sanitizeFloat($value)
    {
        $cleaned = preg_replace('/[^\d.]/', '', $value ?? '0');
        return floatval($cleaned);
    }
    
    protected function sanitizeInt($value)
    {
        if ($value === null || $value === '') {
            return 1;
        }
        $cleaned = preg_replace('/[^\d]/', '', $value);
        return max(1, intval($cleaned));
    }
    
    protected function sanitizeBool($value)
    {
        if (is_bool($value)) return $value;
        
        $value = strtolower(trim($value ?? ''));
        return in_array($value, ['true', '1', 'yes', 'active', 'on']);
    }
}