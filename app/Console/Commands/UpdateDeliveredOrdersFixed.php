<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\KomerceOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateDeliveredOrdersFixed extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'orders:check-delivered-fixed {--recent : Only check recently shipped orders} {--dry-run : Show what would be updated without making changes} {--debug : Show detailed debug information}';

    /**
     * The console command description.
     */
    protected $description = 'Fixed version: Check shipped orders and auto-update to delivered based on tracking status';

    protected $komerceOrderService;

    public function __construct(KomerceOrderService $komerceOrderService)
    {
        parent::__construct();
        $this->komerceOrderService = $komerceOrderService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $recentOnly = $this->option('recent');
        $debug = $this->option('debug');
        
        $this->info('🚚 Checking shipped orders for delivery status... (FIXED VERSION - Direct DB Access)');

        // ✅ PERBAIKAN: Build query for shipped orders with komerce_awb
        $query = Order::where('status', 'shipped')
            ->whereNotNull('komerce_awb');
        
        if ($recentOnly) {
            // Recent: shipped dalam 48 jam terakhir
            $query->where('shipped_at', '>=', now()->subHours(48));
            $this->info('📅 Checking recent shipments (last 48 hours)');
        } else {
            // Skip very recent shipments (tracking mungkin belum update) - ATAU INCLUDE SEMUA UNTUK DEBUG
            $query->where('shipped_at', '<=', now()->subHours(2));
            $this->info('📅 Checking shipments older than 2 hours');
        }
        
        $shippedOrders = $query->orderBy('shipped_at', 'desc')->get();

        if ($shippedOrders->isEmpty()) {
            $this->info('✅ No shipped orders to check');
            
            // DEBUG: Show all shipped orders with komerce_awb regardless of time
            if ($debug) {
                $allShipped = Order::where('status', 'shipped')
                    ->whereNotNull('komerce_awb')
                    ->orderBy('shipped_at', 'desc')
                    ->get(['order_number', 'komerce_awb', 'shipped_at']);
                    
                $this->warn("🔍 DEBUG: All shipped orders with AWB:");
                foreach ($allShipped as $order) {
                    $shippedAt = $order->shipped_at ? $order->shipped_at->format('Y-m-d H:i:s') : 'NULL';
                    $this->info("   {$order->order_number} - AWB: {$order->komerce_awb} - Shipped: {$shippedAt}");
                }
            }
            
            return 0;
        }

        $this->info("🔍 Found {$shippedOrders->count()} shipped orders to check");

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual updates will be made');
        }

        $delivered = 0;
        $failed = 0;
        $unchanged = 0;

        foreach ($shippedOrders as $order) {
            try {
                // ✅ PERBAIKAN UTAMA: Use komerce_awb directly from database (bypass accessor)
                $awbNumber = $order->getAttributes()['komerce_awb'] ?? $order->komerce_awb;
                
                if (!$awbNumber) {
                    if ($debug) {
                        $this->warn("  ⚠️  Order {$order->order_number}: No AWB found");
                    }
                    $unchanged++;
                    continue;
                }

                if ($debug) {
                    $this->info("  🔍 Checking Order {$order->order_number} (AWB: {$awbNumber})");
                }

                // Get tracking data from Komerce API
                $result = $this->komerceOrderService->trackShipment($awbNumber, 'JNE');
                
                if (!$result['success']) {
                    if ($debug) {
                        $this->warn("  ❌ Tracking failed: " . ($result['message'] ?? 'Unknown error'));
                    }
                    $unchanged++;
                    continue;
                }

                $trackingData = $result['data'];
                $lastStatus = $trackingData['last_status'] ?? 'Unknown';
                
                if ($debug) {
                    $this->info("  📋 Tracking Status: '{$lastStatus}'");
                }

                $isDelivered = $this->isDelivered($trackingData, $debug);
                
                if ($debug) {
                    $this->info("  🎯 Is Delivered: " . ($isDelivered ? 'YES' : 'NO'));
                }
                
                if ($isDelivered) {
                    if ($dryRun) {
                        $this->info("  🔍 DRY RUN: Would mark {$order->order_number} as delivered");
                        $this->info("      Status: {$lastStatus}");
                        $delivered++;
                    } else {
                        // Update to delivered
                        $order->update([
                            'status' => 'delivered',
                            'delivered_at' => now(),
                            'notes' => ($order->notes ? $order->notes . "\n" : '') . 
                                      '[' . now()->format('Y-m-d H:i:s') . '] Auto-delivered: ' . $lastStatus
                        ]);
                        
                        Log::info('Order auto-delivered via scheduled job', [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'awb' => $awbNumber,
                            'tracking_status' => $lastStatus,
                            'delivered_at' => now()
                        ]);
                        
                        $this->info("  ✅ Updated {$order->order_number} to delivered");
                        $delivered++;
                    }
                } else {
                    $unchanged++;
                }
                
            } catch (\Exception $e) {
                Log::error('Error checking delivery status', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'awb' => $order->komerce_awb,
                    'error' => $e->getMessage()
                ]);
                
                if ($debug) {
                    $this->error("  💥 Exception: " . $e->getMessage());
                }
                $failed++;
            }
        }

        $this->newLine();
        $this->info('📊 SUMMARY:');
        $this->info("   Total Checked: {$shippedOrders->count()}");
        $this->info("   Delivered: {$delivered}");
        $this->info("   Unchanged: {$unchanged}");
        $this->info("   Failed: {$failed}");

        if ($dryRun) {
            $this->warn('🔍 DRY RUN COMPLETED - No actual changes made');
        } else {
            $this->info('✅ Auto-delivery check completed');
        }

        return 0;
    }

    /**
     * Check if tracking status indicates delivery (IMPROVED VERSION)
     */
    private function isDelivered($trackingData, $debug = false): bool
    {
        $deliveredKeywords = [
            // Indonesian
            'diterima', 'terkirim', 'sampai', 'tiba', 'selesai', 
            'berhasil diterima', 'sudah sampai', 'sudah diterima',
            
            // English
            'delivered', 'received', 'completed', 'finish', 'arrived',
            
            // JNE specific
            'delivered to recipient', 'parcel delivered', 'successful delivery'
        ];
        
        $currentStatus = strtolower($trackingData['last_status'] ?? '');
        
        if ($debug) {
            $this->info("    🔍 Checking status: '{$currentStatus}'");
        }
        
        // Check each keyword
        foreach ($deliveredKeywords as $keyword) {
            $keywordLower = strtolower($keyword);
            if (str_contains($currentStatus, $keywordLower)) {
                if ($debug) {
                    $this->info("    ✅ Matched keyword: '{$keyword}'");
                }
                return true;
            }
        }
        
        // Additional check for tracking history
        if (isset($trackingData['history']) && is_array($trackingData['history'])) {
            foreach ($trackingData['history'] as $history) {
                $historyStatus = strtolower($history['status'] ?? '');
                $historyDesc = strtolower($history['desc'] ?? '');
                
                foreach ($deliveredKeywords as $keyword) {
                    $keywordLower = strtolower($keyword);
                    if (str_contains($historyStatus, $keywordLower) || str_contains($historyDesc, $keywordLower)) {
                        if ($debug) {
                            $this->info("    ✅ Found in history: '{$keyword}' (Status: {$historyStatus}, Desc: {$historyDesc})");
                        }
                        return true;
                    }
                }
            }
        }
        
        if ($debug) {
            $this->warn("    ❌ No delivery keywords found");
        }
        
        return false;
    }
}