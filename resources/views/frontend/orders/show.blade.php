{{-- File: resources/views/frontend/orders/show.blade.php - NO TAX VERSION --}}
@extends('layouts.app')

@section('title', 'Order #' . $order->order_number . ' - SneakerFlash')

@section('content')
<meta name="midtrans-client-key" content="{{ config('services.midtrans.client_key') }}">
<meta name="midtrans-production" content="{{ config('services.midtrans.is_production') ? 'true' : 'false' }}">

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <nav class="text-sm breadcrumbs mb-4">
                <ol class="list-none p-0 inline-flex">
                    <li class="flex items-center">
                        <a href="{{ route('orders.index') }}" class="text-blue-600 hover:text-blue-800">My Orders</a>
                        <svg class="fill-current w-3 h-3 mx-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 512">
                            <path d="M285.476 272.971L91.132 467.314c-9.373 9.373-24.569 9.373-33.941 0l-22.667-22.667c-9.357-9.357-9.375-24.522-.04-33.901L188.505 256 64.484 101.255c-9.335-9.379-9.317-24.544.04-33.901l22.667-22.667c9.373-9.373 24.569-9.373 33.941 0L285.475 239.03c9.373 9.372 9.373 24.568.001 33.941z"/>
                        </svg>
                    </li>
                    <li class="text-gray-500">Order #{{ $order->order_number }}</li>
                </ol>
            </nav>
            
            <h1 class="text-3xl font-bold text-gray-900">Order Details</h1>
        </div>

        <!-- Order Header with Single Status -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">
                        Order #{{ $order->order_number }}
                    </h2>
                    
                    <div class="flex flex-wrap gap-2 mb-3">
                        <!-- UPDATED: Single Status Badge -->
                        @if($order->status === 'pending')
                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                ⏳ Pending
                            </span>
                        @elseif($order->status === 'paid')
                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                ✅ Paid
                            </span>
                        @elseif($order->status === 'processing')
                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
                                🔄 Processing
                            </span>
                        @elseif($order->status === 'shipped')
                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-purple-100 text-purple-800">
                                🚚 Shipped
                            </span>
                        @elseif($order->status === 'delivered')
                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                📦 Delivered
                            </span>
                        @elseif($order->status === 'cancelled')
                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800">
                                ❌ Cancelled
                            </span>
                        @elseif($order->status === 'refund')
                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800">
                                💰 Refunded
                            </span>
                        @else
                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-gray-100 text-gray-800">
                                {{ ucfirst($order->status) }}
                            </span>
                        @endif
                    </div>
                    
                    <div class="text-sm text-gray-600 space-y-1">
                        <p><strong>Order Date:</strong> {{ $order->created_at->format('F j, Y \a\t g:i A') }}</p>
                        <p><strong>Payment Method:</strong> {{ strtoupper(str_replace('_', ' ', $order->payment_method)) }}</p>
                        @if($order->tracking_number)
                            <p><strong>Tracking Number:</strong> {{ $order->tracking_number }}</p>
                        @endif
                        <!-- UPDATED: Status Description -->
                        <p><strong>Status:</strong> {{ $order->getPaymentStatusText() }}</p>
                    </div>
                </div>
                
                <div class="mt-4 lg:mt-0 text-right">
                    <div class="text-3xl font-bold text-gray-900">
                        Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                    </div>
                    <div class="text-sm text-gray-600">
                        {{ $order->orderItems->count() }} item(s)
                    </div>
                </div>
            </div>
        </div>

        <!-- UPDATED: Action Buttons for Single Status -->
        @if($order->status === 'pending' && $order->payment_method !== 'cod')
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <h3 class="text-lg font-medium text-yellow-800">Payment Required</h3>
                        <p class="text-yellow-700">Complete your payment to process this order.</p>
                    </div>
                    <div class="ml-4">
                        <button onclick="retryPayment('{{ $order->order_number }}', '{{ $order->snap_token }}')" 
                                class="bg-yellow-600 text-white px-6 py-3 rounded-lg hover:bg-yellow-700 transition-colors font-medium">
                            💳 Pay Now
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- Order Progress Timeline -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Order Progress</h3>
            
            <div class="flex items-center justify-between">
                @php
                    $statusOrder = ['pending', 'paid', 'processing', 'shipped', 'delivered'];
                    $currentIndex = array_search($order->status, $statusOrder);
                    $isCancelled = $order->status === 'cancelled';
                    $isRefunded = $order->status === 'refund';
                @endphp
                
                @if($isCancelled)
                    <!-- Cancelled Status -->
                    <div class="flex items-center w-full">
                        <div class="flex items-center text-red-600">
                            <div class="flex items-center justify-center w-8 h-8 bg-red-100 rounded-full">
                                <span class="text-sm font-medium">❌</span>
                            </div>
                            <span class="ml-2 text-sm font-medium">Order Cancelled</span>
                        </div>
                    </div>
                @elseif($isRefunded)
                    <!-- Refunded Status -->
                    <div class="flex items-center w-full">
                        <div class="flex items-center text-gray-600">
                            <div class="flex items-center justify-center w-8 h-8 bg-gray-100 rounded-full">
                                <span class="text-sm font-medium">💰</span>
                            </div>
                            <span class="ml-2 text-sm font-medium">Order Refunded</span>
                        </div>
                    </div>
                @else
                    <!-- Normal Progress -->
                    @foreach(['pending' => '⏳', 'paid' => '✅', 'processing' => '🔄', 'shipped' => '🚚', 'delivered' => '📦'] as $status => $icon)
                        @php
                            $statusIndex = array_search($status, $statusOrder);
                            $isCompleted = $currentIndex !== false && $statusIndex <= $currentIndex;
                            $isCurrent = $order->status === $status;
                        @endphp
                        
                        <div class="flex items-center {{ !$loop->last ? 'flex-1' : '' }}">
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full {{ $isCompleted ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400' }}">
                                    <span class="text-sm">{{ $icon }}</span>
                                </div>
                                <span class="ml-2 text-sm font-medium {{ $isCompleted ? 'text-green-600' : 'text-gray-400' }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </div>
                            
                            @if(!$loop->last)
                                <div class="flex-1 h-0.5 ml-4 {{ $isCompleted && !$isCurrent ? 'bg-green-300' : 'bg-gray-200' }}"></div>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        <!-- Customer Information -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Customer Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Contact Details</h4>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p><strong>Name:</strong> {{ $order->customer_name }}</p>
                        <p><strong>Email:</strong> {{ $order->customer_email }}</p>
                        <p><strong>Phone:</strong> {{ $order->customer_phone }}</p>
                    </div>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Shipping Address</h4>
                    <div class="text-sm text-gray-600">
                        <p>{{ $order->getFullShippingAddress() }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Order Items</h3>
            <div class="space-y-4">
                @foreach($order->orderItems as $item)
                    <div class="flex items-center space-x-4 p-4 border border-gray-200 rounded-lg">
                        <div class="flex-shrink-0">
                            @if($item->product && $item->product->featured_image)
                                <img src="{{ $item->product->featured_image }}" 
                                     alt="{{ $item->product_name }}" 
                                     class="h-20 w-20 object-cover rounded-lg">
                            @else
                                <div class="h-20 w-20 bg-gray-200 rounded-lg flex items-center justify-center">
                                    <svg class="h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1">
                            <h4 class="font-medium text-gray-900">{{ $item->product_name }}</h4>
                            <div class="text-sm text-gray-600 mt-1">
                                <p>SKU: {{ $item->product_sku ?: 'N/A' }}</p>
                                <p>Unit Price: Rp {{ number_format($item->product_price, 0, ',', '.') }}</p>
                                <p>Quantity: {{ $item->quantity }}</p>
                            </div>
                            @if($item->product)
                                <a href="{{ route('products.show', $item->product->slug) }}" 
                                   class="text-blue-600 hover:text-blue-800 text-sm">
                                    View Product →
                                </a>
                            @endif
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-lg text-gray-900">
                                Rp {{ number_format($item->total_price, 0, ',', '.') }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Order Summary - REMOVED TAX -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Order Summary</h3>
            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-gray-600">Subtotal</span>
                    <span class="font-medium">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</span>
                </div>
                
                @if($order->shipping_cost > 0)
                    <div class="flex justify-between">
                        <span class="text-gray-600">Shipping Cost</span>
                        <span class="font-medium">Rp {{ number_format($order->shipping_cost, 0, ',', '.') }}</span>
                    </div>
                @endif
                
                <!-- REMOVED TAX DISPLAY -->
                
                @if($order->discount_amount > 0)
                    <div class="flex justify-between text-green-600">
                        <span>Discount</span>
                        <span class="font-medium">-Rp {{ number_format($order->discount_amount, 0, ',', '.') }}</span>
                    </div>
                @endif
                
                <hr class="my-3">
                
                <div class="flex justify-between text-lg font-bold">
                    <span>Total</span>
                    <span>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('orders.index') }}" 
               class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                ← Back to Orders
            </a>
            
            <!-- UPDATED: Show invoice for paid orders and beyond
            @if(in_array($order->status, ['paid', 'processing', 'shipped', 'delivered']))
                <a href="{{ route('orders.invoice', $order->order_number) }}" 
                   class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    📄 Download Invoice
                </a>
            @endif -->
            
            <!-- UPDATED: Cancel button only for pending orders -->
            @if($order->status === 'pending')
                <form action="{{ route('orders.cancel', $order->order_number) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" 
                            onclick="return confirm('Are you sure you want to cancel this order?')"
                            class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                        Cancel Order
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="payment-loading" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
        <p class="text-gray-700">Opening payment gateway...</p>
    </div>
</div>

<script>
// UPDATED: Retry Payment Function for single status
async function retryPayment(orderNumber, snapToken) {
    console.log('🔄 Retrying payment for order:', orderNumber);
    
    const loadingOverlay = document.getElementById('payment-loading');
    loadingOverlay.classList.remove('hidden');
    
    try {
        if (snapToken && snapToken !== 'null' && snapToken !== '') {
            console.log('💳 Using existing snap token');
            
            if (typeof window.snap === 'undefined') {
                await loadMidtransScript();
            }
            
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
                
                if (typeof window.snap === 'undefined') {
                    await loadMidtransScript();
                }
                
                window.snap.pay(data.snap_token, {
                    onSuccess: function(result) {
                        loadingOverlay.classList.add('hidden');
                        window.location.href = `/checkout/success/${orderNumber}?payment=success`;
                    },
                    onPending: function(result) {
                        loadingOverlay.classList.add('hidden');
                        window.location.reload();
                    },
                    onError: function(result) {
                        loadingOverlay.classList.add('hidden');
                        alert('Payment failed. Please try again.');
                    },
                    onClose: function() {
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
</script>

@endsection