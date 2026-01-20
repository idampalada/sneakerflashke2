<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\KomerceOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrderTrackingController extends Controller
{
    protected $komerceOrderService;

    public function __construct(KomerceOrderService $komerceOrderService)
    {
        $this->komerceOrderService = $komerceOrderService;
    }

    /**
     * AJAX endpoint for live tracking in popup
     */
    public function trackOrder(Request $request, $orderNumber)
    {
        try {
            // Find order by order number
            $order = Order::where('order_number', $orderNumber)->first();
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'error' => 'Order not found'
                ], 404);
            }

            // Check if user owns this order (for logged in users)
            if (Auth::check() && $order->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Unauthorized access'
                ], 403);
            }

            // Get AWB number from multiple sources with priority
            $awbNumber = $this->getAwbNumber($order);
            
            if (!$awbNumber) {
                return response()->json([
                    'success' => false,
                    'error' => 'Tracking number not available yet. Package is being prepared for shipment.',
                    'order_status' => $order->status,
                    'order_info' => [
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'created_at' => $order->created_at->format('d M Y H:i'),
                        'total_amount' => number_format($order->total_amount, 0, ',', '.'),
                        'customer_name' => $order->customer_name
                    ]
                ]);
            }

            // Get live tracking data from Komerce API
            $result = $this->komerceOrderService->trackShipment($awbNumber, 'JNE');
            
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'order_info' => [
                            'order_number' => $order->order_number,
                            'status' => $order->status,
                            'created_at' => $order->created_at->format('d M Y H:i'),
                            'total_amount' => number_format($order->total_amount, 0, ',', '.'),
                            'customer_name' => $order->customer_name,
                            'awb_number' => $awbNumber
                        ],
                        'tracking' => [
                            'current_status' => $result['data']['status'] ?? $result['data']['last_status'] ?? 'Processing',
                            'last_status' => $result['data']['last_status'] ?? $result['data']['status'] ?? 'Processing',
                            'airway_bill' => $result['data']['airway_bill'] ?? $awbNumber,
                            'history' => $result['data']['history'] ?? [],
                            'updated_at' => now()->format('d M Y H:i')
                        ]
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['message'] ?? 'Failed to get tracking information',
                    'order_status' => $order->status,
                    'order_info' => [
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'created_at' => $order->created_at->format('d M Y H:i'),
                        'total_amount' => number_format($order->total_amount, 0, ',', '.'),
                        'customer_name' => $order->customer_name,
                        'awb_number' => $awbNumber
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Order tracking popup error', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Server error occurred while fetching tracking information'
            ], 500);
        }
    }

    /**
     * Get AWB number from multiple sources with priority
     */
    private function getAwbNumber($order)
    {
        // Priority 1: Database column komerce_awb
        if (!empty($order->komerce_awb)) {
            return $order->komerce_awb;
        }
        
        // Priority 2: Meta data pickup AWB
        $meta = json_decode($order->meta_data ?? '{}', true) ?? [];
        if (isset($meta['komerce_pickup']['awb']) && !empty($meta['komerce_pickup']['awb'])) {
            return $meta['komerce_pickup']['awb'];
        }
        
        // Priority 3: Meta data root AWB
        if (isset($meta['awb']) && !empty($meta['awb'])) {
            return $meta['awb'];
        }
        
        // Priority 4: Fallback to tracking_number
        if (!empty($order->tracking_number)) {
            return $order->tracking_number;
        }
        
        return null;
    }
}