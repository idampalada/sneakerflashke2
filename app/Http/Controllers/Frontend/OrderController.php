<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\KomerceOrderService;
use App\Services\KomerceShippingService;


class OrderController extends Controller
{
    /**
     * Create order in Komerce system (NEW METHOD)
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
            $validator = $this->validateOrderRequest($request);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'VALIDATION_FAILED',
                    'message' => 'Please check your order data',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Build order data
            $orderData = $this->buildOrderData($request);

            // Create order in Komerce
            $result = $this->komerceOrderService->createOrder($orderData);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'message' => $result['message']
                ], 422);
            }

            // Update local order with Komerce order number
            $order = Order::find($request->local_order_id);
            if ($order) {
                $order->update([
                    'komerce_order_no' => $result['data']['order_no'],
                    'external_order_id' => $result['data']['order_no']
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'komerce_order_no' => $result['data']['order_no'],
                    'status' => $result['data']['status'],
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
     * Request pickup for Komerce orders (NEW METHOD)
     */
    public function requestKomercePickup(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'error' => 'Authentication required.'], 401);
        }

        try {
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
     * Generate Komerce shipping label (NEW METHOD)
     */
    public function generateKomerceLabel(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'error' => 'Authentication required.'], 401);
        }

        try {
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
     * Track Komerce shipment (NEW METHOD)
     */
    public function trackKomerceShipment(Request $request)
    {
        try {
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
     * Handle Komerce webhook (NEW METHOD)
     */
    public function handleKomerceWebhook(Request $request)
    {
        try {
            Log::info('📨 Komerce webhook received', [
                'payload' => $request->all()
            ]);

            $orderNo = $request->order_no;
            $status = $request->status;
            $cnote = $request->cnote;

            // Find local order by Komerce order number
            $order = Order::where('komerce_order_no', $orderNo)
                         ->orWhere('external_order_id', $orderNo)
                         ->first();

            if ($order) {
                // Update tracking number if provided
                if ($cnote) {
                    $order->update(['tracking_number' => $cnote]);
                }

                // Update order status based on Komerce status
                $localStatus = $this->mapKomerceStatusToLocal($status);
                if ($localStatus) {
                    $order->update(['status' => $localStatus]);
                }

                Log::info('✅ Order updated from Komerce webhook', [
                    'order_id' => $order->id,
                    'komerce_status' => $status,
                    'local_status' => $localStatus,
                    'tracking_number' => $cnote
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Komerce webhook error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'WEBHOOK_PROCESSING_ERROR'
            ], 500);
        }
    }

    // Helper methods untuk Komerce integration
    private function validateOrderRequest($request)
    {
        return \Illuminate\Support\Facades\Validator::make($request->all(), [
            'brand_name' => 'required|string|max:100',
            'receiver_name' => 'required|string|max:100',
            'receiver_phone' => 'required|string|max:20',
            'receiver_destination_id' => 'required|integer',
            'receiver_address' => 'required|string|max:255',
            'shipping_cost' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'order_details' => 'required|array|min:1'
        ]);
    }

    private function buildOrderData($request)
    {
        $user = Auth::user();
        
        return [
            'brand_name' => $request->brand_name ?? 'Sneakers Flash',
            'shipper_name' => 'Sneakers Flash Store',
            'shipper_phone' => '6281234567890',
            'shipper_email' => 'sneakersflash@gmail.com',
            'shipper_destination_id' => env('STORE_ORIGIN_DESTINATION_ID', 17485),
            'shipper_address' => 'Duri Kepa',
            'receiver_name' => $request->receiver_name,
            'receiver_phone' => $request->receiver_phone,
            'receiver_destination_id' => $request->receiver_destination_id,
            'receiver_address' => $request->receiver_address,
            'shipping' => 'JNE',
            'shipping_type' => $request->shipping_type ?? 'CTC',
            'shipping_cost' => $request->shipping_cost,
            'grand_total' => $request->grand_total,
            'payment_method' => $request->payment_method ?? 'BANK TRANSFER',
            'order_details' => $request->order_details
        ];
    }

    private function mapKomerceStatusToLocal($komerceStatus)
    {
        $statusMap = [
            'pending' => 'pending',
            'processed' => 'processing',
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled'
        ];

        return $statusMap[strtolower($komerceStatus)] ?? null;
    }

    /**
     * Display user's orders with auth check
     */
    public function index()
    {
        // Manual auth check
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login to view your orders.');
        }

        try {
            $user = Auth::user();
            
            $orders = Order::with(['orderItems.product'])
                ->where(function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhere('customer_email', $user->email);
                })
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            Log::info('Orders page accessed', [
                'user_id' => $user->id,
                'orders_count' => $orders->count(),
                'total_orders' => $orders->total()
            ]);

            return view('frontend.orders.index', compact('orders'));

        } catch (\Exception $e) {
            Log::error('Error loading orders page: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('home')->with('error', 'Failed to load orders. Please try again.');
        }
    }

    /**
     * Show specific order details with auth check
     */
    public function show($orderNumber)
    {
        // Manual auth check
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login to view order details.');
        }

        try {
            $user = Auth::user();
            
            $order = Order::with(['orderItems.product'])
                ->where('order_number', $orderNumber)
                ->where(function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhere('customer_email', $user->email);
                })
                ->first();

            if (!$order) {
                Log::warning('Order not found or access denied', [
                    'order_number' => $orderNumber,
                    'user_id' => $user->id,
                    'user_email' => $user->email
                ]);

                return redirect()->route('orders.index')->with('error', 'Order not found.');
            }

            Log::info('Order details accessed', [
                'order_number' => $orderNumber,
                'user_id' => $user->id,
                'order_status' => $order->status
            ]);

            return view('frontend.orders.show', compact('order'));

        } catch (\Exception $e) {
            Log::error('Error loading order details: ' . $e->getMessage(), [
                'order_number' => $orderNumber,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('orders.index')->with('error', 'Failed to load order details.');
        }
    }

    /**
     * UPDATED: Cancel pending order with auth check - single status
     */
    public function cancel($orderNumber)
    {
        // Manual auth check
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login to cancel orders.');
        }

        try {
            $user = Auth::user();
            
            $order = Order::with('orderItems.product')
                ->where('order_number', $orderNumber)
                ->where(function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhere('customer_email', $user->email);
                })
                ->first();

            if (!$order) {
                return back()->with('error', 'Order not found.');
            }

            // UPDATED: Check if order can be cancelled with single status
            if (!in_array($order->status, ['pending'])) {
                return back()->with('error', 'Cannot cancel this order. Current status: ' . ucfirst($order->status));
            }

            // Update order status to cancelled
            $order->update(['status' => 'cancelled']);

            // Restore stock for each item
            $restoredCount = 0;
            foreach ($order->orderItems as $item) {
                if ($item->product) {
                    $item->product->increment('stock_quantity', $item->quantity);
                    $restoredCount++;
                    
                    Log::info('Stock restored due to order cancellation', [
                        'order_number' => $orderNumber,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'quantity_restored' => $item->quantity,
                        'new_stock' => $item->product->fresh()->stock_quantity
                    ]);
                }
            }

            Log::info('Order cancelled successfully', [
                'order_number' => $orderNumber,
                'user_id' => $user->id,
                'items_restored' => $restoredCount,
                'total_amount' => $order->total_amount
            ]);

            return back()->with('success', "Order cancelled successfully. Stock restored for {$restoredCount} items.");

        } catch (\Exception $e) {
            Log::error('Error cancelling order: ' . $e->getMessage(), [
                'order_number' => $orderNumber,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Failed to cancel order. Please try again or contact support.');
        }
    }

    /**
     * UPDATED: Generate invoice - only for paid and delivered orders
     */
    public function invoice($orderNumber)
    {
        // Manual auth check
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login to view invoices.');
        }

        try {
            $user = Auth::user();
            
            $order = Order::with(['orderItems.product'])
                ->where('order_number', $orderNumber)
                ->where(function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhere('customer_email', $user->email);
                })
                ->first();

            if (!$order) {
                return back()->with('error', 'Order not found.');
            }

            // UPDATED: Only allow invoice for paid orders and beyond
            if (!in_array($order->status, ['paid', 'processing', 'shipped', 'delivered'])) {
                return back()->with('error', 'Invoice is only available for paid orders.');
            }

            Log::info('Invoice accessed', [
                'order_number' => $orderNumber,
                'user_id' => $user->id,
                'order_status' => $order->status,
                'total_amount' => $order->total_amount
            ]);

            // Return HTML view that can be printed as PDF by browser
            return view('frontend.orders.invoice', compact('order'));

        } catch (\Exception $e) {
            Log::error('Error generating invoice: ' . $e->getMessage(), [
                'order_number' => $orderNumber,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Failed to generate invoice. Please try again.');
        }
    }

    /**
     * UPDATED: Retry payment for pending orders - single status
     */
    public function retryPayment($orderNumber)
    {
        // Manual auth check for API
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required.'
            ], 401);
        }

        try {
            $user = Auth::user();
            
            $order = Order::with('orderItems.product')
                ->where('order_number', $orderNumber)
                ->where(function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhere('customer_email', $user->email);
                })
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'error' => 'Order not found.'
                ], 404);
            }

            // UPDATED: Validate order status for payment retry with single status
            if (in_array($order->status, ['paid', 'processing', 'shipped', 'delivered'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Order is already paid or processed.'
                ], 400);
            }

            if (!in_array($order->status, ['pending', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Payment retry not allowed. Status: ' . $order->status
                ], 400);
            }

            // Check if order is for online payment
            if ($order->payment_method === 'cod') {
                return response()->json([
                    'success' => false,
                    'error' => 'COD orders do not require online payment.'
                ], 400);
            }

            // Try to use existing snap token first if it's still valid
            if ($order->snap_token && $this->isSnapTokenValid($order->snap_token)) {
                Log::info('Using existing snap token for retry payment', [
                    'order_number' => $orderNumber,
                    'user_id' => $user->id
                ]);

                return response()->json([
                    'success' => true,
                    'snap_token' => $order->snap_token,
                    'order_number' => $order->order_number,
                    'message' => 'Using existing payment session'
                ]);
            }

            // Generate new snap token
            Log::info('Generating new snap token for retry payment', [
                'order_number' => $orderNumber,
                'user_id' => $user->id,
                'payment_method' => $order->payment_method
            ]);

            $snapToken = $this->createSnapTokenFromOrder($order);

            if ($snapToken) {
                // UPDATED: Update order with new snap token and reset to pending if cancelled
                $updateData = ['snap_token' => $snapToken];
                if ($order->status === 'cancelled') {
                    $updateData['status'] = 'pending';
                }
                
                $order->update($updateData);

                Log::info('New snap token created for retry payment', [
                    'order_number' => $orderNumber,
                    'user_id' => $user->id,
                    'token_length' => strlen($snapToken),
                    'status_reset' => $order->status === 'cancelled'
                ]);

                return response()->json([
                    'success' => true,
                    'snap_token' => $snapToken,
                    'order_number' => $order->order_number,
                    'message' => 'Payment session created successfully'
                ]);
            } else {
                throw new \Exception('Failed to create Midtrans snap token');
            }

        } catch (\Exception $e) {
            Log::error('Error retrying payment: ' . $e->getMessage(), [
                'order_number' => $orderNumber,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to create payment session. Please try again or contact support.'
            ], 500);
        }
    }

    /**
     * Create snap token from existing order
     */
    private function createSnapTokenFromOrder($order)
    {
        try {
            // Prepare item details
            $itemDetails = [];
            
            foreach ($order->orderItems as $item) {
                $itemDetails[] = [
                    'id' => (string) $item->product_id,
                    'price' => (int) $item->product_price,
                    'quantity' => (int) $item->quantity,
                    'name' => substr($item->product_name, 0, 50)
                ];
            }
            
            // Add shipping cost as item
            if ($order->shipping_cost > 0) {
                $itemDetails[] = [
                    'id' => 'shipping',
                    'price' => (int) $order->shipping_cost,
                    'quantity' => 1,
                    'name' => 'Shipping Cost'
                ];
            }
            
            // Add tax as item
            if ($order->tax_amount > 0) {
                $itemDetails[] = [
                    'id' => 'tax',
                    'price' => (int) $order->tax_amount,
                    'quantity' => 1,
                    'name' => 'Tax (PPN 11%)'
                ];
            }

            // Prepare customer details
            $customerName = explode(' ', $order->customer_name, 2);
            $firstName = $customerName[0] ?? 'Customer';
            $lastName = $customerName[1] ?? '';

            $customerDetails = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $order->customer_email,
                'phone' => $order->customer_phone
            ];

            // Add billing address if available
            if ($order->shipping_address) {
                $customerDetails['billing_address'] = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'address' => is_array($order->shipping_address) ? 
                                implode(', ', $order->shipping_address) : 
                                $order->shipping_address,
                    'city' => $order->shipping_destination_label ?? 'Unknown',
                    'postal_code' => $order->shipping_postal_code ?? '00000',
                    'phone' => $order->customer_phone,
                    'country_code' => 'IDN'
                ];
                
                $customerDetails['shipping_address'] = $customerDetails['billing_address'];
            }

            // Prepare Midtrans payload
            $midtransPayload = [
                'transaction_details' => [
                    'order_id' => $order->order_number,
                    'gross_amount' => (int) $order->total_amount
                ],
                'customer_details' => $customerDetails,
                'item_details' => $itemDetails
            ];

            // Create snap token via Midtrans service
            $response = $this->midtransService->createSnapToken($midtransPayload);

            if ($response && isset($response['token'])) {
                return $response['token'];
            }

            Log::warning('Midtrans service returned no token', [
                'order_number' => $order->order_number,
                'response' => $response
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Error creating snap token from order: ' . $e->getMessage(), [
                'order_number' => $order->order_number,
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Check if snap token is still valid (basic check)
     */
    private function isSnapTokenValid($snapToken)
    {
        // Basic validation - just check if token exists and is not empty
        // In production, you might want to validate against Midtrans API
        return !empty($snapToken) && strlen($snapToken) > 10;
    }

    /**
     * Track order status - redirect to show page
     */
    public function track($orderNumber)
    {
        // Manual auth check
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Please login to track orders.');
        }

        try {
            $user = Auth::user();
            
            $order = Order::with(['orderItems.product'])
                ->where('order_number', $orderNumber)
                ->where(function($query) use ($user) {
                    $query->where('user_id', $user->id)
                          ->orWhere('customer_email', $user->email);
                })
                ->first();

            if (!$order) {
                return redirect()->route('orders.index')->with('error', 'Order not found.');
            }

            Log::info('Order tracking accessed', [
                'order_number' => $orderNumber,
                'user_id' => $user->id,
                'current_status' => $order->status,
                'tracking_number' => $order->tracking_number
            ]);

            // Use the existing order show view with tracking information
            return view('frontend.orders.show', compact('order'));

        } catch (\Exception $e) {
            Log::error('Error accessing order tracking: ' . $e->getMessage(), [
                'order_number' => $orderNumber,
                'user_id' => Auth::id()
            ]);

            return redirect()->route('orders.index')->with('error', 'Failed to load tracking information.');
        }
    }

    /**
     * UPDATED: Get order statistics for user dashboard - single status
     */
    public function getOrderStats()
    {
        // Manual auth check for API
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required.'
            ], 401);
        }

        try {
            $user = Auth::user();
            
            $stats = [
                'total_orders' => Order::where('user_id', $user->id)->count(),
                'pending_orders' => Order::where('user_id', $user->id)->where('status', 'pending')->count(),
                'paid_orders' => Order::where('user_id', $user->id)->where('status', 'paid')->count(),
                'processing_orders' => Order::where('user_id', $user->id)->where('status', 'processing')->count(),
                'shipped_orders' => Order::where('user_id', $user->id)->where('status', 'shipped')->count(),
                'delivered_orders' => Order::where('user_id', $user->id)->where('status', 'delivered')->count(),
                'cancelled_orders' => Order::where('user_id', $user->id)->where('status', 'cancelled')->count(),
                'completed_orders' => Order::where('user_id', $user->id)->where('status', 'delivered')->count(), // Delivered = completed
                'total_spent' => Order::where('user_id', $user->id)
                                     ->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])
                                     ->sum('total_amount')
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting order stats: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get order statistics'
            ], 500);
        }
    }

    /**
     * UPDATED: Get payment status for specific order - single status
     */
    public function getPaymentStatus($orderNumber)
    {
        // Manual auth check for API
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
     * Create order in Komerce system (NEW METHOD)
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
            $validator = $this->validateOrderRequest($request);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'VALIDATION_FAILED',
                    'message' => 'Please check your order data',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Build order data
            $orderData = $this->buildOrderData($request);

            // Create order in Komerce
            $result = $this->komerceOrderService->createOrder($orderData);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'message' => $result['message']
                ], 422);
            }

            // Update local order with Komerce order number
            $order = Order::find($request->local_order_id);
            if ($order) {
                $order->update([
                    'komerce_order_no' => $result['data']['order_no'],
                    'external_order_id' => $result['data']['order_no']
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'komerce_order_no' => $result['data']['order_no'],
                    'status' => $result['data']['status'],
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
     * Request pickup for Komerce orders (NEW METHOD)
     */
    public function requestKomercePickup(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'error' => 'Authentication required.'], 401);
        }

        try {
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
     * Generate Komerce shipping label (NEW METHOD)
     */
    public function generateKomerceLabel(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'error' => 'Authentication required.'], 401);
        }

        try {
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
     * Track Komerce shipment (NEW METHOD)
     */
    public function trackKomerceShipment(Request $request)
    {
        try {
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
     * Handle Komerce webhook (NEW METHOD)
     */
    public function handleKomerceWebhook(Request $request)
    {
        try {
            Log::info('📨 Komerce webhook received', [
                'payload' => $request->all()
            ]);

            $orderNo = $request->order_no;
            $status = $request->status;
            $cnote = $request->cnote;

            // Find local order by Komerce order number
            $order = Order::where('komerce_order_no', $orderNo)
                         ->orWhere('external_order_id', $orderNo)
                         ->first();

            if ($order) {
                // Update tracking number if provided
                if ($cnote) {
                    $order->update(['tracking_number' => $cnote]);
                }

                // Update order status based on Komerce status
                $localStatus = $this->mapKomerceStatusToLocal($status);
                if ($localStatus) {
                    $order->update(['status' => $localStatus]);
                }

                Log::info('✅ Order updated from Komerce webhook', [
                    'order_id' => $order->id,
                    'komerce_status' => $status,
                    'local_status' => $localStatus,
                    'tracking_number' => $cnote
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Webhook processed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Komerce webhook error', [
                'error' => $e->getMessage(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'WEBHOOK_PROCESSING_ERROR'
            ], 500);
        }
    }

    // Helper methods untuk Komerce integration
    private function validateOrderRequest($request)
    {
        return \Illuminate\Support\Facades\Validator::make($request->all(), [
            'brand_name' => 'required|string|max:100',
            'receiver_name' => 'required|string|max:100',
            'receiver_phone' => 'required|string|max:20',
            'receiver_destination_id' => 'required|integer',
            'receiver_address' => 'required|string|max:255',
            'shipping_cost' => 'required|numeric|min:0',
            'grand_total' => 'required|numeric|min:0',
            'order_details' => 'required|array|min:1'
        ]);
    }

    private function buildOrderData($request)
    {
        $user = Auth::user();
        
        return [
            'brand_name' => $request->brand_name ?? 'Sneakers Flash',
            'shipper_name' => 'Sneakers Flash Store',
            'shipper_phone' => '6281234567890',
            'shipper_email' => 'sneakersflash@gmail.com',
            'shipper_destination_id' => env('STORE_ORIGIN_DESTINATION_ID', 17485),
            'shipper_address' => 'Duri Kepa',
            'receiver_name' => $request->receiver_name,
            'receiver_phone' => $request->receiver_phone,
            'receiver_destination_id' => $request->receiver_destination_id,
            'receiver_address' => $request->receiver_address,
            'shipping' => 'JNE',
            'shipping_type' => $request->shipping_type ?? 'CTC',
            'shipping_cost' => $request->shipping_cost,
            'grand_total' => $request->grand_total,
            'payment_method' => $request->payment_method ?? 'BANK TRANSFER',
            'order_details' => $request->order_details
        ];
    }

    private function mapKomerceStatusToLocal($komerceStatus)
    {
        $statusMap = [
            'pending' => 'pending',
            'processed' => 'processing',
            'shipped' => 'shipped',
            'delivered' => 'delivered',
            'cancelled' => 'cancelled'
        ];

        return $statusMap[strtolower($komerceStatus)] ?? null;
    }
}