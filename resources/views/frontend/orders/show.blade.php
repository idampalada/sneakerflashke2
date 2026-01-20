{{-- File: resources/views/frontend/orders/show.blade.php - CLEAN AUTO-SYNC VERSION --}}
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
                        <!-- UPDATED: Single Status Badge (auto-updated) -->
                        <span id="current-order-status" class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                            @if($order->status === 'pending')
                                ⏳ Pending
                            @elseif($order->status === 'paid')
                                ✅ Paid
                            @elseif($order->status === 'processing')
                                🔄 Processing
                            @elseif($order->status === 'shipped')
                                🚚 Shipped
                            @elseif($order->status === 'delivered')
                                📦 Delivered
                            @elseif($order->status === 'cancelled')
                                ❌ Cancelled
                            @elseif($order->status === 'refund')
                                💰 Refunded
                            @else
                                {{ ucfirst($order->status) }}
                            @endif
                        </span>

                        <!-- Live Tracking Button (Only visible button) -->
                        @if(in_array($order->status, ['paid', 'processing', 'shipped', 'delivered']))
                            <button onclick="openTrackingModal('{{ $order->order_number }}')"
                                    class="px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors cursor-pointer">
                                📍 Track Package
                            </button>
                        @endif
                    </div>
                    
                    <div class="text-sm text-gray-600 space-y-1">
                        <p><strong>Order Date:</strong> {{ $order->created_at->format('F j, Y \a\t g:i A') }}</p>
                        <p><strong>Payment Method:</strong> {{ strtoupper(str_replace('_', ' ', $order->payment_method)) }}</p>
                        @if($order->tracking_number)
                            <p><strong>Tracking Number:</strong> {{ $order->tracking_number }}</p>
                        @endif
                        <p><strong>Status:</strong> <span id="status-description">{{ $order->getPaymentStatusText() }}</span></p>
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

        <!-- UPDATED: Clean Order Progress Timeline (auto-updated in background) -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Order Progress</h3>
            
            <div class="flex items-center justify-between" id="order-progress-timeline">
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
                    <!-- Normal Progress (auto-updated) -->
                    @foreach(['pending' => '⏳', 'paid' => '✅', 'processing' => '🔄', 'shipped' => '🚚', 'delivered' => '📦'] as $status => $icon)
                        @php
                            $statusIndex = array_search($status, $statusOrder);
                            $isCompleted = $currentIndex !== false && $statusIndex <= $currentIndex;
                            $isCurrent = $order->status === $status;
                        @endphp
                        
                        <div class="flex items-center {{ !$loop->last ? 'flex-1' : '' }}" data-status="{{ $status }}">
                            <div class="flex items-center">
                                <div class="flex items-center justify-center w-8 h-8 rounded-full {{ $isCompleted ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400' }}" data-status-icon="{{ $status }}">
                                    <span class="text-sm">{{ $icon }}</span>
                                </div>
                                <span class="ml-2 text-sm font-medium {{ $isCompleted ? 'text-green-600' : 'text-gray-400' }}" data-status-label="{{ $status }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </div>
                            
                            @if(!$loop->last)
                                <div class="flex-1 h-0.5 ml-4 {{ $isCompleted && !$isCurrent ? 'bg-green-300' : 'bg-gray-200' }}" data-status-line="{{ $status }}"></div>
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
            
            <!-- Live Tracking Button (Only main action button) -->
            @if(in_array($order->status, ['paid', 'processing', 'shipped', 'delivered']))
                <button onclick="openTrackingModal('{{ $order->order_number }}')" 
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium">
                     Live Track Package
                </button>
            @endif
            
            <!-- Cancel button only for pending orders -->
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

<!-- Live Tracking Modal Popup -->
<div id="tracking-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">📦 Live Package Tracking</h3>
            <button onclick="closeTrackingModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <!-- Modal Content -->
        <div class="p-6 max-h-[70vh] overflow-y-auto">
            <!-- Loading State -->
            <div id="tracking-loading" class="text-center py-8">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p class="text-gray-600">Loading tracking information...</p>
            </div>
            
            <!-- Tracking Content -->
            <div id="tracking-content" class="hidden">
                <!-- Order Info -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p class="text-gray-600">Order Number</p>
                            <p class="font-semibold text-gray-900" id="modal-order-number">-</p>
                        </div>
                        <div>
                            <p class="text-gray-600">Customer</p>
                            <p class="font-semibold text-gray-900" id="modal-customer-name">-</p>
                        </div>
                        <div>
                            <p class="text-gray-600">AWB Number</p>
                            <p class="font-mono text-sm text-gray-900" id="modal-awb-number">-</p>
                        </div>
                        <div>
                            <p class="text-gray-600">Last Updated</p>
                            <p class="text-gray-900" id="modal-last-updated">-</p>
                        </div>
                    </div>
                </div>
                
                <!-- Current Status -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-green-100 text-green-800" id="modal-current-status-badge">
                        <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                        <span id="modal-current-status">Loading...</span>
                    </div>
                </div>
                
                <!-- Tracking History -->
                <div id="modal-tracking-history">
                    <!-- Will be populated by JavaScript -->
                </div>
            </div>
            
            <!-- Error State -->
            <div id="tracking-error" class="hidden text-center py-8">
                <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <h4 class="text-lg font-semibold text-gray-900 mb-2">Tracking Information</h4>
                <p class="text-gray-600 mb-4" id="tracking-error-message">Unable to load tracking data</p>
            </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="flex items-center justify-between p-6 border-t border-gray-200 bg-gray-50">
            <button onclick="refreshTracking()" 
                    id="modal-refresh-btn"
                    class="px-4 py-2 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors flex items-center space-x-2">
                <svg id="modal-refresh-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span>Refresh</span>
            </button>
            <button onclick="closeTrackingModal()" 
                    class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                Close
            </button>
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
// Global variables
let currentOrderNumber = '{{ $order->order_number }}';
let isRefreshing = false;
let autoUpdateInterval = null;
let lastTrackingData = null;

// Initialize page - SILENT AUTO-SYNC
document.addEventListener('DOMContentLoaded', function() {
    // Auto-load tracking data when page loads (for eligible orders)
    @if(in_array($order->status, ['paid', 'processing', 'shipped', 'delivered']))
        loadBackgroundTrackingData();
        
        // Start auto-update every 5 minutes (SILENT)
        autoUpdateInterval = setInterval(loadBackgroundTrackingData, 5 * 60 * 1000);
    @endif
});

// SILENT: Load tracking data in background
async function loadBackgroundTrackingData() {
    try {
        const response = await fetch(`/orders/${currentOrderNumber}/track`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.data) {
            lastTrackingData = data.data;
            silentUpdatePageProgress(data.data);
        }
        
    } catch (error) {
        // Silent fail - don't show errors to user
        console.log('Background sync:', error.message || 'Silent update');
    }
}

// SILENT: Update page elements without UI noise
function silentUpdatePageProgress(data) {
    // Determine progress based on tracking status
    if (data.tracking.current_status) {
        updateOrderProgressFromTracking(data.tracking.current_status);
        updateStatusBadge(data.tracking.current_status);
    }
}

// Update status badge based on tracking
function updateStatusBadge(trackingStatus) {
    const badge = document.getElementById('current-order-status');
    if (!badge) return;
    
    // Mapping tracking status to badge display
    const statusMappings = {
        'Pickup': { text: '🔄 Processing', class: 'bg-blue-100 text-blue-800' },
        'Dalam Perjalanan': { text: '🚚 Shipped', class: 'bg-purple-100 text-purple-800' }, 
        'Dalam perjalanan': { text: '🚚 Shipped', class: 'bg-purple-100 text-purple-800' },
        'On Transit': { text: '🚚 Shipped', class: 'bg-purple-100 text-purple-800' },
        'Tiba di Kota Tujuan': { text: '🚚 Shipped', class: 'bg-purple-100 text-purple-800' },
        'Delivered': { text: '📦 Delivered', class: 'bg-green-100 text-green-800' },
        'Terkirim': { text: '📦 Delivered', class: 'bg-green-100 text-green-800' }
    };
    
    // Find appropriate status mapping
    for (const [trackingKey, statusInfo] of Object.entries(statusMappings)) {
        if (trackingStatus.toLowerCase().includes(trackingKey.toLowerCase())) {
            badge.textContent = statusInfo.text;
            badge.className = `px-3 py-1 text-sm font-semibold rounded-full ${statusInfo.class}`;
            break;
        }
    }
}

// Update Order Progress timeline based on tracking status
function updateOrderProgressFromTracking(trackingStatus) {
    if (!trackingStatus) return;
    
    // Mapping tracking status to order progress
    const statusMappings = {
        'Pickup': 'processing',
        'Dalam Perjalanan': 'shipped', 
        'Dalam perjalanan': 'shipped',
        'On Transit': 'shipped',
        'Tiba di Kota Tujuan': 'shipped',
        'Delivered': 'delivered',
        'Terkirim': 'delivered',
        'Gagal Kirim': 'shipped',
        'Return': 'shipped'
    };
    
    // Find appropriate order status based on tracking
    let targetStatus = null;
    for (const [trackingKey, orderStatus] of Object.entries(statusMappings)) {
        if (trackingStatus.toLowerCase().includes(trackingKey.toLowerCase())) {
            targetStatus = orderStatus;
            break;
        }
    }
    
    // Intelligent fallback
    if (!targetStatus) {
        if (trackingStatus.toLowerCase().includes('pickup') || 
            trackingStatus.toLowerCase().includes('diambil')) {
            targetStatus = 'processing';
        } else if (trackingStatus.toLowerCase().includes('perjalanan') || 
                   trackingStatus.toLowerCase().includes('transit')) {
            targetStatus = 'shipped';
        } else if (trackingStatus.toLowerCase().includes('tiba') || 
                   trackingStatus.toLowerCase().includes('sampai')) {
            targetStatus = 'delivered';
        }
    }
    
    if (targetStatus) {
        updateProgressTimeline(targetStatus);
    }
}

// Update progress timeline UI
function updateProgressTimeline(newStatus) {
    const statusOrder = ['pending', 'paid', 'processing', 'shipped', 'delivered'];
    const newIndex = statusOrder.indexOf(newStatus);
    
    if (newIndex === -1) return;
    
    // Update each status in timeline
    statusOrder.forEach((status, index) => {
        const isCompleted = index <= newIndex;
        const statusIcon = document.querySelector(`[data-status-icon="${status}"]`);
        const statusLabel = document.querySelector(`[data-status-label="${status}"]`);
        const statusLine = document.querySelector(`[data-status-line="${status}"]`);
        
        if (statusIcon) {
            statusIcon.className = isCompleted 
                ? 'flex items-center justify-center w-8 h-8 rounded-full bg-green-100 text-green-600'
                : 'flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-gray-400';
        }
        
        if (statusLabel) {
            statusLabel.className = isCompleted
                ? 'ml-2 text-sm font-medium text-green-600'
                : 'ml-2 text-sm font-medium text-gray-400';
        }
        
        if (statusLine) {
            statusLine.className = isCompleted && index < newIndex
                ? 'flex-1 h-0.5 ml-4 bg-green-300'
                : 'flex-1 h-0.5 ml-4 bg-gray-200';
        }
    });
}

// Modal Functions
function openTrackingModal(orderNumber) {
    currentOrderNumber = orderNumber;
    document.getElementById('tracking-modal').classList.remove('hidden');
    loadTrackingData();
}

function closeTrackingModal() {
    document.getElementById('tracking-modal').classList.add('hidden');
    resetModalStates();
}

function resetModalStates() {
    document.getElementById('tracking-loading').classList.remove('hidden');
    document.getElementById('tracking-content').classList.add('hidden');
    document.getElementById('tracking-error').classList.add('hidden');
}

async function loadTrackingData() {
    if (!currentOrderNumber) return;
    
    try {
        resetModalStates();
        
        // Use cached data if available
        if (lastTrackingData) {
            displayTrackingData(lastTrackingData);
            return;
        }
        
        const response = await fetch(`/orders/${currentOrderNumber}/track`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        });
        
        const data = await response.json();
        
        if (data.success && data.data) {
            lastTrackingData = data.data;
            displayTrackingData(data.data);
        } else {
            displayTrackingError(data.error || 'Failed to load tracking information');
        }
        
    } catch (error) {
        console.error('Tracking load error:', error);
        displayTrackingError('Network error occurred. Please try again.');
    }
}

function displayTrackingData(data) {
    // Hide loading, show content
    document.getElementById('tracking-loading').classList.add('hidden');
    document.getElementById('tracking-error').classList.add('hidden');
    document.getElementById('tracking-content').classList.remove('hidden');
    
    // Populate order info
    document.getElementById('modal-order-number').textContent = data.order_info.order_number;
    document.getElementById('modal-customer-name').textContent = data.order_info.customer_name;
    document.getElementById('modal-awb-number').textContent = data.order_info.awb_number || 'Not available';
    document.getElementById('modal-last-updated').textContent = data.tracking.updated_at;
    
    // Update current status
    document.getElementById('modal-current-status').textContent = data.tracking.current_status;
    
    // Update tracking history
    const historyContainer = document.getElementById('modal-tracking-history');
    if (data.tracking.history && data.tracking.history.length > 0) {
        let historyHtml = '<h4 class="font-semibold text-gray-900 mb-4">📋 Package Journey</h4>';
        historyHtml += '<div class="space-y-4">';
        
        data.tracking.history.forEach((track, index) => {
            const isLatest = index === 0;
            historyHtml += `
                <div class="flex items-start space-x-4 p-3 rounded-lg ${isLatest ? 'bg-green-50 border border-green-200' : 'bg-gray-50'}">
                    <div class="flex-shrink-0 mt-1">
                        <div class="w-3 h-3 ${isLatest ? 'bg-green-500' : 'bg-gray-300'} rounded-full"></div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900">
                            ${track.description || track.status || 'Status Update'}
                        </p>
                        <div class="text-xs text-gray-500 mt-1">
                            📅 ${track.date || 'Date not available'}
                            ${track.code ? `<span class="ml-2 font-mono bg-gray-200 px-2 py-1 rounded">${track.code}</span>` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        historyHtml += '</div>';
        historyContainer.innerHTML = historyHtml;
    } else {
        historyContainer.innerHTML = `
            <div class="text-center py-6 text-gray-500">
                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
                <p class="text-sm">No tracking history available yet.</p>
            </div>
        `;
    }
}

function displayTrackingError(errorMessage) {
    // Hide loading and content, show error
    document.getElementById('tracking-loading').classList.add('hidden');
    document.getElementById('tracking-content').classList.add('hidden');
    document.getElementById('tracking-error').classList.remove('hidden');
    
    document.getElementById('tracking-error-message').textContent = errorMessage;
}

function refreshTracking() {
    if (isRefreshing || !currentOrderNumber) return;
    
    isRefreshing = true;
    const refreshBtn = document.getElementById('modal-refresh-btn');
    const refreshIcon = document.getElementById('modal-refresh-icon');
    
    // Show loading state on button
    refreshBtn.disabled = true;
    refreshBtn.classList.add('opacity-50');
    refreshIcon.classList.add('animate-spin');
    
    // Clear cached data to force fresh fetch
    lastTrackingData = null;
    
    loadTrackingData().finally(() => {
        isRefreshing = false;
        refreshBtn.disabled = false;
        refreshBtn.classList.remove('opacity-50');
        refreshIcon.classList.remove('animate-spin');
    });
}

// Close modal when clicking outside
document.getElementById('tracking-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeTrackingModal();
    }
});

// Cleanup intervals on page unload
window.addEventListener('beforeunload', function() {
    if (autoUpdateInterval) {
        clearInterval(autoUpdateInterval);
    }
});

// Payment Functions
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