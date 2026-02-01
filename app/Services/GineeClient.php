<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GineeClient
{
    private string $base;
    private string $accessKey;
    private string $secretKey;
    private string $country;
    private string $defaultWarehouseId;

    public function __construct(?array $cfg = null)
    {
        $cfg = $cfg ?: config('services.ginee', []);
        $this->base      = rtrim($cfg['base'] ?? 'https://api.ginee.com', '/');
        $this->accessKey = (string)($cfg['access_key'] ?? '');
        $this->secretKey = (string)($cfg['secret_key'] ?? '');
        $this->country   = (string)($cfg['country'] ?? 'ID');
        $this->defaultWarehouseId = (string)($cfg['warehouse_id'] ?? 'WW614C57B6E21B840001B4A467');

        if (!$this->accessKey || !$this->secretKey) {
            throw new \RuntimeException('Ginee access_key/secret_key belum terisi.');
        }
    }

    /* ===================== WORKING STOCK SYNCHRONIZATION METHODS ===================== */

    /**
     * Get Master Products from Ginee - WORKING ✅
     * Endpoint: /openapi/product/master/v1/list
     */
    public function getMasterProducts(array $params = []): array
    {
        $body = [
            'page' => $params['page'] ?? 0,
            'size' => $params['size'] ?? 50,
            'productName' => $params['productName'] ?? null,
            'sku' => $params['sku'] ?? null,
            'brand' => $params['brand'] ?? null,
            'barCode' => $params['barCode'] ?? null,
            'categoryId' => $params['categoryId'] ?? null,
            'createDateFrom' => $params['createDateFrom'] ?? null,
            'createDateTo' => $params['createDateTo'] ?? null,
        ];
        
        // Remove null values
        $body = array_filter($body, fn ($v) => !is_null($v) && $v !== '');
        
        Log::info('📦 [Ginee] Getting master products', [
            'page' => $body['page'],
            'size' => $body['size']
        ]);
        
        return $this->request('POST', '/openapi/product/master/v1/list', $body);
    }

    /**
     * Get Warehouse Inventory from Ginee - WORKING ✅  
     * Endpoint: /openapi/warehouse-inventory/v1/sku/list
     * NOTE: Tidak perlu warehouseId, akan return semua inventory
     */
 

    /**
     * Update Stock in Ginee - WORKING ✅
     * Endpoint: /openapi/warehouse-inventory/v1/product/stock/update
     * Format: {"warehouseId": "xxx", "stockList": [{"masterSku": "xxx", "quantity": 123}]}
     */
    public function updateStock(array $stockUpdates, string $warehouseId = null): array
    {
        $warehouseId = $warehouseId ?: $this->defaultWarehouseId;
        
        $body = [
            'warehouseId' => $warehouseId,
            'stockList' => $stockUpdates
        ];
        
        Log::info('📤 [Ginee] Updating stock', [
            'warehouse_id' => $warehouseId,
            'updates_count' => count($stockUpdates),
            'sample_sku' => $stockUpdates[0]['masterSku'] ?? 'none'
        ]);
        
        return $this->request('POST', '/openapi/warehouse-inventory/v1/product/stock/update', $body);
    }

    /**
     * Get Shops/Stores - WORKING ✅
     * Endpoint: /openapi/v3/oms/shop/list
     */
    public function getShops(array $params = []): array
    {
        $body = [
            'page' => $params['page'] ?? 0,
            'size' => $params['size'] ?? 20,
        ];
        
        return $this->request('POST', '/openapi/v3/oms/shop/list', $body);
    }

    /**
     * Get Warehouses - WORKING ✅
     * Endpoint: /openapi/warehouse/v1/search
     */
    public function getWarehouses(array $params = []): array
    {
        $body = [
            'page' => $params['page'] ?? 0,
            'size' => $params['size'] ?? 20,
        ];
        
        return $this->request('POST', '/openapi/warehouse/v1/search', $body);
    }

    /* ===================== COMPLETE SYNC WORKFLOWS ===================== */

    /**
     * Pull all products from Ginee to sync to local database
     */
    public function pullAllProducts(int $batchSize = 50): array
    {
        Log::info('🔄 [Ginee] Starting complete product pull');
        
        $allProducts = [];
        $page = 0;
        $totalFetched = 0;
        
        do {
            $result = $this->getMasterProducts([
                'page' => $page,
                'size' => $batchSize
            ]);
            
            if (($result['code'] ?? null) !== 'SUCCESS') {
                Log::error('❌ [Ginee] Failed to fetch products', [
                    'page' => $page,
                    'error' => $result['message'] ?? 'Unknown error'
                ]);
                break;
            }
            
            $data = $result['data'] ?? [];
            $products = $data['list'] ?? [];
            $total = $data['total'] ?? 0;
            
            $allProducts = array_merge($allProducts, $products);
            $totalFetched += count($products);
            
            Log::info("📦 [Ginee] Fetched page {$page}: " . count($products) . " products");
            
            $page++;
            
            // Stop if we've fetched all products or no more products
        } while (!empty($products) && $totalFetched < ($total ?? 0));
        
        Log::info("✅ [Ginee] Complete product pull finished", [
            'total_products' => count($allProducts),
            'pages_fetched' => $page
        ]);
        
        return [
            'code' => 'SUCCESS',
            'message' => 'All products fetched successfully',
            'data' => [
                'products' => $allProducts,
                'total_count' => count($allProducts),
                'pages_fetched' => $page
            ]
        ];
    }

    /**
     * Pull all inventory/stock data from Ginee
     */
    public function pullAllInventory(int $batchSize = 100): array  // Ubah default ke 100
    {
        Log::info('📊 [Ginee] Starting complete inventory pull');
        
        $allInventory = [];
        $page = 0;
        $totalFetched = 0;
        
        // Selalu gunakan warehouse ID (jangan opsional)
        $warehouseId = $this->defaultWarehouseId;  // Gunakan default yang sama
        
        while (true) {  // Gunakan while(true) seperti di Node.js
            Log::info("📄 [Ginee] Fetching inventory page {$page}...");
            
            // Selalu sertakan warehouseId dalam request
            $result = $this->getWarehouseInventory([
                'page' => $page,
                'size' => $batchSize,
                'warehouseId' => $warehouseId  // Tambahkan ini
            ]);
            
            if (($result['code'] ?? null) !== 'SUCCESS') {
                Log::error("⚠️ [Ginee] Stop: " . ($result['message'] ?? 'No response'), [
                    'page' => $page
                ]);
                break;
            }
            
            $data = $result['data'] ?? [];
            $inventory = $data['content'] ?? [];
            
            // Kondisi terminasi sama seperti Node.js
            if (empty($inventory)) {
                Log::info('✅ [Ginee] Semua halaman selesai.');
                break;
            }
            
            $allInventory = array_merge($allInventory, $inventory);
            $totalFetched += count($inventory);
            
            Log::info("📦 [Ginee] Page {$page} fetched: " . count($inventory) . " items (Total: {$totalFetched})");
            
            $page++;
            
            // Tambahkan delay untuk rate limit
            usleep(300000);  // 300ms, sama seperti Node.js
        }
        
        Log::info("✅ [Ginee] Selesai! Total SKU tersimpan: " . count($allInventory));
        
        return [
            'code' => 'SUCCESS',
            'message' => 'Complete inventory pull finished',
            'data' => [
                'inventory' => $allInventory,
                'total_count' => count($allInventory),
                'pages_fetched' => $page
            ]
        ];
    }

    /**
     * Push stock updates to Ginee in batches
     */
    public function pushStockUpdates(array $stockUpdates, int $batchSize = 20, string $warehouseId = null): array
    {
        Log::info('📤 [Ginee] Starting batch stock updates', [
            'total_updates' => count($stockUpdates),
            'batch_size' => $batchSize
        ]);
        
        $batches = array_chunk($stockUpdates, $batchSize);
        $results = [];
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($batches as $batchIndex => $batch) {
            Log::info("📦 [Ginee] Processing batch " . ($batchIndex + 1) . "/" . count($batches));
            
            $result = $this->updateStock($batch, $warehouseId);
            
            if (($result['code'] ?? null) === 'SUCCESS') {
                $successCount += count($batch);
                Log::info("✅ [Ginee] Batch " . ($batchIndex + 1) . " successful");
            } else {
                $failureCount += count($batch);
                Log::error("❌ [Ginee] Batch " . ($batchIndex + 1) . " failed", [
                    'error' => $result['message'] ?? 'Unknown error'
                ]);
            }
            
            $results[] = [
                'batch' => $batchIndex + 1,
                'items' => count($batch),
                'success' => ($result['code'] ?? null) === 'SUCCESS',
                'result' => $result
            ];
            
            // Small delay between batches to avoid rate limiting
            if ($batchIndex < count($batches) - 1) {
                usleep(500000); // 0.5 second delay
            }
        }
        
        Log::info("🎯 [Ginee] Batch stock updates completed", [
            'total_batches' => count($batches),
            'successful_items' => $successCount,
            'failed_items' => $failureCount
        ]);
        
        return [
            'code' => 'SUCCESS',
            'message' => 'Stock updates processed',
            'data' => [
                'total_batches' => count($batches),
                'successful_items' => $successCount,
                'failed_items' => $failureCount,
                'batch_results' => $results
            ]
        ];
    }

    /* ===================== SYNC HELPERS ===================== */

    /**
     * Create stock update format for Ginee
     */
    public function createStockUpdate(string $masterSku, int $quantity): array
    {
        return [
            'masterSku' => $masterSku,
            'quantity' => $quantity
        ];
    }


    /* ===================== CORE REQUEST METHOD ===================== */

    private function request(string $method, string $path, array $json = []): array
    {
        $method = strtoupper($method);
        $path = '/' . ltrim($path, '/');

        // Convert body to JSON string
        $bodyStr = empty($json) ? '{}' : json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // GINEE SIGNATURE FORMAT (verified working)
        $newline = '$';
        $signStr = $method . $newline . $path . $newline;
        
        // Generate signature using HMAC-SHA256
        $signature = base64_encode(hash_hmac('sha256', $signStr, $this->secretKey, true));
        
        // Create authorization header
        $authorization = $this->accessKey . ':' . $signature;

        $headers = [
            'Authorization'   => $authorization,
            'Content-Type'    => 'application/json',
            'X-Advai-Country' => $this->country,
        ];

        Log::debug('🔐 [Ginee] Request details', [
            'method' => $method,
            'path' => $path,
            'body_length' => strlen($bodyStr)
        ]);

        try {
            $response = Http::baseUrl($this->base)
                ->timeout(60)
                ->withHeaders($headers)
                ->acceptJson()
                ->withBody($bodyStr, 'application/json')
                ->send($method, $path);

            $responseData = $response->json() ?? [];
            
            if (($responseData['code'] ?? null) === 'SUCCESS') {
                Log::debug('✅ [Ginee] Request successful', [
                    'path' => $path,
                    'transaction_id' => $responseData['transactionId'] ?? 'N/A'
                ]);
            } else {
                Log::warning('❌ [Ginee] Request failed', [
                    'path' => $path,
                    'code' => $responseData['code'] ?? 'unknown',
                    'message' => $responseData['message'] ?? 'no message'
                ]);
            }

            return $responseData ?: [
                'code' => 'HTTP_ERROR',
                'message' => "HTTP {$response->status()}: {$response->body()}",
                'data' => null
            ];

        } catch (\Exception $e) {
            Log::error('❌ [Ginee] Request exception', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);

            return [
                'code' => 'CLIENT_ERROR',
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /* ===================== TESTING & UTILITIES ===================== */

    public function testStockSyncEndpoints(): array
    {
        Log::info('🧪 [Ginee] Testing all stock sync endpoints');
        
        $results = [];
        
        // Test 1: Get shops (basic connectivity)
        $results['shops'] = $this->getShops(['page' => 0, 'size' => 2]);
        
        // Test 2: Get master products
        $results['master_products'] = $this->getMasterProducts(['page' => 0, 'size' => 3]);
        
        // Test 3: Get warehouse inventory
        $results['warehouse_inventory'] = $this->getWarehouseInventory(['page' => 0, 'size' => 3]);
        
        // Test 4: Stock update (with real SKU that exists)
        $testStockUpdate = $this->createStockUpdate('BOX', 1308);
        $results['stock_update'] = $this->updateStock([$testStockUpdate]);
        
        // Analyze results
        $summary = [];
        foreach ($results as $test => $result) {
            $summary[$test] = [
                'success' => ($result['code'] ?? null) === 'SUCCESS',
                'message' => $result['message'] ?? 'Unknown',
                'transaction_id' => $result['transactionId'] ?? null
            ];
        }
        
        return [
            'code' => 'SUCCESS',
            'message' => 'Stock sync endpoints tested',
            'data' => [
                'summary' => $summary,
                'detailed_results' => $results
            ]
        ];
    }

    public function testAllEndpoints(): array
    {
        Log::info('🧪 [Ginee] Testing all working endpoints');
        
        $results = [];
        
        // Test all working endpoints
        $results['shops'] = $this->getShops(['page' => 0, 'size' => 2]);
        $results['warehouses'] = $this->getWarehouses(['page' => 0, 'size' => 2]);
        $results['master_products'] = $this->getMasterProducts(['page' => 0, 'size' => 3]);
        $results['warehouse_inventory'] = $this->getWarehouseInventory(['page' => 0, 'size' => 3]);
        
        // Test stock update with sample data
        $testStockUpdate = $this->createStockUpdate('BOX', 1308);
        $results['stock_update'] = $this->updateStock([$testStockUpdate]);
        
        return [
            'code' => 'SUCCESS',
            'message' => 'All endpoints tested',
            'data' => $results
        ];
    }
    
public function createAvailableStockUpdate(
    string $masterSku, 
    string $action, 
    int $quantity, 
    string $warehouseId = null, 
    string $shelfInventoryId = '', 
    string $remark = ''
): array {
    $warehouseId = $warehouseId ?: $this->defaultWarehouseId;
    
    return [
        'masterSku' => $masterSku,
        'action' => strtoupper($action), // INCREASE/DECREASE/OVER_WRITE
        'quantity' => $quantity,
        'warehouseId' => $warehouseId,
        'shelfInventoryId' => $shelfInventoryId,
        'remark' => $remark ?: 'Updated via API'
    ];
}
public function getWarehouseInventory(array $params = []): array
{
    // Get the ENABLED warehouse ID first
    $enabledWarehouseId = $this->getEnabledWarehouseId();
    
    $body = [
        'page' => $params['page'] ?? 0,
        'size' => $params['size'] ?? 50,
    ];
    
    // If we found an enabled warehouse, include it
    if ($enabledWarehouseId) {
        $body['warehouseId'] = $enabledWarehouseId;
        
        Log::info('📊 [Ginee] Getting warehouse inventory with enabled warehouse ID', [
            'warehouse_id' => $enabledWarehouseId,
            'page' => $body['page'],
            'size' => $body['size']
        ]);
    } else {
        Log::info('📊 [Ginee] Getting warehouse inventory without warehouse ID', [
            'page' => $body['page'],
            'size' => $body['size']
        ]);
    }
    
    return $this->request('POST', '/openapi/warehouse-inventory/v1/sku/list', $body);
}

private function getEnabledWarehouseId(): string
{
    static $cachedWarehouseId = null;

    if ($cachedWarehouseId !== null) {
        return $cachedWarehouseId;
    }

    $configuredWarehouseId = config('services.ginee.warehouse_id');

    $result = $this->getWarehouses(['page' => 0, 'size' => 20]);

    if (($result['code'] ?? null) !== 'SUCCESS') {
        throw new \RuntimeException('Failed to fetch warehouses from Ginee');
    }

    $warehouses = $result['data']['content'] ?? [];

    // 1️⃣ PRIORITAS ABSOLUT: warehouse dari config
    if ($configuredWarehouseId) {
        foreach ($warehouses as $warehouse) {
            if (
                ($warehouse['id'] ?? null) === $configuredWarehouseId &&
                ($warehouse['status'] ?? null) === 'ENABLE'
            ) {
                Log::info('🏭 [Ginee] Using configured warehouse', [
                    'id' => $warehouse['id'],
                    'code' => $warehouse['code'],
                    'name' => $warehouse['name'],
                ]);

                return $cachedWarehouseId = $warehouse['id'];
            }
        }

        // ❌ Kalau config ada tapi tidak ketemu → HARUS FAIL
        throw new \RuntimeException(
            "Configured GINEE_WAREHOUSE_ID not found or not ENABLE"
        );
    }

    // 2️⃣ FALLBACK (opsional, kalau memang mau)
    foreach ($warehouses as $warehouse) {
        if (($warehouse['status'] ?? null) === 'ENABLE') {
            Log::warning('⚠️ [Ginee] Falling back to first ENABLE warehouse', [
                'id' => $warehouse['id'],
                'code' => $warehouse['code'],
            ]);

            return $cachedWarehouseId = $warehouse['id'];
        }
    }

    throw new \RuntimeException('No ENABLE warehouse found in Ginee');
}

public function testBothStockEndpoints(): array
{
    Log::info('🧪 [Ginee] Testing both stock update endpoints');
    
    $results = [];
    
    // Get the correct warehouse ID using the fixed method
    $warehouseId = $this->getEnabledWarehouseId();
    
    Log::info('🏗️ [Ginee] Using warehouse ID: ' . $warehouseId);
    
    // Test 1: AdjustInventory (warehouse stock update)
    $testStockUpdate = $this->createStockUpdate('BOX', 0);
    $results['adjust_inventory'] = $this->updateStock([$testStockUpdate], $warehouseId);
    
    // Test 2: UpdateAvailableStock (available stock update with actions)
    $testAvailableStockUpdate = $this->createAvailableStockUpdate('BOX', 'INCREASE', 0, $warehouseId);
    $results['update_available_stock'] = $this->updateAvailableStock([$testAvailableStockUpdate]);
    
    return [
        'code' => 'SUCCESS',
        'message' => 'Both stock endpoints tested',
        'data' => [
            'warehouse_id_used' => $warehouseId,
            'results' => $results
        ]
    ];
}

/**
 * Enhanced method to get current stock with proper error handling
 */
public function getCurrentStock(array $skus): array
{
    Log::info('📊 [Ginee] Getting current stock for specific SKUs', [
        'sku_count' => count($skus)
    ]);
    
    $stockData = [];
    $page = 0;
    $batchSize = 50;
    
    // Get all inventory and filter by SKUs
    do {
        $result = $this->getWarehouseInventory([
            'page' => $page,
            'size' => $batchSize
        ]);
        
        if (($result['code'] ?? null) !== 'SUCCESS') {
            Log::warning('❌ [Ginee] Failed to get inventory, trying alternative method', [
                'page' => $page,
                'error' => $result['message'] ?? 'Unknown error'
            ]);
            
            // Fallback: Try to get stock from master products
            $masterResult = $this->getMasterProducts([
                'page' => $page,
                'size' => $batchSize
            ]);
            
            if (($masterResult['code'] ?? null) === 'SUCCESS') {
                $products = $masterResult['data']['list'] ?? [];
                
                foreach ($products as $product) {
                    $masterSku = $product['masterSku'] ?? null;
                    
                    if ($masterSku && in_array($masterSku, $skus)) {
                        $stockData[$masterSku] = [
                            'masterSku' => $masterSku,
                            'warehouseStock' => $product['stockQuantity'] ?? 0,
                            'availableStock' => $product['stockQuantity'] ?? 0,
                            'lockedStock' => 0,
                            'productName' => $product['name'] ?? 'Unknown',
                            'source' => 'master_products'
                        ];
                    }
                }
            }
            break;
        }
        
        $inventory = $result['data']['content'] ?? [];
        
        foreach ($inventory as $item) {
            $masterSku = $item['masterVariation']['masterSku'] ?? null;
            
            if ($masterSku && in_array($masterSku, $skus)) {
                $stockData[$masterSku] = [
                    'masterSku' => $masterSku,
                    'warehouseStock' => $item['warehouseStock'] ?? 0,
                    'availableStock' => $item['availableStock'] ?? 0,
                    'lockedStock' => $item['lockedStock'] ?? 0,
                    'productName' => $item['masterVariation']['name'] ?? 'Unknown',
                    'source' => 'warehouse_inventory'
                ];
            }
        }
        
        $page++;
        
    } while (!empty($inventory) && count($stockData) < count($skus));
    
    return [
        'code' => 'SUCCESS',
        'message' => 'Stock data retrieved',
        'data' => array_values($stockData)
    ];
}
public function updateSpareStock(array $stockUpdates): array
{
    $body = [
        'stockList' => $stockUpdates
    ];
    
    Log::info('🏪 [Ginee] Updating spare stock with actions', [
        'updates_count' => count($stockUpdates),
        'sample_sku' => $stockUpdates[0]['masterSku'] ?? 'none',
        'sample_action' => $stockUpdates[0]['action'] ?? 'none'
    ]);
    
    return $this->request('POST', '/openapi/v1/oms/stock/spare-stock/update', $body);
}
public function createSpareStockUpdate(
    string $masterSku, 
    string $action, 
    int $quantity, 
    string $warehouseId = null, 
    string $shelfInventoryId = '', 
    string $remark = ''
): array {
    $warehouseId = $warehouseId ?: $this->defaultWarehouseId;
    
    return [
        'masterSku' => $masterSku,
        'action' => strtoupper($action), // INCREASE/DECREASE/OVER_WRITE
        'quantity' => $quantity,
        'warehouseId' => $warehouseId,
        'shelfInventoryId' => $shelfInventoryId,
        'remark' => $remark ?: 'Spare stock updated via API'
    ];
}

/**
 * Batch update spare stock with different actions
 * 
 * @param array $stockUpdates Array of spare stock updates
 * @param int $batchSize Number of updates per batch (max 20 for this endpoint)
 * @return array Batch processing results
 */
public function batchUpdateSpareStock(array $stockUpdates, int $batchSize = 20): array
{
    // Spare stock update endpoint has max 20 items per request
    $batchSize = min($batchSize, 20);
    
    Log::info('🏪 [Ginee] Starting batch spare stock updates', [
        'total_updates' => count($stockUpdates),
        'batch_size' => $batchSize
    ]);
    
    $batches = array_chunk($stockUpdates, $batchSize);
    $results = [];
    $totalSuccess = 0;
    $totalFailed = 0;
    
    foreach ($batches as $batchIndex => $batch) {
        Log::info("📦 [Ginee] Processing spare stock batch " . ($batchIndex + 1) . "/" . count($batches));
        
        $result = $this->updateSpareStock($batch);
        
        if (($result['code'] ?? null) === 'SUCCESS') {
            $successCount = count($result['data']['successList'] ?? []);
            $failedCount = count($result['data']['failedList'] ?? []);
            
            $totalSuccess += $successCount;
            $totalFailed += $failedCount;
            
            Log::info("✅ [Ginee] Spare stock batch " . ($batchIndex + 1) . " completed", [
                'successful' => $successCount,
                'failed' => $failedCount
            ]);
        } else {
            $totalFailed += count($batch);
            Log::error("❌ [Ginee] Spare stock batch " . ($batchIndex + 1) . " failed", [
                'error' => $result['message'] ?? 'Unknown error'
            ]);
        }
        
        $results[] = [
            'batch' => $batchIndex + 1,
            'items' => count($batch),
            'success' => ($result['code'] ?? null) === 'SUCCESS',
            'result' => $result
        ];
        
        // Small delay between batches to avoid rate limiting
        if ($batchIndex < count($batches) - 1) {
            usleep(500000); // 0.5 second delay
        }
    }
    
    Log::info("🎯 [Ginee] Batch spare stock updates completed", [
        'total_batches' => count($batches),
        'successful_items' => $totalSuccess,
        'failed_items' => $totalFailed
    ]);
    
    return [
        'code' => 'SUCCESS',
        'message' => 'Spare stock updates processed',
        'data' => [
            'total_batches' => count($batches),
            'successful_items' => $totalSuccess,
            'failed_items' => $totalFailed,
            'batch_results' => $results
        ]
    ];
}

/**
 * Test ALL FOUR stock update endpoints - COMPLETE TEST
 */
public function testAllStockEndpoints(): array
{
    Log::info('🧪 [Ginee] Testing ALL FOUR stock update endpoints');
    
    $results = [];
    
    // Get the correct warehouse ID
    $warehouseId = $this->getEnabledWarehouseId();
    
    Log::info('🏗️ [Ginee] Using warehouse ID for all tests: ' . $warehouseId);
    
    // Test 1: AdjustInventory (warehouse stock update - absolute values)
    $testStockUpdate = $this->createStockUpdate('BOX', 0);
    $results['adjust_inventory'] = $this->updateStock([$testStockUpdate], $warehouseId);
    
    // Test 2: UpdateAvailableStock (available stock update with actions)
    $testAvailableStockUpdate = $this->createAvailableStockUpdate('BOX', 'INCREASE', 0, $warehouseId);
    $results['update_available_stock'] = $this->updateAvailableStock([$testAvailableStockUpdate]);
    
    // Test 3: UpdateSpareStock (spare stock update with actions) - NEW!
    $testSpareStockUpdate = $this->createSpareStockUpdate('BOX', 'INCREASE', 0, $warehouseId);
    $results['update_spare_stock'] = $this->updateSpareStock([$testSpareStockUpdate]);
    
    // Test 4: Warehouse Inventory (read operations)
    $results['warehouse_inventory'] = $this->getWarehouseInventory(['page' => 0, 'size' => 3]);
    
    return [
        'code' => 'SUCCESS',
        'message' => 'All four stock endpoints tested',
        'data' => [
            'warehouse_id_used' => $warehouseId,
            'results' => $results,
            'endpoint_summary' => [
                'adjust_inventory' => ($results['adjust_inventory']['code'] ?? null) === 'SUCCESS',
                'update_available_stock' => ($results['update_available_stock']['code'] ?? null) === 'SUCCESS',
                'update_spare_stock' => ($results['update_spare_stock']['code'] ?? null) === 'SUCCESS',
                'warehouse_inventory' => ($results['warehouse_inventory']['code'] ?? null) === 'SUCCESS'
            ]
        ]
    ];
}

/**
 * Comprehensive stock management - Use different stock types strategically
 */
public function comprehensiveStockUpdate(string $sku, array $stockLevels, string $warehouseId = null): array
{
    $warehouseId = $warehouseId ?: $this->getEnabledWarehouseId();
    $results = [];
    
    Log::info('🔄 [Ginee] Comprehensive stock update for SKU: ' . $sku, $stockLevels);
    
    // Update warehouse stock (absolute)
    if (isset($stockLevels['warehouse_stock'])) {
        $stockUpdate = $this->createStockUpdate($sku, $stockLevels['warehouse_stock']);
        $results['warehouse'] = $this->updateStock([$stockUpdate], $warehouseId);
    }
    
    // Update available stock (with action)
    if (isset($stockLevels['available_action'], $stockLevels['available_quantity'])) {
        $availableUpdate = $this->createAvailableStockUpdate(
            $sku, 
            $stockLevels['available_action'], 
            $stockLevels['available_quantity'], 
            $warehouseId
        );
        $results['available'] = $this->updateAvailableStock([$availableUpdate]);
    }
    
    // Update spare stock (with action)
    if (isset($stockLevels['spare_action'], $stockLevels['spare_quantity'])) {
        $spareUpdate = $this->createSpareStockUpdate(
            $sku, 
            $stockLevels['spare_action'], 
            $stockLevels['spare_quantity'], 
            $warehouseId
        );
        $results['spare'] = $this->updateSpareStock([$spareUpdate]);
    }
    
    return [
        'code' => 'SUCCESS',
        'message' => 'Comprehensive stock update completed',
        'data' => [
            'sku' => $sku,
            'warehouse_id' => $warehouseId,
            'updates_performed' => array_keys($results),
            'results' => $results
        ]
    ];
}

// ===============================================
// USAGE EXAMPLES AND HELPER METHODS
// ===============================================

/**
 * Example usage methods for different business scenarios
 */

/**
 * Scenario 1: New product stock setup
 */
public function setupNewProductStock(string $sku, int $totalStock, int $spareStock = 0): array
{
    $warehouseId = $this->getEnabledWarehouseId();
    
    // Set initial warehouse stock
    $warehouseUpdate = $this->createStockUpdate($sku, $totalStock);
    $warehouseResult = $this->updateStock([$warehouseUpdate], $warehouseId);
    
    $results = ['warehouse' => $warehouseResult];
    
    // Set spare stock if specified
    if ($spareStock > 0) {
        $spareUpdate = $this->createSpareStockUpdate($sku, 'OVER_WRITE', $spareStock, $warehouseId);
        $results['spare'] = $this->updateSpareStock([$spareUpdate]);
    }
    
    return [
        'code' => 'SUCCESS',
        'message' => 'New product stock setup completed',
        'data' => [
            'sku' => $sku,
            'total_stock' => $totalStock,
            'spare_stock' => $spareStock,
            'results' => $results
        ]
    ];
}

/**
 * Scenario 2: Handle product sale
 */
public function processSale(string $sku, int $soldQuantity): array
{
    $warehouseId = $this->getEnabledWarehouseId();
    
    // Decrease available stock
    $availableUpdate = $this->createAvailableStockUpdate($sku, 'DECREASE', $soldQuantity, $warehouseId);
    $availableResult = $this->updateAvailableStock([$availableUpdate]);
    
    return [
        'code' => 'SUCCESS',
        'message' => 'Sale processed in Ginee',
        'data' => [
            'sku' => $sku,
            'sold_quantity' => $soldQuantity,
            'result' => $availableResult
        ]
    ];
}

/**
 * Scenario 3: Restock from spare to available
 */
public function restockFromSpare(string $sku, int $quantity): array
{
    $warehouseId = $this->getEnabledWarehouseId();
    
    $updates = [
        // Decrease spare stock
        $this->createSpareStockUpdate($sku, 'DECREASE', $quantity, $warehouseId),
        // Increase available stock  
        $this->createAvailableStockUpdate($sku, 'INCREASE', $quantity, $warehouseId)
    ];
    
    $spareResult = $this->updateSpareStock([$updates[0]]);
    $availableResult = $this->updateAvailableStock([$updates[1]]);
    
    return [
        'code' => 'SUCCESS',
        'message' => 'Restock from spare completed',
        'data' => [
            'sku' => $sku,
            'quantity' => $quantity,
            'results' => [
                'spare_decrease' => $spareResult,
                'available_increase' => $availableResult
            ]
        ]
    ];
}
public function getWarehouseInventoryBulk(array $params = []): array
{
    $page = $params['page'] ?? 0;
    $size = min($params['size'] ?? 200, 500); // Max 500 items per page for performance
    
    $body = [
        'page' => $page,
        'size' => $size
    ];
    
    Log::info('📦 [Ginee] Getting bulk warehouse inventory', [
        'page' => $page,
        'size' => $size
    ]);
    
    return $this->request('POST', '/openapi/warehouse-inventory/v1/product/search', $body);
}

/**
 * 🔍 OPTIMIZED: Search products by SKU filter (if available)
 */
public function searchProductsBySku(array $skus, array $params = []): array
{
    $body = [
        'page' => $params['page'] ?? 0,
        'size' => $params['size'] ?? 100,
        'masterSkus' => $skus, // Try to filter by SKUs directly
        'status' => 'ACTIVE'
    ];
    
    Log::info('🔍 [Ginee] Searching products by SKU filter', [
        'sku_count' => count($skus),
        'page' => $body['page'],
        'size' => $body['size']
    ]);
    
    return $this->request('POST', '/openapi/master-product/v1/product/search', $body);
}

/**
 * 📊 Get ALL warehouse inventory efficiently
 */
public function getAllWarehouseInventory(array $options = []): array
{
    $maxPages = $options['max_pages'] ?? 50;
    $pageSize = $options['page_size'] ?? 200;
    $stopOnEmpty = $options['stop_on_empty'] ?? true;
    
    Log::info('📊 [Ginee] Getting ALL warehouse inventory', [
        'max_pages' => $maxPages,
        'page_size' => $pageSize
    ]);
    
    $allItems = [];
    $page = 0;
    $totalFetched = 0;
    $errors = [];
    
    do {
        try {
            $result = $this->getWarehouseInventoryBulk([
                'page' => $page,
                'size' => $pageSize
            ]);
            
            if (($result['code'] ?? null) !== 'SUCCESS') {
                $errors[] = [
                    'page' => $page,
                    'error' => $result['message'] ?? 'Unknown error'
                ];
                Log::warning("❌ Failed to get inventory page {$page}: " . ($result['message'] ?? 'Unknown'));
                
                // Try smaller page size on error
                if ($pageSize > 50) {
                    $pageSize = 50;
                    Log::info("📉 Reducing page size to {$pageSize} and retrying...");
                    continue;
                }
                break;
            }
            
            $items = $result['data']['content'] ?? [];
            $pageItemCount = count($items);
            
            if ($pageItemCount === 0 && $stopOnEmpty) {
                Log::info("✅ No more items on page {$page}, stopping");
                break;
            }
            
            $allItems = array_merge($allItems, $items);
            $totalFetched += $pageItemCount;
            
            Log::info("📦 Page {$page}: Got {$pageItemCount} items (Total: {$totalFetched})");
            
            $page++;
            
            // Safety break
            if ($page >= $maxPages) {
                Log::warning("⚠️ Reached max pages limit ({$maxPages}), stopping");
                break;
            }
            
            // Rate limiting
            usleep(100000); // 0.1 second between pages
            
        } catch (\Exception $e) {
            $errors[] = [
                'page' => $page,
                'error' => $e->getMessage()
            ];
            Log::error("💥 Exception getting inventory page {$page}: " . $e->getMessage());
            break;
        }
        
    } while (true);
    
    Log::info('🏁 [Ginee] Finished getting ALL inventory', [
        'total_items' => count($allItems),
        'pages_fetched' => $page,
        'errors' => count($errors)
    ]);
    
    return [
        'code' => 'SUCCESS',
        'message' => 'All inventory fetched',
        'data' => [
            'content' => $allItems,
            'total' => count($allItems),
            'pages_fetched' => $page,
            'errors' => $errors,
            'performance' => [
                'items_per_page_avg' => $page > 0 ? round(count($allItems) / $page, 2) : 0,
                'total_items' => count($allItems)
            ]
        ]
    ];
}

/**
 * 🎯 Smart SKU search with multiple strategies
 */
public function smartSkuSearch(array $targetSkus, array $options = []): array
{
    $strategies = $options['strategies'] ?? [
        'sku_filter',      // Try to search with SKU filter first
        'bulk_inventory',  // Get all inventory and filter
        'master_products'  // Fallback to master products
    ];
    
    Log::info('🎯 [Ginee] Smart SKU search starting', [
        'target_skus' => count($targetSkus),
        'strategies' => $strategies
    ]);
    
    $foundItems = [];
    $notFound = $targetSkus;
    $strategyResults = [];
    
    foreach ($strategies as $strategy) {
        if (empty($notFound)) {
            Log::info("✅ All SKUs found, stopping search");
            break;
        }
        
        Log::info("🔍 Trying strategy: {$strategy} for " . count($notFound) . " remaining SKUs");
        
        switch ($strategy) {
            case 'sku_filter':
                $result = $this->searchUsingSKUFilter($notFound);
                break;
                
            case 'bulk_inventory':
                $result = $this->searchUsingBulkInventory($notFound, $options);
                break;
                
            case 'master_products':
                $result = $this->searchUsingMasterProducts($notFound);
                break;
                
            default:
                continue 2;
        }
        
        if ($result['success']) {
            $strategyFound = $result['found'];
            $foundItems = array_merge($foundItems, $strategyFound);
            
            // Remove found SKUs from not found list
            $foundSkus = array_keys($strategyFound);
            $notFound = array_diff($notFound, $foundSkus);
            
            $strategyResults[$strategy] = [
                'found_count' => count($strategyFound),
                'success' => true
            ];
            
            Log::info("✅ Strategy '{$strategy}' found " . count($strategyFound) . " SKUs");
        } else {
            $strategyResults[$strategy] = [
                'found_count' => 0,
                'success' => false,
                'error' => $result['error'] ?? 'Unknown error'
            ];
            Log::warning("❌ Strategy '{$strategy}' failed: " . ($result['error'] ?? 'Unknown'));
        }
    }
    
    Log::info('🏁 [Ginee] Smart SKU search completed', [
        'total_requested' => count($targetSkus),
        'total_found' => count($foundItems),
        'not_found' => count($notFound),
        'strategies_used' => array_keys($strategyResults)
    ]);
    
    return [
        'code' => 'SUCCESS',
        'message' => 'Smart search completed',
        'data' => [
            'found_items' => $foundItems,
            'not_found_skus' => $notFound,
            'strategy_results' => $strategyResults,
            'summary' => [
                'total_requested' => count($targetSkus),
                'found_count' => count($foundItems),
                'not_found_count' => count($notFound),
                'success_rate' => round((count($foundItems) / count($targetSkus)) * 100, 2) . '%'
            ]
        ]
    ];
}

/**
 * Strategy 1: Search using SKU filter
 */
private function searchUsingSKUFilter(array $skus): array
{
    try {
        $result = $this->searchProductsBySku($skus);
        
        if (($result['code'] ?? null) === 'SUCCESS') {
            $products = $result['data']['list'] ?? [];
            $found = [];
            
            foreach ($products as $product) {
                $masterSku = $product['masterSku'] ?? '';
                if (in_array($masterSku, $skus)) {
                    $found[$masterSku] = $this->convertProductToStockData($product, 'sku_filter');
                }
            }
            
            return ['success' => true, 'found' => $found];
        }
        
        return ['success' => false, 'error' => $result['message'] ?? 'API failed'];
        
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Strategy 2: Search using bulk inventory
 */
private function searchUsingBulkInventory(array $skus, array $options): array
{
    try {
        $maxPages = min($options['max_pages'] ?? 20, 30); // Limit for performance
        $result = $this->getAllWarehouseInventory([
            'max_pages' => $maxPages,
            'page_size' => 200
        ]);
        
        if (($result['code'] ?? null) === 'SUCCESS') {
            $items = $result['data']['content'] ?? [];
            $found = [];
            
            foreach ($items as $item) {
                $masterVariation = $item['masterVariation'] ?? [];
                $itemSku = strtoupper($masterVariation['masterSku'] ?? '');
                
                if (in_array($itemSku, $skus)) {
                    $found[$itemSku] = $this->convertInventoryItemToStockData($item, 'bulk_inventory');
                }
            }
            
            return ['success' => true, 'found' => $found];
        }
        
        return ['success' => false, 'error' => $result['message'] ?? 'Inventory fetch failed'];
        
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Strategy 3: Search using master products
 */
private function searchUsingMasterProducts(array $skus): array
{
    try {
        $found = [];
        $page = 0;
        $maxPages = 10; // Limit for performance
        
        do {
            $result = $this->getMasterProducts(['page' => $page, 'size' => 100]);
            
            if (($result['code'] ?? null) !== 'SUCCESS') {
                break;
            }
            
            $products = $result['data']['list'] ?? [];
            
            if (empty($products)) {
                break;
            }
            
            foreach ($products as $product) {
                $masterSku = $product['masterSku'] ?? '';
                if (in_array($masterSku, $skus)) {
                    $found[$masterSku] = $this->convertProductToStockData($product, 'master_products');
                }
            }
            
            $page++;
            
        } while ($page < $maxPages && count($found) < count($skus));
        
        return ['success' => true, 'found' => $found];
        
    } catch (\Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Convert product data to standardized stock format
 */
private function convertProductToStockData(array $product, string $source): array
{
    return [
        'sku' => $product['masterSku'] ?? '',
        'product_name' => $product['name'] ?? $product['productName'] ?? 'Unknown Product',
        'warehouse_stock' => $product['stockQuantity'] ?? 0,
        'available_stock' => $product['stockQuantity'] ?? 0,
        'locked_stock' => 0,
        'total_stock' => $product['stockQuantity'] ?? 0,
        'last_updated' => now(),
        'api_source' => $source
    ];
}

/**
 * Convert inventory item to standardized stock format
 */
private function convertInventoryItemToStockData(array $item, string $source): array
{
    $masterVariation = $item['masterVariation'] ?? [];
    $warehouseInventory = $item['warehouseInventory'] ?? [];
    
    return [
        'sku' => $masterVariation['masterSku'] ?? '',
        'product_name' => $masterVariation['name'] ?? 'Unknown Product',
        'warehouse_stock' => $warehouseInventory['stock'] ?? 0,
        'available_stock' => $warehouseInventory['availableStock'] ?? 0,
        'locked_stock' => $warehouseInventory['lockedStock'] ?? 0,
        'total_stock' => ($warehouseInventory['stock'] ?? 0) + ($warehouseInventory['lockedStock'] ?? 0),
        'last_updated' => $warehouseInventory['updateDatetime'] ?? now(),
        'api_source' => $source
    ];
}
public function getOrderById(string $orderId): array
{
    Log::info("🔍 [Ginee] Mengambil detail pesanan: {$orderId}");
    
    $body = [
        'orderIds' => [$orderId]
    ];
    
    // Perbaikan endpoint yang tadinya salah
    return $this->request('POST', '/openapi/order/v1/batch-get', $body);
}
public function updateAvailableStock(array $stockUpdates): array
{
    $body = [
        'stockList' => $stockUpdates
    ];
    
    Log::info('📈 [Ginee] Updating available stock with actions', [
        'updates_count' => count($stockUpdates),
        'sample_sku' => $stockUpdates[0]['masterSku'] ?? 'none',
        'sample_action' => $stockUpdates[0]['action'] ?? 'none'
    ]);
    
    return $this->request('POST', '/openapi/v1/oms/stock/available-stock/update', $body);
}


}