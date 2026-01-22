<?php

// Debug script untuk melihat data order dan tracking response
// Jalankan: php debug_order_tracking.php

require 'vendor/autoload.php';

use App\Models\Order;
use App\Services\KomerceOrderService;

// Bootstrap Laravel
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 DEBUG: Order Tracking\n";
echo "========================\n\n";

// Get shipped orders
$orders = Order::where('status', 'shipped')
    ->whereNotNull('komerce_awb')
    ->orderBy('shipped_at', 'desc')
    ->get();

echo "📋 Found " . $orders->count() . " shipped orders with AWB\n\n";

$komerceService = app(KomerceOrderService::class);

foreach ($orders as $order) {
    echo "🔍 ORDER: {$order->order_number}\n";
    echo "   Status: {$order->status}\n";
    echo "   AWB (awb): " . ($order->awb ?? 'NULL') . "\n";
    echo "   AWB (komerce_awb): " . ($order->komerce_awb ?? 'NULL') . "\n";
    echo "   Shipped At: " . ($order->shipped_at ?? 'NULL') . "\n";
    
    // Try tracking with both AWB fields
    $awbToTest = $order->komerce_awb ?: $order->awb;
    
    if ($awbToTest) {
        echo "   Using AWB: {$awbToTest}\n";
        
        try {
            $result = $komerceService->trackShipment($awbToTest, 'JNE');
            
            echo "   API Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
            
            if ($result['success']) {
                $data = $result['data'];
                echo "   Last Status: " . ($data['last_status'] ?? 'NULL') . "\n";
                echo "   Status (lowercase): '" . strtolower($data['last_status'] ?? '') . "'\n";
                
                // Test the isDelivered logic manually
                $deliveredKeywords = [
                    'diterima', 'terkirim', 'sampai', 'tiba', 'selesai', 
                    'berhasil diterima', 'sudah sampai', 'sudah diterima',
                    'delivered', 'received', 'completed', 'finish', 'arrived',
                    'delivered to recipient', 'parcel delivered', 'successful delivery'
                ];
                
                $currentStatus = strtolower($data['last_status'] ?? '');
                $isDelivered = false;
                $matchedKeyword = null;
                
                foreach ($deliveredKeywords as $keyword) {
                    if (str_contains($currentStatus, strtolower($keyword))) {
                        $isDelivered = true;
                        $matchedKeyword = $keyword;
                        break;
                    }
                }
                
                echo "   Is Delivered: " . ($isDelivered ? 'YES' : 'NO') . "\n";
                if ($matchedKeyword) {
                    echo "   Matched Keyword: '{$matchedKeyword}'\n";
                }
                
                // Show recent history
                if (isset($data['history']) && is_array($data['history'])) {
                    echo "   History Count: " . count($data['history']) . "\n";
                    echo "   Latest Entry: " . ($data['history'][0]['desc'] ?? 'NULL') . "\n";
                    echo "   Latest Status: " . ($data['history'][0]['status'] ?? 'NULL') . "\n";
                }
            } else {
                echo "   Error: " . ($result['message'] ?? 'Unknown error') . "\n";
            }
            
        } catch (\Exception $e) {
            echo "   Exception: " . $e->getMessage() . "\n";
        }
    } else {
        echo "   No AWB available\n";
    }
    
    echo "\n" . str_repeat('-', 50) . "\n\n";
}

echo "✅ Debug completed\n";