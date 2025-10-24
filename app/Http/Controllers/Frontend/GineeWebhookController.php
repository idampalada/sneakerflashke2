<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GineeWebhookController extends Controller
{
    /**
     * Global webhook endpoint for all Ginee events
     */
    public function global(Request $request)
{
    try {
        // DEBUG CONNECTION - Log lebih detail untuk memeriksa koneksi
        Log::info('🔍 WEBHOOK CONNECTION TEST - DETAILED', [
            'host' => $request->getHost(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'raw_body' => $request->getContent(),
            'server_vars' => [
                'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? 'unknown',
                'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'unknown',
                'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ],
            'timestamp' => now()->toDateTimeString()
        ]);

        // Log the incoming webhook (kode asli)
        Log::info('🔔 Ginee Webhook Received', [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'raw_body' => $request->getContent(),
            'timestamp' => now()
        ]);

        // Verify webhook signature if enabled
        if (config('services.ginee.verify_webhooks', false)) {
            if (!$this->verifyWebhookSignature($request)) {
                Log::warning('❌ Invalid webhook signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $data = $request->all();
        $eventType = $data['event_type'] ?? $data['type'] ?? 'unknown';

        // Store webhook event for debugging
        $this->storeWebhookEvent($request, $eventType);

        // Route to appropriate handler based on event type
        switch ($eventType) {
            case 'master_product_updated':
            case 'product_updated':
                return $this->handleProductUpdate($data);

            case 'stock_updated':
            case 'inventory_updated':
                return $this->handleStockUpdate($data);

            case 'order_created':
            case 'order_updated':
                return $this->handleOrderUpdate($data);

            default:
                Log::info("📝 Unhandled webhook event: {$eventType}", ['data' => $data]);
                return $this->successResponse('Event received but not processed');
        }

    } catch (\Exception $e) {
        // Tambahkan detail lebih lengkap saat error
        Log::error('❌ Webhook processing failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->all(),
            'host' => $request->getHost(),
            'url' => $request->fullUrl(),
            'timestamp' => now()->toDateTimeString()
        ]);

        return response()->json([
            'error' => 'Webhook processing failed',
            'message' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Handle specific order events
     */
public function orders(Request $request)
{
    // Log ke file terpisah untuk memudahkan debugging
    $logPath = storage_path('logs/ginee-webhook.log');
    $logData = date('Y-m-d H:i:s') . ' - Webhook order received from IP: ' . $request->ip() . "\n";
    $logData .= 'Raw data: ' . $request->getContent() . "\n\n";
    file_put_contents($logPath, $logData, FILE_APPEND);
    
    // Log ke laravel.log biasa
    Log::info('📋 GINEE ORDER WEBHOOK RECEIVED', [
        'url' => $request->fullUrl(),
        'method' => $request->method(),
        'ip' => $request->ip(),
        'host' => $request->getHost(),
        'headers' => $request->headers->all(),
        'data' => $request->all(),
        'timestamp' => now()->toDateTimeString()
    ]);

    try {
        // Store the webhook event for debugging
        try {
            DB::table('webhook_events')->insert([
                'source' => 'ginee',
                'entity' => 'order',
                'action' => $request->input('action', 'unknown'),
                'event_type' => $request->input('event_type', 'order_webhook'),
                'payload' => $request->getContent(),
                'headers' => json_encode($request->headers->all()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'processed' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            Log::info('✅ Webhook event stored in database');
        } catch (\Exception $dbEx) {
            Log::error('❌ Failed to store webhook event in database', [
                'error' => $dbEx->getMessage()
            ]);
            // Continue despite DB error
        }

        $data = $request->all();
        Log::info('📦 Processing order data', ['order_id' => $data['orderId'] ?? 'unknown']);
        
        // Process the webhook
        return $this->handleOrderUpdate($data);
    } catch (\Exception $e) {
        $errorMsg = '❌ Order webhook processing failed: ' . $e->getMessage();
        Log::error($errorMsg, [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'data' => $request->all()
        ]);
        
        // Also log to the separate log file
        file_put_contents($logPath, date('Y-m-d H:i:s') . ' - ERROR: ' . $e->getMessage() . "\n", FILE_APPEND);

        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Handle specific master product events
     */
    public function masterProducts(Request $request)
    {
        Log::info('📦 Ginee Master Product Webhook', [
            'data' => $request->all(),
            'timestamp' => now()
        ]);

        try {
            $data = $request->all();
            return $this->handleProductUpdate($data);

        } catch (\Exception $e) {
            Log::error('❌ Master product webhook failed', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /* ===================== WEBHOOK HANDLERS ===================== */

    /**
     * Handle product update webhook
     */
    private function handleProductUpdate(array $data): \Illuminate\Http\JsonResponse
    {
        Log::info('🔄 Processing product update webhook', ['data' => $data]);

        try {
            $productData = $data['product'] ?? $data['data'] ?? $data;
            $sku = $productData['masterSku'] ?? $productData['sku'] ?? null;

            if (!$sku) {
                Log::warning('⚠️ Product webhook missing SKU', ['data' => $data]);
                return $this->errorResponse('Missing product SKU');
            }

            DB::beginTransaction();

            $product = Product::where('sku', $sku)->first();

            if (!$product) {
                Log::info("📦 Creating new product from webhook: {$sku}");
                $product = new Product();
                $product->sku = $sku;
                $product->slug = \Str::slug(($productData['productName'] ?? 'product') . '-' . $sku);
            } else {
                Log::info("📝 Updating existing product from webhook: {$sku}");
            }

            // Update product fields
            $product->fill([
                'name' => $productData['productName'] ?? $product->name,
                'description' => $productData['description'] ?? $product->description,
                'price' => isset($productData['price']) ? (float)$productData['price'] : $product->price,
                'stock_quantity' => isset($productData['stock']) ? (int)$productData['stock'] : $product->stock_quantity,
                'weight' => isset($productData['weight']) ? (float)$productData['weight'] : $product->weight,
                'brand' => $productData['brand'] ?? $product->brand,
                'is_active' => isset($productData['status']) ? ($productData['status'] === 'ACTIVE') : $product->is_active,
                'ginee_last_sync' => now(),
                'ginee_sync_status' => 'synced',
                'ginee_data' => json_encode($productData)
            ]);

            $product->save();

            DB::commit();

            Log::info("✅ Product updated successfully: {$sku}");

            return $this->successResponse('Product updated successfully', [
                'sku' => $sku,
                'product_id' => $product->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Product update failed', [
                'error' => $e->getMessage(),
                'sku' => $sku ?? 'unknown'
            ]);

            return $this->errorResponse('Product update failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle stock update webhook
     */
    private function handleStockUpdate(array $data): \Illuminate\Http\JsonResponse
    {
        Log::info('📊 Processing stock update webhook', ['data' => $data]);

        try {
            $stockData = $data['stock'] ?? $data['inventory'] ?? $data['data'] ?? $data;
            
            // Handle multiple stock updates
            $updates = [];
            if (isset($stockData['items']) && is_array($stockData['items'])) {
                $updates = $stockData['items'];
            } elseif (isset($stockData['masterSku'])) {
                $updates = [$stockData];
            }

            if (empty($updates)) {
                Log::warning('⚠️ Stock webhook has no valid stock data', ['data' => $data]);
                return $this->errorResponse('No valid stock data found');
            }

            DB::beginTransaction();

            $updatedCount = 0;
            foreach ($updates as $item) {
                $sku = $item['masterSku'] ?? $item['sku'] ?? null;
                $quantity = $item['quantity'] ?? $item['stock'] ?? null;

                if (!$sku || $quantity === null) {
                    Log::warning('⚠️ Skipping invalid stock item', ['item' => $item]);
                    continue;
                }

                $product = Product::where('sku', $sku)->first();
                if ($product) {
                    $oldStock = $product->stock_quantity;
                    $product->stock_quantity = (int)$quantity;
                    $product->ginee_last_sync = now();
                    $product->save();

                    Log::info("📊 Stock updated: {$sku} ({$oldStock} → {$quantity})");
                    $updatedCount++;
                } else {
                    Log::warning("📦 Product not found for stock update: {$sku}");
                }
            }

            DB::commit();

            Log::info("✅ Stock update completed", ['updated_count' => $updatedCount]);

            return $this->successResponse('Stock updated successfully', [
                'updated_count' => $updatedCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Stock update failed', ['error' => $e->getMessage()]);

            return $this->errorResponse('Stock update failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle order update webhook
     */
    /**
 * Handle order update webhook
 */
private function handleOrderUpdate(array $data): \Illuminate\Http\JsonResponse
{
    Log::info('📋 Processing order update webhook', ['data' => $data]);

    try {
        // Ambil data order dari struktur webhook Ginee
        $orderData = $data['payload'] ?? $data['order'] ?? $data['data'] ?? $data;
        $gineeOrderId = $orderData['orderId'] ?? $orderData['id'] ?? $data['id'] ?? null;
        $orderStatus = $orderData['orderStatus'] ?? $orderData['externalOrderStatus'] ?? null;
        $action = $data['action'] ?? '';

        if (!$gineeOrderId) {
            Log::warning('⚠️ Order webhook missing order ID', ['data' => $data]);
            return $this->errorResponse('Missing order ID');
        }

        Log::info("📋 Ginee order event received", [
            'ginee_order_id' => $gineeOrderId,
            'order_status' => $orderStatus,
            'action' => $action
        ]);

        // Proses item pesanan jika ada
        $items = $orderData['items'] ?? [];
        
        // Jika tidak ada item di webhook, coba ambil dari API Ginee
        // Cek berbagai status yang memerlukan pemrosesan item
        $requiresItems = in_array($orderStatus, ['PAID', 'READY_TO_SHIP', 'CANCELLED']);
        
        if (empty($items) && $requiresItems) {
            try {
                // Pastikan GineeClient telah terinject atau di-resolve dari container
                $gineeClient = app(\App\Services\GineeClient::class);
                Log::info("🔍 Mengambil detail pesanan dari Ginee API", ['order_id' => $gineeOrderId]);
                
                $orderDetails = $gineeClient->getOrderById($gineeOrderId);
                
                // Pastikan response valid dan memiliki data item
                if (isset($orderDetails['data'][0]['items']) && is_array($orderDetails['data'][0]['items'])) {
                    $items = $orderDetails['data'][0]['items'];
                    Log::info("✅ Berhasil mendapatkan detail pesanan", ['items_count' => count($items)]);
                } else {
                    Log::warning("⚠️ Tidak dapat menemukan item dalam response API", [
                        'order_id' => $gineeOrderId,
                        'response' => $orderDetails
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("❌ Error saat mengambil detail pesanan", [
                    'order_id' => $gineeOrderId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Proses item berdasarkan status
        if (!empty($items)) {
            if ($requiresItems) {
                $this->processOrderItems($items, $orderStatus, $action, $gineeOrderId);
            } else {
                Log::info("ℹ️ Status {$orderStatus} tidak memerlukan pemrosesan stok", ['order_id' => $gineeOrderId]);
            }
        } else {
            if ($requiresItems) {
                Log::warning("⚠️ Tidak ada item yang diproses untuk pesanan yang memerlukan pemrosesan", [
                    'order_id' => $gineeOrderId,
                    'status' => $orderStatus
                ]);
            } else {
                Log::info("ℹ️ Tidak ada item dan tidak memerlukan pemrosesan untuk status {$orderStatus}", ['order_id' => $gineeOrderId]);
            }
        }

        return $this->successResponse('Order event processed', [
            'ginee_order_id' => $gineeOrderId,
            'status' => $orderStatus,
            'items_processed' => count($items)
        ]);

    } catch (\Exception $e) {
        Log::error('❌ Order update failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        return $this->errorResponse('Order update failed: ' . $e->getMessage());
    }
}

/**
 * Process order items and update stock
 */
/**
 * Proses item-item dalam pesanan dan perbarui stok
 */
private function processOrderItems(array $items, string $orderStatus, string $action, ?string $gineeOrderId = null): void
{
    // Log informasi awal
    Log::info("🛒 Memproses item pesanan", [
        'order_id' => $gineeOrderId ?? 'unknown',
        'status' => $orderStatus,
        'action' => $action,
        'items_count' => count($items)
    ]);

    // Periksa jika order sudah diproses sebelumnya dengan memeriksa juga status
    if ($gineeOrderId) {
        $processKey = "ginee_order_{$gineeOrderId}_processed";
        $cachedProcess = cache()->get($processKey);
        
        // Jika sudah diproses sebelumnya, tapi status berbeda, dan status baru adalah CANCELLED,
        // tetap proses untuk mengembalikan stok
        if ($cachedProcess && isset($cachedProcess['status']) && 
            $cachedProcess['status'] !== $orderStatus && 
            $orderStatus === 'CANCELLED') {
            
            Log::info("🔄 Status order berubah ke CANCELLED, memproses pengembalian stok", [
                'order_id' => $gineeOrderId,
                'previous_status' => $cachedProcess['status'],
                'new_status' => $orderStatus
            ]);
            // Lanjutkan pemrosesan untuk mengembalikan stok
        } 
        elseif ($cachedProcess) {
            Log::info("⏭️ Pesanan sudah diproses sebelumnya, melewati: {$gineeOrderId}");
            return;
        }
    }

    // Jika tidak ada item, log dan return
    if (empty($items)) {
        Log::warning("⚠️ Tidak ada item untuk diproses", ['order_id' => $gineeOrderId ?? 'unknown']);
        return;
    }

    $updatedProducts = [];
    $failedUpdates = [];

    // Proses setiap item pesanan
    foreach ($items as $item) {
        // Ekstrak informasi penting dari item
        $sku = $item['masterSku'] ?? $item['sku'] ?? null;
        $quantity = intval($item['quantity'] ?? 1);
        $productName = $item['productName'] ?? 'Unknown Product';
        
        if (!$sku) {
            Log::warning("⚠️ Item pesanan tidak memiliki SKU", ['item' => $item]);
            continue;
        }

        // Cari produk berdasarkan SKU
        $product = Product::where('sku', $sku)->first();
        if (!$product) {
            Log::warning("📦 Produk tidak ditemukan untuk SKU: {$sku}");
            $failedUpdates[] = [
                'sku' => $sku,
                'quantity' => $quantity,
                'reason' => 'Product not found'
            ];
            continue;
        }

        try {
            DB::beginTransaction();
            
            $oldStock = $product->stock_quantity;
            
            // Logika berbeda berdasarkan status order
            if ($orderStatus === 'PAID' || $orderStatus === 'READY_TO_SHIP') {
                // Kurangi stok untuk pesanan baru
                $newStock = max(0, $oldStock - $quantity);
                
                if ($oldStock != $newStock) {
                    $product->stock_quantity = $newStock;
                    $product->ginee_last_sync = now();
                    $product->save();
                    
                    Log::info("📉 Stok dikurangi: {$sku} ({$oldStock} → {$newStock})", [
                        'product_id' => $product->id, 
                        'product_name' => $product->name,
                        'order_id' => $gineeOrderId ?? 'unknown'
                    ]);
                    
                    $updatedProducts[] = [
                        'sku' => $sku,
                        'product_id' => $product->id,
                        'old_stock' => $oldStock,
                        'new_stock' => $newStock,
                        'quantity_ordered' => $quantity,
                        'action' => 'decrease'
                    ];
                }
            } 
            elseif ($orderStatus === 'CANCELLED') {
                // Kembalikan stok untuk pesanan yang dibatalkan
                $newStock = $oldStock + $quantity;
                
                $product->stock_quantity = $newStock;
                $product->ginee_last_sync = now();
                $product->save();
                
                Log::info("📈 Stok dikembalikan: {$sku} ({$oldStock} → {$newStock})", [
                    'product_id' => $product->id, 
                    'product_name' => $product->name,
                    'order_id' => $gineeOrderId ?? 'unknown'
                ]);
                
                $updatedProducts[] = [
                    'sku' => $sku,
                    'product_id' => $product->id,
                    'old_stock' => $oldStock,
                    'new_stock' => $newStock,
                    'quantity_returned' => $quantity,
                    'action' => 'increase'
                ];
            }
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("❌ Gagal memperbarui stok untuk SKU: {$sku}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $failedUpdates[] = [
                'sku' => $sku,
                'quantity' => $quantity,
                'reason' => $e->getMessage()
            ];
        }
    }

    // Simpan record tentang pemrosesan order ini
    if ($gineeOrderId) {
        $processKey = "ginee_order_{$gineeOrderId}_processed";
        cache()->put($processKey, [
            'processed_at' => now()->toDateTimeString(),
            'status' => $orderStatus,
            'updated_products' => $updatedProducts,
            'failed_updates' => $failedUpdates
        ], now()->addHours(24));
    }

    // Log ringkasan hasil pemrosesan
    Log::info("✅ Pemrosesan item pesanan selesai", [
        'order_id' => $gineeOrderId ?? 'unknown',
        'status' => $orderStatus,
        'updated_products_count' => count($updatedProducts),
        'failed_updates_count' => count($failedUpdates)
    ]);
}

/**
 * Update local stock based on order status
 */
private function updateStockForOrderItem(string $sku, int $quantity, string $orderStatus, string $action): bool
{
    try {
        $product = Product::where('sku', $sku)->first();
        
        if (!$product) {
            Log::warning("📦 Product not found for stock update: {$sku}");
            return false;
        }

        $oldStock = $product->stock_quantity;
        $newStock = $oldStock;
        
        // Handle different order statuses
        if (in_array(strtoupper($orderStatus), ['PAID', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'COMPLETED']) && 
            strtoupper($action) === 'UPDATE') {
            // Kurangi stok saat pesanan dibayar atau diproses
            $newStock = $oldStock - $quantity;
            Log::info("📊 Reducing stock due to paid order: {$sku} ({$oldStock} → {$newStock})");
        } 
        else if (strtoupper($orderStatus) === 'CANCELLED' && strtoupper($action) === 'UPDATE') {
            // Kembalikan stok jika pesanan dibatalkan
            $newStock = $oldStock + $quantity;
            Log::info("📊 Restoring stock due to cancelled order: {$sku} ({$oldStock} → {$newStock})");
        }
        
        // Pastikan stok tidak negatif
        $newStock = max(0, $newStock);
        
        // Update stok jika ada perubahan
        if ($newStock !== $oldStock) {
            $product->stock_quantity = $newStock;
            $product->ginee_last_sync = now();
            $product->save();
            
            Log::info("✅ Stock updated for order: {$sku} ({$oldStock} → {$newStock})");
            return true;
        }
        
        return false;
        
    } catch (\Exception $e) {
        Log::error("❌ Failed to update stock: {$e->getMessage()}", [
            'sku' => $sku,
            'quantity' => $quantity,
            'status' => $orderStatus
        ]);
        
        return false;
    }
}

    /* ===================== HELPER METHODS ===================== */

    /**
     * Verify webhook signature (if configured)
     */
    private function verifyWebhookSignature(Request $request): bool
    {
        $secretKey = config('services.ginee.webhook_secret');
        if (!$secretKey) {
            return true; // Skip verification if no secret configured
        }

        $signature = $request->header('X-Ginee-Signature') ?? $request->header('X-Signature');
        if (!$signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secretKey);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Store webhook event for debugging and audit
     */
    private function storeWebhookEvent(Request $request, string $eventType): void
    {
        try {
            DB::table('webhook_events')->insert([
                'source' => 'ginee',
                'event_type' => $eventType,
                'payload' => json_encode($request->all()),
                'headers' => json_encode($request->headers->all()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'processed' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::warning('⚠️ Failed to store webhook event', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Standard success response
     */
    private function successResponse(string $message, array $data = []): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => now()
        ]);
    }

    /**
     * Standard error response
     */
    private function errorResponse(string $message, int $code = 400): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $message,
            'timestamp' => now()
        ], $code);
    }
}