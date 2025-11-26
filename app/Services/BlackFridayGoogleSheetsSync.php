<?php

namespace App\Services;

use App\Models\Product;  // CHANGE: Import Product instead of BlackFridayProduct
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class BlackFridayGoogleSheetsSync
{
    protected $sheetUrl;
    
    public function __construct()
    {
        $this->sheetUrl = 'https://docs.google.com/spreadsheets/d/1TJi2-UpmvtnjXWfGG7htw-fLKW-eRI8ERzuJsR-5kcg/export?format=csv&gid=582214489';
    }

    public function syncFromGoogleSheets()
    {
        try {
            Log::info('🖤 Starting Black Friday sync to Products table', [
                'url' => $this->sheetUrl,
                'timestamp' => now()
            ]);
            
            $response = Http::timeout(30)->get($this->sheetUrl);
            
            if (!$response->successful()) {
                throw new Exception("Failed to fetch data from Google Sheets. Status: {$response->status()}");
            }

            $csvData = $response->body();
            
            if (empty($csvData)) {
                throw new Exception('Empty response from Google Sheets');
            }
            
            $records = $this->parseCSV($csvData);
            
            if (empty($records)) {
                throw new Exception('No data records found after parsing');
            }
            
            $header = array_shift($records);
            
            Log::info('🖤 CSV Header found', ['header' => $header]);
            
            $syncCount = 0;
            $errorCount = 0;
            $errors = [];
            
            foreach ($records as $lineNumber => $record) {
                if (empty($record) || count($record) < 10) continue;
                
                try {
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
        
        // ⭐ IMPORTANT: Always set product_type as BLACKFRIDAY
        $productData = [
            'product_type' => 'BLACKFRIDAY',  // FIXED: Always BLACKFRIDAY
            'brand' => $this->sanitizeString($record['brand']),
            'related_product' => $this->sanitizeString($record['related_product'] ?? ''),
            'name' => $this->sanitizeString($record['name']),
            'description' => $this->sanitizeString($record['description'] ?? ''),
            'price' => $this->sanitizeFloat($record['sale_price'] ?? $record['price'] ?? 0),
            'original_price' => $this->sanitizeFloat($record['price'] ?? 0),
            'sku' => $this->sanitizeString($record['sku']),
            'sku_parent' => $this->sanitizeString($record['sku_parent'] ?? ''),
            'stock_quantity' => $this->sanitizeInt($record['stock_quantity'] ?? 1),
            'is_active' => true,
            'is_featured' => $this->sanitizeBool($record['is_featured'] ?? 'false'),
            'is_sale' => true,  // Mark as sale
        ];
        
        // Handle image URLs
        $imageUrls = [];
        for ($i = 1; $i <= 5; $i++) {
            $imageKey = "images_$i";
            if (!empty($record[$imageKey] ?? '')) {
                $imageUrls[] = trim($record[$imageKey]);
            }
        }
        $productData['images'] = $imageUrls;
        
        // Set featured image
        if (!empty($imageUrls)) {
            $productData['featured_image'] = $imageUrls[0];
        }
        
        // Handle size - create size variant
        $size = $this->sanitizeString($record['available_sizes'] ?? '');
        if (!empty($size)) {
            $productData['size_variants'] = [
                [
                    'id' => $productData['sku'],
                    'size' => $size,
                    'sku' => $productData['sku'],
                    'stock' => $productData['stock_quantity'],
                    'price' => $productData['price'],
                    'original_price' => $productData['original_price']
                ]
            ];
            $productData['total_stock'] = $productData['stock_quantity'];
        }
        
        // Calculate sale price
        if ($productData['original_price'] > 0 && $productData['price'] > 0) {
            $productData['sale_price'] = $productData['price'];
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
            'product_type' => $productData['product_type']
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
