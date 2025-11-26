<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                'total_processed' => count($records)
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
        
        // ⭐ FIXED: Match exact database schema from your screenshot
        $productData = [
            'product_type' => 'BLACKFRIDAY',  // FIXED: Always BLACKFRIDAY
            'category_id' => $this->getBlackFridayCategoryId(),  // Get or create Black Friday category
            'brand' => $this->sanitizeString($record['brand']),
            'related_product' => $this->sanitizeString($record['related_product'] ?? ''),
            'name' => $this->sanitizeString($record['name']),
            'description' => $this->sanitizeString($record['description'] ?? ''),
            'price' => $this->sanitizeFloat($record['sale_price'] ?? $record['price'] ?? 0),
            'sale_price' => $this->sanitizeFloat($record['sale_price'] ?? 0), 
            'original_price' => $this->sanitizeFloat($record['price'] ?? 0),
            'sku' => $this->sanitizeString($record['sku']),
            'sku_parent' => $this->sanitizeString($record['sku_parent'] ?? ''),
            'stock_quantity' => $this->sanitizeInt($record['stock_quantity'] ?? 1),
            'min_stock_level' => $this->sanitizeInt($record['min_stock_level'] ?? 5),
            'weight' => $this->sanitizeFloat($record['weight'] ?? 500), // Default 500g
            'is_active' => true,
            'is_featured' => $this->sanitizeBool($record['is_featured'] ?? 'false'),
            'is_featured_sale' => $this->sanitizeBool($record['is_featured'] ?? 'false')
        ];
        
        // Handle image URLs - store as JSON array
        $imageUrls = [];
        for ($i = 1; $i <= 5; $i++) {
            $imageKey = "images_$i";
            if (!empty($record[$imageKey] ?? '')) {
                $imageUrls[] = trim($record[$imageKey]);
            }
        }
        
        // Store images as JSON (as per database schema)
        if (!empty($imageUrls)) {
            $productData['images'] = json_encode($imageUrls);
            // Set main image for featured_image field
            $productData['featured_image'] = $imageUrls[0];
        }
        
        // ⭐ FIXED: Handle size properly using available_sizes JSON field
        $size = $this->sanitizeString($record['available_sizes'] ?? '');
        if (!empty($size)) {
            // Store as JSON array in available_sizes column
            $productData['available_sizes'] = json_encode([$size]);
            
            // For specifications JSON field
            $productData['specifications'] = json_encode([
                'size' => $size,
                'type' => 'BLACKFRIDAY'
            ]);
        }
        
        // Handle available_colors as JSON
        if (!empty($record['available_colors'] ?? '')) {
            $colors = explode(',', $record['available_colors']);
            $colors = array_map('trim', $colors);
            $productData['available_colors'] = json_encode($colors);
        }
        
        // Generate unique slug
        $baseSlug = Str::slug($productData['name']);
        $slug = $baseSlug;
        $counter = 1;
        
        while (Product::where('slug', $slug)->where('sku', '!=', $productData['sku'])->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        $productData['slug'] = $slug;
        
        Log::info("🖤 Processing Black Friday product", [
            'line' => $lineNumber,
            'sku' => $productData['sku'],
            'name' => $productData['name'],
            'product_type' => $productData['product_type'],
            'category_id' => $productData['category_id'],
            'available_sizes' => $productData['available_sizes'] ?? null
        ]);
        
        // Update or create in products table
        Product::updateOrCreate(
            ['sku' => $productData['sku']],
            $productData
        );
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