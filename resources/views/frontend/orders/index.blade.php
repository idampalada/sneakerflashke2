{{-- File: resources/views/frontend/orders/index.blade.php --}}
@extends('layouts.app')

@section('title', 'My Orders - SneakerFlash')

@section('content')
<meta name="midtrans-client-key" content="{{ config('services.midtrans.client_key') }}">
<meta name="midtrans-production" content="{{ config('services.midtrans.is_production') ? 'true' : 'false' }}">

<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">My Orders</h1>
        
        @if($orders->isEmpty())
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <div class="mb-4">
                    <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-medium text-gray-900 mb-2">No Orders Yet</h3>
                <p class="text-gray-600 mb-6">You haven't placed any orders yet.</p>
                <a href="{{ route('products.index') }}" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                    Start Shopping
                </a>
            </div>
        @else
            <div class="space-y-6">
                @foreach($orders as $order)
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <!-- Order Header -->
                        <div class="p-6 border-b border-gray-200">
                            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                                <div class="mb-4 lg:mb-0">
                                    <div class="flex items-center space-x-4">
                                        <h3 class="text-lg font-semibold text-gray-900">
                                            Order #{{ $order->order_number }}
                                        </h3>
                                        
                                        <!-- UPDATED: Single Status Badge -->
                                        @if($order->status === 'pending')
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                ⏳ Pending
                                            </span>
                                        @elseif($order->status === 'paid')
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                ✅ Paid
                                            </span>
                                        @elseif($order->status === 'processing')
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                🔄 Processing
                                            </span>
                                        @elseif($order->status === 'shipped')
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                                                🚚 Shipped
                                            </span>
                                        @elseif($order->status === 'delivered')
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                📦 Delivered
                                            </span>
                                        @elseif($order->status === 'cancelled')
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                ❌ Cancelled
                                            </span>
                                        @elseif($order->status === 'refund')
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                💰 Refunded
                                            </span>
                                        @else
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                {{ ucfirst($order->status) }}
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <div class="mt-2 text-sm text-gray-600">
                                        <p>Placed on {{ $order->created_at->format('F j, Y \a\t g:i A') }}</p>
                                        <p>Payment Method: {{ strtoupper(str_replace('_', ' ', $order->payment_method)) }}</p>
                                        <!-- UPDATED: Status description -->
                                        <p class="text-xs text-gray-500 mt-1">{{ $order->getPaymentStatusText() }}</p>
                                    </div>
                                </div>
                                
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-gray-900">
                                        Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        {{ $order->orderItems->count() }} item(s)
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Items -->
                        <div class="p-6 border-b border-gray-200">
                            <div class="space-y-4">
                                @foreach($order->orderItems as $item)
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-shrink-0">
                                            @if($item->product && $item->product->featured_image)
                                                <img src="{{ $item->product->featured_image }}" alt="{{ $item->product_name }}" class="h-16 w-16 object-cover rounded">
                                            @else
                                                <div class="h-16 w-16 bg-gray-200 rounded flex items-center justify-center">
                                                    <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-medium text-gray-900">{{ $item->product_name }}</h4>
                                            <p class="text-sm text-gray-600">
                                                Qty: {{ $item->quantity }} × Rp {{ number_format($item->product_price, 0, ',', '.') }}
                                            </p>
                                            @if($item->product_sku)
                                                <p class="text-xs text-gray-500">SKU: {{ $item->product_sku }}</p>
                                            @endif
                                        </div>
                                        <div class="text-right">
                                            <div class="font-medium text-gray-900">
                                                Rp {{ number_format($item->total_price, 0, ',', '.') }}
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        
                        <!-- Order Summary -->
                        <div class="p-6 bg-gray-50">
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>Subtotal</span>
                                    <span>Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                                </div>
                                @if($order->shipping_cost > 0)
                                    <div class="flex justify-between text-sm">
                                        <span>Shipping</span>
                                        <span>Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                                @if($order->tax_amount > 0)
                                    <div class="flex justify-between text-sm">
                                        <span>Tax (11%)</span>
                                        <span>Rp {{ number_format($order->tax_amount, 0, ',', '.') }}</span>
                                    </div>
                                @endif
                                <hr>
                                <div class="flex justify-between font-semibold">
                                    <span>Total</span>
                                    <span>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- UPDATED: Action Buttons for Single Status -->
                        <div class="p-6 bg-white border-t border-gray-200">
                            <div class="flex flex-wrap gap-3">
                                <!-- View Details -->
                                <a href="{{ route('orders.show', $order->order_number) }}" 
                                   class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                    View Details
                                </a>
                                
                                <!-- UPDATED: Download Invoice (for paid orders and beyond)
                                @if(in_array($order->status, ['paid', 'processing', 'shipped', 'delivered']))
                                    <a href="{{ route('orders.invoice', $order->order_number) }}" 
                                       class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors">
                                        📄 Download Invoice
                                    </a>
                                @endif -->
                                
                                <!-- UPDATED: Retry Payment (if pending and online payment) -->
                                @if($order->status === 'pending' && $order->payment_method !== 'cod')
                                    <button onclick="retryPayment('{{ $order->order_number }}', '{{ $order->snap_token }}')" 
                                            class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                        💳 Pay Now
                                    </button>
                                @endif
                                
                                <!-- UPDATED: Cancel Order (only for pending) -->
                                @if($order->status === 'pending')
                                    <form action="{{ route('orders.cancel', $order->order_number) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" 
                                                onclick="return confirm('Are you sure you want to cancel this order?')"
                                                class="px-4 py-2 bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors">
                                            Cancel Order
                                        </button>
                                    </form>
                                @endif
                                
                                <!-- Track Order (if shipped) -->
@if($order->awb)
    <a href="#"
       class="px-4 py-2 bg-purple-100 text-purple-700 rounded-lg hover:bg-purple-200 transition-colors">
        📦 Track Package ({{ $order->awb }})
    </a>
@endif

                                
                                <!-- UPDATED: Order Actions Based on Status -->
                                @if($order->status === 'delivered')
                                    <button class="px-4 py-2 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200 transition-colors"
                                            onclick="alert('Thank you for your purchase! Please rate your experience.')">
                                        ⭐ Rate Order
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <!-- Pagination -->
            @if($orders->hasPages())
                <div class="mt-8">
                    {{ $orders->links() }}
                </div>
            @endif
        @endif
    </div>
</div>

<!-- Payment Status Messages -->
@if(session('success'))
    <div id="success-message" class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg shadow-lg z-50">
        <div class="flex items-center">
            <span class="mr-2">✅</span>
            {{ session('success') }}
        </div>
    </div>
@endif

@if(session('error'))
    <div id="error-message" class="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg shadow-lg z-50">
        <div class="flex items-center">
            <span class="mr-2">❌</span>
            {{ session('error') }}
        </div>
    </div>
@endif

<!-- Loading Overlay -->
<div id="payment-loading" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
        <p class="text-gray-700">Opening payment gateway...</p>
    </div>
</div>

<script>
// Auto hide messages
setTimeout(() => {
    const successMsg = document.getElementById('success-message');
    const errorMsg = document.getElementById('error-message');
    if (successMsg) successMsg.style.display = 'none';
    if (errorMsg) errorMsg.style.display = 'none';
}, 5000);

// UPDATED: Retry Payment Function for Single Status
async function retryPayment(orderNumber, snapToken) {
    console.log('🔄 Retrying payment for order:', orderNumber);
    
    const loadingOverlay = document.getElementById('payment-loading');
    loadingOverlay.classList.remove('hidden');
    
    try {
        // Check if we have a snap token
        if (snapToken && snapToken !== 'null' && snapToken !== '') {
            console.log('💳 Using existing snap token');
            
            // Load Midtrans script if not loaded
            if (typeof window.snap === 'undefined') {
                await loadMidtransScript();
            }
            
            // Open Midtrans payment
            window.snap.pay(snapToken, {
                onSuccess: function(result) {
                    console.log('✅ Payment successful:', result);
                    loadingOverlay.classList.add('hidden');
                    window.location.href = `/checkout/success/${orderNumber}?payment=success`;
                },
                onPending: function(result) {
                    console.log('⏳ Payment pending:', result);
                    loadingOverlay.classList.add('hidden');
                    window.location.reload();
                },
                onError: function(result) {
                    console.error('❌ Payment error:', result);
                    loadingOverlay.classList.add('hidden');
                    alert('Payment failed. Please try again.');
                },
                onClose: function() {
                    console.log('🔒 Payment popup closed');
                    loadingOverlay.classList.add('hidden');
                }
            });
        } else {
            console.log('🔄 Generating new snap token');
            
            // Get new snap token
            const response = await fetch(`/api/payment/retry/${orderNumber}`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                }
            });
            
            const data = await response.json();
            
            if (data.success && data.snap_token) {
                console.log('✅ New snap token received');
                
                // Load Midtrans script if not loaded
                if (typeof window.snap === 'undefined') {
                    await loadMidtransScript();
                }
                
                // Open payment with new token
                window.snap.pay(data.snap_token, {
                    onSuccess: function(result) {
                        console.log('✅ Payment successful:', result);
                        loadingOverlay.classList.add('hidden');
                        window.location.href = `/checkout/success/${orderNumber}?payment=success`;
                    },
                    onPending: function(result) {
                        console.log('⏳ Payment pending:', result);
                        loadingOverlay.classList.add('hidden');
                        window.location.reload();
                    },
                    onError: function(result) {
                        console.error('❌ Payment error:', result);
                        loadingOverlay.classList.add('hidden');
                        alert('Payment failed. Please try again.');
                    },
                    onClose: function() {
                        console.log('🔒 Payment popup closed');
                        loadingOverlay.classList.add('hidden');
                    }
                });
            } else {
                throw new Error(data.error || 'Failed to create payment session');
            }
        }
    } catch (error) {
        console.error('❌ Error retrying payment:', error);
        loadingOverlay.classList.add('hidden');
        alert('Failed to open payment. Please try again.');
    }
}

// Load Midtrans Script
function loadMidtransScript() {
    return new Promise((resolve, reject) => {
        if (window.snap) {
            resolve();
            return;
        }

        const clientKey = document.querySelector('meta[name="midtrans-client-key"]')?.getAttribute('content');
        const isProduction = document.querySelector('meta[name="midtrans-production"]')?.getAttribute('content') === 'true';

        if (!clientKey) {
            reject(new Error('Midtrans client key not found'));
            return;
        }

        const script = document.createElement('script');
        script.src = isProduction 
            ? 'https://app.midtrans.com/snap/snap.js'
            : 'https://app.sandbox.midtrans.com/snap/snap.js';
        script.setAttribute('data-client-key', clientKey);

        script.onload = () => {
            setTimeout(() => {
                if (window.snap) {
                    resolve();
                } else {
                    reject(new Error('Snap object not available'));
                }
            }, 500);
        };

        script.onerror = () => reject(new Error('Failed to load Midtrans script'));
        document.head.appendChild(script);
    });
}

// UPDATED: Check payment status for single status system
function checkPaymentStatus(orderNumber) {
    fetch(`/api/payment/status/${orderNumber}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        console.log('💳 Order status:', data);
        
        if (data.status === 'paid') {
            alert('✅ Payment confirmed!');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else if (data.status === 'cancelled') {
            alert('❌ Order cancelled');
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            alert(`⏳ Order status: ${data.status}`);
        }
    })
    .catch(error => {
        console.error('❌ Failed to check order status:', error);
    });
}
</script>

@endsection