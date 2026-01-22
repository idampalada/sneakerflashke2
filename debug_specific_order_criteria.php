<?php

// Debug khusus untuk order SF-20260115-P617LK
// Jalankan: php debug_specific_order_criteria.php

require 'vendor/autoload.php';

use App\Models\Order;

// Bootstrap Laravel
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🔍 DEBUG: Mengapa SF-20260115-P617LK tidak muncul dalam command\n";
echo "===========================================================\n\n";

// Get the specific order
$order = Order::where('order_number', 'SF-20260115-P617LK')->first();

if (!$order) {
    echo "❌ Order tidak ditemukan!\n";
    exit;
}

echo "📋 ORDER SF-20260115-P617LK:\n";
echo "   Status: '{$order->status}'\n";
echo "   komerce_awb: " . ($order->komerce_awb ?? 'NULL') . "\n";
echo "   shipped_at: " . ($order->shipped_at ? $order->shipped_at->format('Y-m-d H:i:s') : 'NULL') . "\n";
echo "   pickup_requested_at: " . ($order->pickup_requested_at ? $order->pickup_requested_at->format('Y-m-d H:i:s') : 'NULL') . "\n";

echo "\n🔍 TESTING QUERY CRITERIA:\n";

// Test criteria 1: status = 'shipped'
$pass1 = ($order->status === 'shipped');
echo "   1. status = 'shipped': " . ($pass1 ? '✅ PASS' : '❌ FAIL') . " (actual: '{$order->status}')\n";

// Test criteria 2: komerce_awb is not null
$pass2 = !is_null($order->komerce_awb) && $order->komerce_awb !== '';
echo "   2. komerce_awb NOT NULL: " . ($pass2 ? '✅ PASS' : '❌ FAIL') . " (value: " . ($order->komerce_awb ?? 'NULL') . ")\n";

// Test criteria 3: shipped_at conditions
$now = now();
echo "   Current time: " . $now->format('Y-m-d H:i:s') . "\n";

if ($order->shipped_at) {
    $cutoff2h = $now->copy()->subHours(2);
    $cutoff48h = $now->copy()->subHours(48);
    
    $pass3a = $order->shipped_at->lte($cutoff2h);  // Default command: shipped_at <= 2 hours ago
    $pass3b = $order->shipped_at->gte($cutoff48h); // Recent command: shipped_at >= 48 hours ago
    
    echo "   3a. shipped_at <= 2 hours ago (default): " . ($pass3a ? '✅ PASS' : '❌ FAIL') . "\n";
    echo "       shipped_at: " . $order->shipped_at->format('Y-m-d H:i:s') . "\n";
    echo "       cutoff (2h): " . $cutoff2h->format('Y-m-d H:i:s') . "\n";
    echo "       difference: " . $order->shipped_at->diffInHours($now, false) . " hours ago\n";
    
    echo "   3b. shipped_at >= 48 hours ago (--recent): " . ($pass3b ? '✅ PASS' : '❌ FAIL') . "\n";
    echo "       cutoff (48h): " . $cutoff48h->format('Y-m-d H:i:s') . "\n";
    
} else {
    echo "   3. shipped_at: ❌ FAIL - VALUE IS NULL!\n";
    $pass3a = false;
    $pass3b = false;
}

echo "\n🎯 FINAL RESULT:\n";
$shouldAppearDefault = $pass1 && $pass2 && $pass3a;
$shouldAppearRecent = $pass1 && $pass2 && $pass3b;

echo "   Should appear in DEFAULT command: " . ($shouldAppearDefault ? '✅ YES' : '❌ NO') . "\n";
echo "   Should appear in --recent command: " . ($shouldAppearRecent ? '✅ YES' : '❌ NO') . "\n";

if (!$shouldAppearDefault && !$shouldAppearRecent) {
    echo "\n💡 SOLUTION:\n";
    if (!$pass1) {
        echo "   - Status harus 'shipped'\n";
    }
    if (!$pass2) {
        echo "   - komerce_awb harus ada\n";
    }
    if (!$pass3a && !$pass3b) {
        if (!$order->shipped_at) {
            echo "   - shipped_at harus diset! (saat ini NULL)\n";
            echo "   - Gunakan: UPDATE orders SET shipped_at = NOW() - INTERVAL '3 hours' WHERE order_number = 'SF-20260115-P617LK';\n";
        } else {
            echo "   - shipped_at terlalu recent atau terlalu lama\n";
        }
    }
}

echo "\n🧪 MANUAL TEST QUERY:\n";
// Test actual queries
$queryDefault = Order::where('status', 'shipped')
    ->whereNotNull('komerce_awb')
    ->where('shipped_at', '<=', now()->subHours(2));

$queryRecent = Order::where('status', 'shipped')
    ->whereNotNull('komerce_awb')
    ->where('shipped_at', '>=', now()->subHours(48));

echo "   Default query count: " . $queryDefault->count() . "\n";
echo "   Recent query count: " . $queryRecent->count() . "\n";

$ordersDefault = $queryDefault->get(['order_number', 'komerce_awb', 'shipped_at']);
$ordersRecent = $queryRecent->get(['order_number', 'komerce_awb', 'shipped_at']);

echo "\n   Default query results:\n";
foreach ($ordersDefault as $o) {
    $shipped = $o->shipped_at ? $o->shipped_at->format('Y-m-d H:i:s') : 'NULL';
    echo "     - {$o->order_number} (AWB: {$o->komerce_awb}, Shipped: {$shipped})\n";
}

echo "\n   Recent query results:\n";
foreach ($ordersRecent as $o) {
    $shipped = $o->shipped_at ? $o->shipped_at->format('Y-m-d H:i:s') : 'NULL';
    echo "     - {$o->order_number} (AWB: {$o->komerce_awb}, Shipped: {$shipped})\n";
}

echo "\n✅ Debug completed\n";