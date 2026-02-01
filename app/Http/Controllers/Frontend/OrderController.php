<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\KomerceOrderService;
use App\Services\KomerceShippingService;

class OrderController extends Controller
{
    protected $komerceOrderService;

    public function __construct()
    {
        $this->komerceOrderService = app(KomerceOrderService::class);
    }

    /**
     * Display orders for authenticated user
     */
    public function index()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        try {
            $user = Auth::user();
            $orders = Order::where('user_id', $user->id)
                ->with(['orderItems.product'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return view('frontend.orders.index', compact('orders'));

        } catch (\Exception $e) {
            Log::error('Error loading orders: ' . $e->getMessage());
            return back()->with('error', 'Failed to load orders.');
        }
    }

    /**
     * Show specific order details
     */
    public function show($orderNumber)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        try {
            $user = Auth::user();
            $order = Order::where('order_number', $orderNumber)
                ->where('user_id', $user->id)
                ->with(['orderItems.product'])
                ->firstOrFail();

            return view('frontend.orders.show', compact('order'));

        } catch (\Exception $e) {
            Log::error('Error loading order: ' . $e->getMessage());
            return redirect()->route('orders.index')->with('error', 'Order not found.');
        }
    }

    /**
     * Cancel order
     */
    public function cancel(Request $request, $orderNumber)
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'error' => 'Authentication required.'], 401);
        }

        try {
            $user = Auth::user();
            $order = Order::where('order_number', $orderNumber)
                ->where('user_id', $user->id)
                ->firstOrFail();

            if (!in_array($order->status, ['pending', 'processing'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order cannot be cancelled at this stage.'
                ], 422);
            }

            $order->update(['status' => 'cancelled']);

            Log::info('Order cancelled', [
                'order_number' => $orderNumber,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error('Error cancelling order: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order.'
            ], 500);
        }
    }

    /**
     * Generate invoice PDF
     */
    public function invoice($orderNumber)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        try {
            $user = Auth::user();
            $order = Order::where('order_number', $orderNumber)
                ->where('user_id', $user->id)
                ->with(['orderItems.product'])
                ->firstOrFail();

            $pdf = Pdf::loadView('frontend.orders.invoice', compact('order'))
                ->setPaper('A4', 'portrait');

            return $pdf->download('invoice-' . $order->order_number . '.pdf');

        } catch (\Exception $e) {
            Log::error('Error generating invoice: ' . $e->getMessage());
            return back()->with('error', 'Failed to generate invoice.');
        }
    }

    /**
     * Print order
     */
    public function print($orderNumber)
    {
        try {
            $order = Order::with(['orderItems.product'])
                ->where('order_number', $orderNumber)
                ->firstOrFail();

            Log::info('Admin PDF order print triggered', [
                'order_number' => $order->order_number,
                'origin' => 'admin-panel',
            ]);

            $pdf = Pdf::loadView('frontend.orders.print', compact('order'))
                ->setPaper('A5', 'portrait');

            return $pdf->download('order-' . $order->order_number . '.pdf');

        } catch (\Exception $e) {
            Log::error('Error printing order: ' . $e->getMessage(), [
                'order_number' => $orderNumber,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate print file.'
            ], 500);
        }
    }

    /**
     * Get payment status for specific order
     */
    public function getPaymentStatus($orderNumber)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required.'
            ], 401);
        }

        try {
            $user = Auth::user();
            
            $order = Order::where('order_number', $orderNumber)
                ->where(function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhere('customer_email', $user->email);
                })
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                'status_info' => $order->getPaymentStatusText()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting payment status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment status'
            ], 500);
        }
    }

    // ========================================
    // KOMERCE API INTEGRATION METHODS
    // ========================================

    /**
     * Create order in Komerce system (FIXED - NO DUPLICATION)
     */
    public function createKomerceOrder(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required.'
            ], 401);
        }

        try {
            // Validate request
            $validator = $this->validateKomerceOrderRequest($request);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'VALIDATION_FAILED',
                    'message' => 'Please check your order data',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Build order data untuk Komerce API
            $orderData = $this->buildKomerceOrderData($request);

            Log::info('🚀 Creating Komerce order', [
                'local_order_id' => $request->local_order_id,
                'receiver_destination_id' => $orderData['receiver_destination_id'],
                'grand_total' => $orderData['grand_total']
            ]);

            // Create order in Komerce via service
            $result = $this->komerceOrderService->createOrder($orderData);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'message' => $result['message']
                ], 422);
            }

            // Update local order dengan Komerce order number
            if ($request->local_order_id) {
                $order = Order::find($request->local_order_id);
                if ($order) {
                    $order->update([
                        'komerce_order_no' => $result['data']['order_no'],
                        'external_order_id' => $result['data']['order_no']
                    ]);
                    
                    Log::info('✅ Local order updated', [
                        'local_order_id' => $order->id,
                        'komerce_order_no' => $result['data']['order_no']
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'komerce_order_no' => $result['data']['order_no'],
                    'status' => $result['data']['status'] ?? 'created',
                    'message' => 'Order created in Komerce successfully'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Komerce order creation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'ORDER_CREATION_ERROR',
                'message' => 'Failed to create order in Komerce'
            ], 500);
        }
    }

    /**
     * Request pickup for Komerce orders
     */
    public function requestKomercePickup(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false, 
                'error' => 'Authentication required.'
            ], 401);
        }

        try {
            $validator = Validator::make($request->all(), [
                'pickup_date' => 'required|date|after_or_equal:today',
                'pickup_time' => 'required|date_format:H:i',
                'order_numbers' => 'required|array|min:1',
                'order_numbers.*' => 'required|string',
                'pickup_vehicle' => 'sometimes|string|in:Motor,Mobil'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'VALIDATION_FAILED',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->komerceOrderService->requestPickup(
                $request->pickup_date,
                $request->pickup_time,
                $request->order_numbers,
                $request->input('pickup_vehicle', 'Motor')
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('❌ Komerce pickup request error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'PICKUP_REQUEST_ERROR',
                'message' => 'Failed to request pickup'
            ], 500);
        }
    }

    /**
     * Generate Komerce shipping label
     */
    public function generateKomerceLabel(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false, 
                'error' => 'Authentication required.'
            ], 401);
        }

        try {
            $validator = Validator::make($request->all(), [
                'order_no' => 'required|string',
                'page_size' => 'sometimes|string|in:page_1,page_2'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'VALIDATION_FAILED',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->komerceOrderService->printLabel(
                $request->order_no,
                $request->input('page_size', 'page_2')
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('❌ Komerce label generation error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'LABEL_GENERATION_ERROR',
                'message' => 'Failed to generate label'
            ], 500);
        }
    }

    /**
     * Track Komerce shipment
     */
    public function trackKomerceShipment(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'airway_bill' => 'required|string',
                'shipping' => 'sometimes|string|in:JNE,SICEPAT,JNT,NINJA'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'VALIDATION_FAILED',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->komerceOrderService->trackShipment(
                $request->airway_bill,
                $request->input('shipping', 'JNE')
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('❌ Komerce tracking error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'TRACKING_ERROR',
                'message' => 'Failed to track shipment'
            ], 500);
        }
    }

    /**
     * Cancel Komerce order
     */
    public function cancelKomerceOrder(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false, 
                'error' => 'Authentication required.'
            ], 401);
        }

        try {
            $validator = Validator::make($request->all(), [
                'order_no' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'VALIDATION_FAILED',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->komerceOrderService->cancelOrder($request->order_no);

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('❌ Komerce order cancellation error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'ORDER_CANCELLATION_ERROR',
                'message' => 'Failed to cancel order'
            ], 500);
        }
    }

    /**
     * Handle Komerce webhook for order status updates
     */
    public function handleKomerceWebhook(Request $request)
    {
        try {
            Log::info('📞 Komerce webhook received', [
                'order_no' => $request->order_no,
                'cnote' => $request->cnote,
                'status' => $request->status,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            // Validate webhook data
            $validator = Validator::make($request->all(), [
                'order_no' => 'required|string',
                'cnote' => 'sometimes|string',
                'status' => 'required|string'
            ]);

            if ($validator->fails()) {
                Log::warning('❌ Invalid Komerce webhook data', [
                    'errors' => $validator->errors(),
                    'request_data' => $request->all()
                ]);

                return response()->json(['error' => 'Invalid webhook data'], 400);
            }

            // Find and update local order
            $order = Order::where('komerce_order_no', $request->order_no)
                ->orWhere('external_order_id', $request->order_no)
                ->first();

            if ($order) {
                $oldStatus = $order->status;
                $newStatus = $this->mapKomerceStatusToLocal($request->status);
                
                $order->update([
                    'status' => $newStatus,
                    'tracking_number' => $request->cnote ?: $order->tracking_number,
                ]);

                Log::info('✅ Order status updated via webhook', [
                    'order_number' => $order->order_number,
                    'komerce_order_no' => $request->order_no,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'tracking_number' => $request->cnote
                ]);
            } else {
                Log::warning('⚠️ Webhook for unknown order', [
                    'komerce_order_no' => $request->order_no
                ]);
            }

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('❌ Komerce webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }

    // ========================================
    // PRIVATE HELPER METHODS
    // ========================================

    /**
     * Validate Komerce order request
     */
    private function validateKomerceOrderRequest(Request $request)
    {
        return Validator::make($request->all(), [
            'local_order_id' => 'sometimes|integer|exists:orders,id',
            'brand_name' => 'sometimes|string|max:100',
            'shipper_name' => 'required|string|max:100',
            'shipper_phone' => 'required|string|max:20',
            'shipper_email' => 'required|email|max:100',
            'receiver_name' => 'required|string|max:100',
            'receiver_phone' => 'required|string|max:20',
            'receiver_destination_id' => 'required|integer',
            'receiver_address' => 'required|string|max:200',
            'shipping' => 'required|string|max:20',
            'shipping_type' => 'required|string|max:50',
            'shipping_cost' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'order_details' => 'required|array|min:1',
            'order_details.*.product_name' => 'required|string|max:100',
            'order_details.*.product_price' => 'required|numeric|min:0',
            'order_details.*.product_weight' => 'required|numeric|min:0',
            'order_details.*.qty' => 'required|integer|min:1',
            'order_details.*.subtotal' => 'required|numeric|min:0'
        ]);
    }

    /**
     * Build order data for Komerce API
     */
    private function buildKomerceOrderData(Request $request)
    {
        return [
            'order_date' => now()->format('Y-m-d H:i:s'),
            'brand_name' => $request->input('brand_name', 'Sneakers Flash'),
            'shipper_name' => $request->shipper_name,
            'shipper_phone' => $request->shipper_phone,
            'shipper_email' => $request->shipper_email,
            'shipper_destination_id' => (int) env('KOMERCE_SHIPPER_DESTINATION_ID', 17485),
            'shipper_address' => env('STORE_ADDRESS', 'Jl. Komp Graha Indah Green Ville Blok X No 34 RT 16 RW 9'),
            'receiver_name' => $request->receiver_name,
            'receiver_phone' => $request->receiver_phone,
            'receiver_destination_id' => (int) $request->receiver_destination_id,
            'receiver_address' => $request->receiver_address,
            'shipping' => $request->shipping,
            'shipping_type' => $request->shipping_type,
            'shipping_cost' => (int) $request->shipping_cost,
            'shipping_cashback' => 0,
            'service_fee' => 0,
            'payment_method' => $request->input('payment_method', 'BANK TRANSFER'),
            'additional_cost' => 0,
            'grand_total' => (int) $request->grand_total,
            'cod_value' => 0,
            'insurance_value' => 0,
            'order_details' => $request->order_details
        ];
    }

    /**
     * Map Komerce status to local order status
     */
    private function mapKomerceStatusToLocal($komerceStatus)
    {
        $statusMapping = [
            'Diterima' => 'processing',
            'Dalam Perjalanan' => 'shipped',
            'Terkirim' => 'delivered',
            'Dibatalkan' => 'cancelled',
            'Pending' => 'pending'
        ];

        return $statusMapping[$komerceStatus] ?? 'pending';
    }
}