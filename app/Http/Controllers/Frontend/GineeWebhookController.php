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
        $orderData = $data['order'] ?? $data['data'] ?? $data;
        $gineeOrderId = $orderData['orderId'] ?? $orderData['id'] ?? null;
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

        // Proses item dalam order jika ada
        if (isset($orderData['items']) && is_array($orderData['items'])) {
            $this->processOrderItems($orderData['items'], $orderStatus, $action);
        }

        return $this->successResponse('Order event processed', [
            'ginee_order_id' => $gineeOrderId,
            'status' => $orderStatus
        ]);

    } catch (\Exception $e) {
        Log::error('❌ Order update failed', ['error' => $e->getMessage()]);
        return $this->errorResponse('Order update failed: ' . $e->getMessage());
    }
}

/**
 * Process order items and update stock
 */
private function processOrderItems(array $items, string $orderStatus, string $action): void
{
    Log::info("🛒 Processing order items", [
        'count' => count($items),
        'status' => $orderStatus,
        'action' => $action
    ]);

    foreach ($items as $item) {
        $sku = $item['masterSku'] ?? $item['sku'] ?? null;
        $quantity = $item['quantity'] ?? 0;

        if (!$sku || $quantity <= 0) {
            Log::warning("⚠️ Invalid item data", ['item' => $item]);
            continue;
        }

        $this->updateStockForOrderItem($sku, $quantity, $orderStatus, $action);
    }
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