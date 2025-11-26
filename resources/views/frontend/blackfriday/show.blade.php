@extends('layouts.app')

@section('title', $product->meta_title)
@section('meta_description', $product->meta_description)

@section('content')
<!-- Breadcrumb -->
<section class="bg-gray-50 py-4">
    <div class="container mx-auto px-4">
        <nav class="text-sm text-gray-600">
            <ol class="flex space-x-2">
                <li><a href="/" class="hover:text-red-600">Home</a></li>
                <li>/</li>
                <li><a href="{{ route('black-friday.index') }}" class="hover:text-red-600">Black Friday</a></li>
                <li>/</li>
                <li class="text-gray-900">{{ $product->brand }}</li>
                <li>/</li>
                <li class="text-gray-900">{{ Str::limit($product->name, 30) }}</li>
            </ol>
        </nav>
    </div>
</section>

<!-- Product Detail -->
<div class="container mx-auto px-4 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <!-- Product Images -->
        <div class="space-y-4">
            <!-- Main Image -->
            <div class="relative">
                <img id="mainProductImage" 
                     src="{{ $product->first_image }}" 
                     alt="{{ $product->name }}"
                     class="w-full h-96 object-cover rounded-lg shadow-lg">
                
                <!-- Discount Badge -->
                @if($product->calculated_discount_percentage > 0)
                    <div class="absolute top-4 left-4 bg-red-500 text-white px-4 py-2 rounded-full font-bold text-lg">
                        -{{ $product->calculated_discount_percentage }}% OFF
                    </div>
                @endif
                
                <!-- Flash Sale Badge -->
                @if($product->is_flash_sale)
                    <div class="absolute top-4 right-4 bg-yellow-400 text-black px-3 py-1 rounded-lg font-bold animate-pulse">
                        ⚡ FLASH SALE
                    </div>
                @endif

                <!-- Sale End Countdown -->
                @if($product->sale_end_date && $product->sale_end_date > now())
                    <div class="absolute bottom-4 left-4 bg-black bg-opacity-80 text-white px-4 py-2 rounded-lg">
                        <div class="text-xs">Sale ends in:</div>
                        <div id="sale-countdown" class="font-bold text-lg"></div>
                    </div>
                @endif
            </div>

            <!-- Thumbnail Images -->
            @if($product->image_urls && count($product->image_urls) > 1)
                <div class="grid grid-cols-4 gap-2">
                    @foreach($product->image_urls as $index => $image)
                        <img src="{{ $image }}" 
                             alt="{{ $product->name }} - Image {{ $index + 1 }}"
                             class="w-full h-20 object-cover rounded cursor-pointer hover:opacity-75 transition-opacity thumbnail-image {{ $index === 0 ? 'ring-2 ring-red-500' : '' }}"
                             onclick="changeMainImage('{{ $image }}', this)">
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Product Info -->
        <div class="space-y-6">
            <!-- Brand & Name -->
            <div>
                <div class="text-sm text-gray-600 mb-2">{{ $product->brand }}</div>
                <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ $product->name }}</h1>
                
                <!-- Stock Status -->
                <div class="mb-4">
                    @if($product->stock_quantity > 0)
                        @if($product->stock_quantity <= 5)
                            <div class="flex items-center text-orange-600">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span class="font-medium">Only {{ $product->stock_quantity }} left in stock!</span>
                            </div>
                        @else
                            <div class="flex items-center text-green-600">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span class="font-medium">In Stock</span>
                            </div>
                        @endif
                    @else
                        <div class="flex items-center text-red-600">
                            <i class="fas fa-times-circle mr-2"></i>
                            <span class="font-medium">Out of Stock</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Pricing -->
            <div class="border-b border-gray-200 pb-6">
                <div class="flex flex-col gap-2">
                    @if($product->original_price && $product->original_price > $product->price)
                        <div class="text-2xl text-gray-500 line-through">{{ $product->formatted_original_price }}</div>
                    @endif
                    <div class="text-4xl font-bold text-red-600">{{ $product->formatted_price }}</div>
                    @if($product->discount_amount > 0)
                        <div class="text-lg text-green-600 font-medium">
                            You Save: Rp {{ number_format($product->discount_amount, 0, ',', '.') }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- Product Options -->
            <form id="addToCartForm" class="space-y-6">
                @csrf
                <!-- Size Selection -->
                @if($product->available_sizes && count($product->available_sizes) > 0)
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-3">Size</label>
                        <div class="grid grid-cols-5 gap-2">
                            @foreach($product->available_sizes as $size)
                                <button type="button" 
                                        class="size-option border border-gray-300 px-4 py-2 text-center rounded hover:border-red-500 hover:text-red-500 transition-colors"
                                        data-size="{{ $size }}">
                                    {{ $size }}
                                </button>
                            @endforeach
                        </div>
                        <input type="hidden" name="size" id="selectedSize">
                    </div>
                @endif

                <!-- Color Selection -->
                @if($product->available_colors && count($product->available_colors) > 0)
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-3">Color</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($product->available_colors as $color)
                                <button type="button" 
                                        class="color-option px-4 py-2 border border-gray-300 rounded hover:border-red-500 hover:text-red-500 transition-colors"
                                        data-color="{{ $color }}">
                                    {{ $color }}
                                </button>
                            @endforeach
                        </div>
                        <input type="hidden" name="color" id="selectedColor">
                    </div>
                @endif

                <!-- Quantity -->
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-3">Quantity</label>
                    <div class="flex items-center space-x-3">
                        <button type="button" onclick="updateQuantity(-1)" 
                                class="w-10 h-10 border border-gray-300 rounded-lg flex items-center justify-center hover:border-red-500">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" name="quantity" id="quantity" value="1" min="1" max="{{ $product->stock_quantity }}"
                               class="w-16 text-center border border-gray-300 rounded-lg py-2">
                        <button type="button" onclick="updateQuantity(1)" 
                                class="w-10 h-10 border border-gray-300 rounded-lg flex items-center justify-center hover:border-red-500">
                            <i class="fas fa-plus"></i>
                        </button>
                        <span class="text-sm text-gray-600">Max: {{ $product->stock_quantity }}</span>
                    </div>
                </div>

                <!-- Add to Cart Button -->
                <div class="pt-6">
                    @if($product->stock_quantity > 0)
                        <button type="submit" 
                                class="w-full bg-red-600 text-white py-4 px-6 rounded-lg text-lg font-bold hover:bg-red-700 transition-colors mb-4">
                            <i class="fas fa-shopping-cart mr-2"></i>
                            ADD TO CART - {{ $product->formatted_price }}
                        </button>
                    @else
                        <button type="button" disabled
                                class="w-full bg-gray-400 text-white py-4 px-6 rounded-lg text-lg font-bold cursor-not-allowed mb-4">
                            <i class="fas fa-times mr-2"></i>
                            OUT OF STOCK
                        </button>
                    @endif
                    
                    <!-- Quick Buy Button -->
                    @if($product->stock_quantity > 0)
                        <button type="button" onclick="quickBuy()" 
                                class="w-full bg-black text-white py-4 px-6 rounded-lg text-lg font-bold hover:bg-gray-800 transition-colors">
                            <i class="fas fa-bolt mr-2"></i>
                            BUY NOW
                        </button>
                    @endif
                </div>
            </form>

            <!-- Product Features -->
            @if($product->is_flash_sale || $product->calculated_discount_percentage > 30)
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas fa-fire text-red-500 mr-3"></i>
                        <div>
                            <h4 class="font-bold text-red-900">Hot Deal!</h4>
                            <p class="text-red-700 text-sm">This is a limited-time Black Friday special offer.</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Shipping Info -->
            <div class="bg-gray-50 rounded-lg p-4">
                <div class="space-y-2">
                    <div class="flex items-center">
                        <i class="fas fa-shipping-fast text-blue-500 mr-3"></i>
                        <span class="text-sm">Free shipping for orders over Rp 500,000</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-undo text-green-500 mr-3"></i>
                        <span class="text-sm">Easy returns within 30 days</span>
                    </div>
                    <div class="flex items-center">
                        <i class="fas fa-shield-alt text-purple-500 mr-3"></i>
                        <span class="text-sm">Authentic products guaranteed</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Description -->
    <div class="mt-12 border-t border-gray-200 pt-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2">
                <h3 class="text-2xl font-bold mb-4">Product Description</h3>
                <div class="prose max-w-none text-gray-700">
                    {!! nl2br(e($product->description)) !!}
                </div>
            </div>
            <div>
                <h3 class="text-xl font-bold mb-4">Product Details</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Brand:</span>
                        <span class="font-medium">{{ $product->brand }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">SKU:</span>
                        <span class="font-medium">{{ $product->sku }}</span>
                    </div>
                    @if($product->gender_target)
                        <div class="flex justify-between">
                            <span class="text-gray-600">Gender:</span>
                            <span class="font-medium">{{ implode(', ', array_map('ucfirst', $product->gender_target)) }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-gray-600">Type:</span>
                        <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $product->product_type)) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Products -->
    @if($relatedProducts && $relatedProducts->count() > 0)
        <div class="mt-12 border-t border-gray-200 pt-8">
            <h3 class="text-2xl font-bold mb-6">More Black Friday Deals</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                @foreach($relatedProducts as $related)
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow">
                        <div class="relative">
                            <a href="{{ route('black-friday.show', $related->slug) }}">
                                <img src="{{ $related->first_image }}" 
                                     alt="{{ $related->name }}" 
                                     class="w-full h-48 object-cover">
                            </a>
                            @if($related->calculated_discount_percentage > 0)
                                <div class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 rounded text-sm font-bold">
                                    -{{ $related->calculated_discount_percentage }}%
                                </div>
                            @endif
                        </div>
                        <div class="p-4">
                            <h4 class="font-bold mb-2 line-clamp-2">
                                <a href="{{ route('black-friday.show', $related->slug) }}" class="hover:text-red-600">
                                    {{ $related->name }}
                                </a>
                            </h4>
                            <div class="space-y-1">
                                @if($related->original_price)
                                    <span class="text-gray-500 line-through text-sm">{{ $related->formatted_original_price }}</span>
                                @endif
                                <div class="text-red-600 font-bold text-lg">{{ $related->formatted_price }}</div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

@endsection

@push('scripts')
<script>
// Product options selection
document.addEventListener('DOMContentLoaded', function() {
    // Size selection
    document.querySelectorAll('.size-option').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('.size-option').forEach(btn => btn.classList.remove('border-red-500', 'bg-red-500', 'text-white'));
            this.classList.add('border-red-500', 'bg-red-500', 'text-white');
            document.getElementById('selectedSize').value = this.dataset.size;
        });
    });

    // Color selection
    document.querySelectorAll('.color-option').forEach(button => {
        button.addEventListener('click', function() {
            document.querySelectorAll('.color-option').forEach(btn => btn.classList.remove('border-red-500', 'bg-red-500', 'text-white'));
            this.classList.add('border-red-500', 'bg-red-500', 'text-white');
            document.getElementById('selectedColor').value = this.dataset.color;
        });
    });

    // Initialize countdown
    initSaleCountdown();
});

// Image gallery
function changeMainImage(imageSrc, thumbnail) {
    document.getElementById('mainProductImage').src = imageSrc;
    document.querySelectorAll('.thumbnail-image').forEach(img => img.classList.remove('ring-2', 'ring-red-500'));
    thumbnail.classList.add('ring-2', 'ring-red-500');
}

// Quantity controls
function updateQuantity(change) {
    const quantityInput = document.getElementById('quantity');
    let currentValue = parseInt(quantityInput.value);
    const maxValue = parseInt(quantityInput.max);
    
    currentValue += change;
    if (currentValue < 1) currentValue = 1;
    if (currentValue > maxValue) currentValue = maxValue;
    
    quantityInput.value = currentValue;
}

// Add to cart
document.getElementById('addToCartForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitButton = this.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Adding...';
    
    fetch('{{ route("cart.add-black-friday", $product) }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update cart count
            const cartCountElements = document.querySelectorAll('#cartCount');
            cartCountElements.forEach(el => el.textContent = data.cart_count);
            
            // Show success message
            showNotification('Product added to cart successfully!', 'success');
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error adding product to cart', 'error');
    })
    .finally(() => {
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    });
});

// Quick buy function
function quickBuy() {
    // First add to cart, then redirect to checkout
    const form = document.getElementById('addToCartForm');
    const formData = new FormData(form);
    
    fetch('{{ route("cart.add-black-friday", $product) }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '/checkout';
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error processing quick buy', 'error');
    });
}

// Sale countdown
function initSaleCountdown() {
    @if($product->sale_end_date && $product->sale_end_date > now())
    const saleEndDate = new Date('{{ $product->sale_end_date->toISOString() }}').getTime();
    
    function updateCountdown() {
        const now = new Date().getTime();
        const distance = saleEndDate - now;
        
        if (distance > 0) {
            const hours = Math.floor(distance / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            const countdownEl = document.getElementById('sale-countdown');
            if (countdownEl) {
                countdownEl.innerHTML = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }
        } else {
            const countdownEl = document.getElementById('sale-countdown');
            if (countdownEl) {
                countdownEl.innerHTML = 'EXPIRED';
            }
        }
    }
    
    updateCountdown();
    setInterval(updateCountdown, 1000);
    @endif
}

// Notification function
function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg transition-all duration-300 ${
        type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
    }`;
    notification.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check' : 'exclamation-triangle'} mr-2"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>
@endpush

@push('styles')
<style>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.animate-pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}
</style>
@endpush