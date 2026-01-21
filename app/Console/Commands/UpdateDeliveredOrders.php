<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\KomerceOrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateDeliveredOrders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'orders:check-delivered {--recent : Only check recently shipped orders} {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Check shipped orders and auto-update to delivered based on tracking status';

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
        
        $this->info('🚚 Checking shipped orders for delivery status...');

        // Build query for shipped orders with AWB
        $query = Order::where('status', 'shipped')->whereNotNull('komerce_awb');
        
        if ($recentOnly) {
            // Recent: shipped dalam 48 jam terakhir
            $query->where('shipped_at', '>=', now()->subHours(48));
            $this->info('📅 Checking recent shipments (last 48 hours)');
        } else {
            // Skip very recent shipments (tracking mungkin belum update)
            $query->where('shipped_at', '<=', now()->subHours(2));
        }
        
        $shippedOrders = $query->orderBy('shipped_at', 'desc')->get();

        if ($shippedOrders->isEmpty()) {
            $this->info('✅ No shipped orders to check');
            return 0;
        }

        $this->info("🔍 Found {$shippedOrders->count()} shipped orders to check");

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No actual updates will be made');
        }

        $delivered = 0;
        $failed = 0;
        $unchanged = 0;

        $this->withProgressBar($shippedOrders, function ($order) use (&$delivered, &$failed, &$unchanged, $dryRun) {
            try {
                // Get tracking data from Komerce API
                $result = $this->komerceOrderService->trackShipment($order->awb, 'JNE');
                
                if (!$result['success']) {
                    $unchanged++;
                    return;
                }

                $trackingData = $result['data'];
                $isDelivered = $this->isDelivered($trackingData);
                
                if ($isDelivered) {
                    if ($dryRun) {
                        $this->newLine();
                        $this->info("  🔍 DRY RUN: Would mark {$order->order_number} as delivered");
                        $this->info("      Status: " . ($trackingData['last_status'] ?? 'N/A'));
                        $delivered++;
                    } else {
                        // Update to delivered
                        $order->update([
                            'status' => 'delivered',
                            'delivered_at' => now(),
                            'notes' => ($order->notes ? $order->notes . "\n" : '') . 
                                      '[' . now()->format('Y-m-d H:i:s') . '] Auto-delivered: ' . 
                                      ($trackingData['last_status'] ?? 'Package confirmed delivered')
                        ]);
                        
                        Log::info('Order auto-delivered via scheduled job', [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'awb' => $order->awb,
                            'tracking_status' => $trackingData['last_status'] ?? 'N/A',
                            'delivered_at' => now()
                        ]);
                        
                        $delivered++;
                    }
                } else {
                    $unchanged++;
                }
                
            } catch (\Exception $e) {
                Log::error('Error checking delivery status', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'awb' => $order->awb,
                    'error' => $e->getMessage()
                ]);
                $failed++;
            }
        });

        $this->newLine(2);
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
     * Check if tracking status indicates delivery
     */
    private function isDelivered($trackingData): bool
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
        
        // Check each keyword
        foreach ($deliveredKeywords as $keyword) {
            if (str_contains($currentStatus, strtolower($keyword))) {
                return true;
            }
        }
        
        // Additional check for tracking history
        if (isset($trackingData['history']) && is_array($trackingData['history'])) {
            foreach ($trackingData['history'] as $history) {
                $historyStatus = strtolower($history['status'] ?? '');
                foreach ($deliveredKeywords as $keyword) {
                    if (str_contains($historyStatus, strtolower($keyword))) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }
}