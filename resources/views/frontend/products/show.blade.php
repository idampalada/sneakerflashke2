@extends('layouts.app')

@section('title', $product->name . ' - SneakerFlash')

@section('content')
<div class="container mx-auto px-4 py-8">
<nav class="text-sm mb-6">
    <ol class="flex space-x-2 text-gray-600">
        <!-- Gender -->
        @if($product->gender_target && is_array($product->gender_target) && count($product->gender_target) > 0)
@php
    $primaryGender = ucwords($product->gender_target[0]); // Mens, Womens, Unisex
@endphp
<li><a href="{{ route('products.index', ['category' => $product->gender_target[0]]) }}" class="hover:text-blue-600">{{ $primaryGender }}</a></li>
            <li>/</li>
        @endif
        
        <!-- Category & Type dari product_type -->
        @if($product->product_type)
            @php
                // Tentukan kategori utama berdasarkan product_type
                $mainCategory = '';
                $subType = '';
                
                // Footwear types
                if(in_array($product->product_type, ['lifestyle_casual', 'running', 'basketball', 'badminton', 'training', 'tennis'])) {
                    $mainCategory = 'FOOTWEAR';
                    
                    if($product->product_type === 'lifestyle_casual') {
                        $subType = 'LIFESTYLE CASUAL'; // Gabung jadi satu
                    } else {
                        $subType = strtoupper($product->product_type);
                    }
                }
                // Apparel
                elseif($product->product_type === 'apparel') {
                    $mainCategory = 'APPAREL';
                }
                // Accessories  
                elseif(in_array($product->product_type, ['caps', 'bags'])) {
                    $mainCategory = 'ACCESSORIES';
                    $subType = strtoupper($product->product_type);
                }
                
                // URLs untuk navigasi
                $categoryUrl = route('products.index', [
                    'category' => $product->gender_target[0] ?? 'mens', 
                    'section' => strtolower($mainCategory)
                ]);
                
                $typeUrl = route('products.index', [
                    'category' => $product->gender_target[0] ?? 'mens', 
                    'type' => $product->product_type
                ]);
            @endphp
            
            <!-- Main Category -->
@if($mainCategory)
    <li><a href="{{ $categoryUrl }}" class="hover:text-blue-600">{{ ucwords(strtolower($mainCategory)) }}</a></li>
    <li>/</li>
@endif
            
            <!-- Sub Type -->
@if($subType)
    <li><a href="{{ $typeUrl }}" class="hover:text-blue-600">{{ ucwords(strtolower($subType)) }}</a></li>
@endif
        @endif
        
    </ol>
</nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <!-- Product Images -->
        <div class="space-y-4">
            <div class="aspect-square bg-gray-100 rounded-lg overflow-hidden">
                @if($product->images && count($product->images) > 0)
                    @php
                        $mainImageUrl = $product->featured_image;
                        if (!filter_var($mainImageUrl, FILTER_VALIDATE_URL)) {
                            $mainImageUrl = asset('storage/' . ltrim($mainImageUrl, '/'));
                        }
                    @endphp
                    <img id="mainImage" src="{{ $mainImageUrl }}" 
                         alt="{{ $product->name }}"
                         class="w-full h-full object-cover"
                         onerror="this.src='{{ asset('images/default-product.jpg') }}'">
                @else
                    <div class="w-full h-full flex items-center justify-center">
                        <i class="fas fa-image text-6xl text-gray-400"></i>
                    </div>
                @endif
            </div>
            
            @if($product->images && count($product->images) > 1)
                <div class="grid grid-cols-4 gap-2">
                    @foreach($product->images as $index => $image)
                        @php
                            // Handle both external URLs and storage paths
                            if (filter_var($image, FILTER_VALIDATE_URL)) {
                                $imageUrl = $image;
                            } else {
                                $imageUrl = asset('storage/' . ltrim($image, '/'));
                            }
                        @endphp
                        <button class="thumbnail aspect-square bg-gray-100 rounded-lg overflow-hidden hover:ring-2 hover:ring-blue-500 transition-all {{ $index === 0 ? 'ring-2 ring-blue-500' : '' }}"
                                data-image="{{ $imageUrl }}"
                                onclick="changeMainImage('{{ $imageUrl }}', this)">
                            <img src="{{ $imageUrl }}" 
                                 alt="{{ $product->name }}"
                                 class="w-full h-full object-cover"
                                 onerror="this.src='{{ asset('images/default-product.jpg') }}'">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Product Info -->
        <div class="space-y-6">
            <div>

                
                @php
                    // ⭐ CLEAN: Remove SKU parent from product name
                    $originalName = $product->name ?? 'Unknown Product';
                    $skuParent = $product->sku_parent ?? '';
                    
                    $cleanProductName = $originalName;
                    if (!empty($skuParent)) {
                        // Remove SKU parent pattern like "- VN0A3HZFCAR"
                        $cleanProductName = preg_replace('/\s*-\s*' . preg_quote($skuParent, '/') . '\s*/', '', $cleanProductName);
                        $cleanProductName = preg_replace('/\s*' . preg_quote($skuParent, '/') . '\s*/', '', $cleanProductName);
                    }
                    
                    // ⭐ ADDITIONAL: Remove size patterns like "- Size M", "Size L", etc.
                    $cleanProductName = preg_replace('/\s*-\s*Size\s+[A-Z0-9.]+\s*$/i', '', $cleanProductName);
                    $cleanProductName = preg_replace('/\s*Size\s+[A-Z0-9.]+\s*$/i', '', $cleanProductName);
                    $cleanProductName = preg_replace('/\s*-\s*[A-Z0-9.]+\s*$/i', '', $cleanProductName);
                    
                    $cleanProductName = trim($cleanProductName, ' -');
                @endphp
                
                <h1 class="text-xl md:text-3xl font-semibold md:font-bold text-gray-900 mb-3 md:mb-4">{{ $cleanProductName }}</h1>
                
                

                <!-- SKU Information -->
@if($product->sku_parent)
    <div class="text-sm text-gray-500 mb-4">
        <p><strong>SKU:</strong> {{ $product->sku_parent }}</p>
    </div>
@endif
            </div>

            <!-- Price -->
<div class="space-y-1">
    @if($product->sale_price && $product->sale_price < $product->price)
        <div class="flex items-center space-x-2">
            <span class="text-2xl font-bold text-red-600" id="currentPrice">
                Rp {{ number_format($product->sale_price, 0, ',', '.') }}
            </span>
            <span class="text-sm text-gray-500 line-through" id="originalPrice">
                Rp {{ number_format($product->price, 0, ',', '.') }}
            </span>
            <span class="bg-red-100 text-red-800 text-xs px-1.5 py-0.5 rounded">
                Save {{ round((($product->price - $product->sale_price) / $product->price) * 100) }}%
            </span>
        </div>
        <p class="text-xs text-gray-500">Tax included</p>
    @else
        <span class="text-2xl font-bold text-gray-900" id="currentPrice">
            Rp {{ number_format($product->price, 0, ',', '.') }}
        </span>
        <p class="text-xs text-gray-500">Tax included</p>
    @endif
</div>

@if(Auth::check())
    {{-- User yang sudah login - tampilkan earn points berdasarkan tier --}}
    @php
        $user = Auth::user();
        
        // Ambil data member tier dan persentase points
        $pointsPercentage = method_exists($user, 'getPointsPercentage') ? $user->getPointsPercentage() : 1.0;
        $tierLabel = method_exists($user, 'getCustomerTierLabel') ? $user->getCustomerTierLabel() : 'Basic Member';
        $tier = method_exists($user, 'getCustomerTier') ? $user->getCustomerTier() : 'basic';
        
        // Hitung points dari harga produk (gunakan sale price jika ada)
        $productPrice = $product->sale_price && $product->sale_price < $product->price ? 
                       $product->sale_price : $product->price;
        $pointsEarned = round(($productPrice * $pointsPercentage) / 100, 0);
        $pointsValue = $pointsEarned; // 1 point = Rp 1
        
        // Styling berdasarkan tier
        $tierClass = match($tier) {
            'ultimate' => 'tier-ultimate',
            'advance' => 'tier-advance', 
            'basic' => 'tier-basic',
            default => 'tier-basic'
        };
        
        $tierEmoji = match($tier) {
            'ultimate' => '💎',
            'advance' => '🥇',
            'basic' => '🥉',
            default => '🥉'
        };
    @endphp
    
    {{-- Earn Points Container untuk Member --}}
    <div class="earn-points-container">
        <div class="points-text">
            Earn <span class="points-value">{{ number_format($pointsEarned, 0, ',', '.') }}</span> points when you buy me!<br>
            That's worth <span class="points-value">Rp{{ number_format($pointsValue, 0, ',', '.') }}</span>
        </div>
        
        {{-- Copy Link Section --}}
        <div class="copy-link-container">
            <input type="text" class="copy-link-input" value="{{ request()->url() }}" readonly>
            <button class="copy-btn" onclick="copyProductLink(this)">
                Copy Url
            </button>
        </div>
    </div>
@else
    {{-- Guest User - ajak untuk login --}}
    <div class="earn-points-container" style="opacity: 0.8;">
        <div class="points-text">
            <i class="fas fa-lock" style="color: #6b7280; margin-right: 8px;"></i>
            <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-800 underline font-medium">Login</a> to see how many points you can earn!
        </div>
        
        {{-- Copy Link Section untuk Guest --}}
        <div class="copy-link-container">
            <input type="text" class="copy-link-input" value="{{ request()->url() }}" readonly>
            <button class="copy-btn" onclick="copyProductLink(this)">
                Copy Url
            </button>
        </div>
    </div>
@endif

<!-- Section Divider -->
<div class="border-b border-gray-300 my-6"></div>

            <!-- ⭐ ENHANCED: Size Selection with Clean Display -->
            @if(($product->available_sizes && count($product->available_sizes) > 0) || (isset($sizeVariants) && $sizeVariants->count() > 0))
                <div>
                    <h3 class="text-sm font-medium text-gray-900 mb-3">Select Size:</h3>
                    <div class="flex flex-wrap gap-3" id="sizeOptionsContainer">
                        @php
                            $allSizes = collect();
                            
                            // Add current product size
                            if ($product->available_sizes && count($product->available_sizes) > 0) {
                                foreach ($product->available_sizes as $size) {
                                    $allSizes->push([
                                        'size' => $size,
                                        'product_id' => $product->id,
                                        'stock' => $product->stock_quantity ?? 0,
                                        'price' => $product->sale_price ?: $product->price,
                                        'original_price' => $product->price,
                                        'sku' => $product->sku,
                                        'available' => ($product->stock_quantity ?? 0) > 0,
                                        'is_current' => true
                                    ]);
                                }
                            }
                            
                            // Add size variants
                            if (isset($sizeVariants)) {
                                foreach ($sizeVariants as $variant) {
                                    $allSizes->push([
                                        'size' => $variant['size'],
                                        'product_id' => $variant['id'],
                                        'stock' => $variant['stock'],
                                        'price' => $variant['price'],
                                        'original_price' => $variant['original_price'],
                                        'sku' => $variant['sku'],
                                        'available' => $variant['available'],
                                        'is_current' => false
                                    ]);
                                }
                            }
                            
                            // Sort by size
                            $allSizes = $allSizes->sortBy('size')->unique('size');
                        @endphp
                        
                        @foreach($allSizes as $sizeOption)
                            <button type="button" 
                                    class="size-option px-4 py-3 border-2 rounded-lg text-center transition-all {{ $sizeOption['available'] ? 'border-gray-300 hover:border-blue-500 hover:bg-blue-50 text-gray-700' : 'border-gray-200 bg-gray-100 cursor-not-allowed opacity-50 text-gray-400' }} {{ $sizeOption['is_current'] ? 'border-blue-500 bg-blue-50' : '' }}"
                                    data-size="{{ $sizeOption['size'] }}"
                                    data-product-id="{{ $sizeOption['product_id'] }}"
                                    data-stock="{{ $sizeOption['stock'] }}"
                                    data-price="{{ $sizeOption['price'] }}"
                                    data-original-price="{{ $sizeOption['original_price'] }}"
                                    data-sku="{{ $sizeOption['sku'] }}"
                                    data-available="{{ $sizeOption['available'] ? 'true' : 'false' }}"
                                    {{ $sizeOption['available'] ? '' : 'disabled' }}
                                    onclick="selectSize(this)">
                                <div class="font-semibold text-sm">{{ $sizeOption['size'] }}</div>
                            </button>
                        @endforeach
                    </div>
                    

                </div>
            @endif

            <!-- Available Colors -->
            @if($product->available_colors && is_array($product->available_colors) && count($product->available_colors) > 0)
                <div>
                    <h3 class="text-sm font-medium text-gray-900 mb-2">Available Colors:</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($product->available_colors as $color)
                            <span class="px-3 py-1 border border-gray-300 rounded-lg text-sm bg-white hover:bg-gray-50">
                                {{ ucfirst($color) }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif



            

            <!-- ⭐ ENHANCED: Add to Cart with Size Support -->
            <form id="addToCartForm" action="{{ route('cart.add') }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}" id="selectedProductId">
                <input type="hidden" name="size" value="" id="selectedSizeValue">
                
<div class="flex items-center space-x-4">
    <label class="text-sm font-medium text-gray-700">Quantity:</label>
    <div class="flex items-center border border-gray-300 rounded-lg">
        <button type="button" onclick="decreaseQuantity()" 
                class="px-4 py-2 text-gray-600 hover:bg-gray-100 border-r border-gray-300">
            -
        </button>
        <input type="number" name="quantity" id="quantity" 
               min="1" max="{{ $product->stock_quantity ?? 1 }}" value="1"
               class="w-16 text-center py-2 border-0 focus:outline-none focus:ring-0" readonly>
        <button type="button" onclick="increaseQuantity()" 
                class="px-4 py-2 text-gray-600 hover:bg-gray-100 border-l border-gray-300">
            +
        </button>
    </div>
    <span class="text-sm text-gray-500" id="maxQuantityText">Max: {{ $product->stock_quantity ?? 0 }}</span>
</div>

                <div class="flex space-x-4">
    @if(($product->stock_quantity ?? 0) > 0)
        <button type="submit" 
                id="addToCartBtn"
                class="w-16 bg-white text-black border border-gray-300 py-3 px-4 rounded-lg hover:bg-gray-50 transition-colors font-medium flex items-center justify-center">
            <i class="fas fa-shopping-cart"></i>
        </button>
    @else
        <button type="button" 
                disabled
                id="addToCartBtn"
                class="w-16 bg-gray-400 text-white py-3 px-4 rounded-lg cursor-not-allowed flex items-center justify-center">
            <i class="fas fa-times"></i>
        </button>
    @endif
    
    <button type="button" 
            class="flex-1 bg-black text-white py-3 px-6 rounded-lg hover:bg-gray-800 transition-colors flex items-center justify-center buy-now-btn"
            data-product-id="{{ $product->id }}"
            data-product-name="{{ $cleanProductName }}"
            title="Buy Now">
        Buy Now!
    </button>
</div>

<!-- Section Divider -->
<div class="border-b border-gray-300 my-6"></div>
            <!-- Features -->
            @if($product->features && is_array($product->features) && count($product->features) > 0)
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Features</h3>
                    <ul class="space-y-2">
                        @foreach($product->features as $feature)
                            <li class="flex items-start text-gray-600">
                                <i class="fas fa-check text-green-500 mr-3 mt-1 flex-shrink-0"></i>
                                <span>{{ $feature }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            
        </div>
    </div>

    <!-- Description -->
    @if($product->description)
        <div class="mt-16">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Product Description</h2>
            <div class="prose prose-gray max-w-none bg-gray-50 rounded-lg p-6">
                {!! nl2br(e($product->description)) !!}
            </div>
        </div>
    @endif
<!-- Section Divider -->
<div class="border-b border-gray-300 my-6"></div>
    <!-- Related Products -->
@if(isset($relatedProducts) && $relatedProducts->count() > 0)
    <div class="mt-16">
        <h2 class="text-2xl font-bold text-gray-900 mb-8">Related Products</h2>
        
        <!-- Horizontal Scrollable Products -->
        <div class="relative">
            <div class="related-products-scroll overflow-x-auto scrollbar-hide">
                <div class="flex space-x-4 pb-4 pr-4" style="width: max-content;">
                    @foreach($relatedProducts as $relatedProduct)
                        <div class="product-card-horizontal bg-white rounded-lg border border-gray-200 overflow-hidden hover:border-gray-300 transition-colors flex-shrink-0 relative">
                            <!-- Sale Badge -->
                            @if($relatedProduct->sale_price && $relatedProduct->sale_price < $relatedProduct->price)
                                <div class="absolute top-2 left-2 z-10">
                                    <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded">
                                        Sale
                                    </span>
                                </div>
                            @endif

                            <div class="relative">
                                <a href="{{ route('products.show', $relatedProduct->slug) }}">
                                    @if($relatedProduct->images && count($relatedProduct->images) > 0)
                                        @php
                                            $relatedImage = filter_var($relatedProduct->images[0], FILTER_VALIDATE_URL) 
                                                ? $relatedProduct->images[0] 
                                                : asset('storage/' . ltrim($relatedProduct->images[0], '/'));
                                        @endphp
                                        <img src="{{ $relatedImage }}" 
                                             alt="{{ $relatedProduct->name }}"
                                             class="w-full h-48 object-cover"
                                             onerror="this.src='{{ asset('images/default-product.jpg') }}'">
                                    @else
                                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                                            <i class="fas fa-shoe-prints text-3xl text-gray-400"></i>
                                        </div>
                                    @endif
                                </a>
                            </div>
                            
                            <div class="p-4">
                                @php
                                    $relatedCleanName = $relatedProduct->name;
                                    if (!empty($relatedProduct->sku_parent)) {
                                        $relatedCleanName = preg_replace('/\s*-\s*' . preg_quote($relatedProduct->sku_parent, '/') . '\s*/', '', $relatedCleanName);
                                        $relatedCleanName = preg_replace('/\s*-\s*Size\s+[A-Z0-9.]+\s*$/i', '', $relatedCleanName);
                                        $relatedCleanName = preg_replace('/\s*Size\s+[A-Z0-9.]+\s*$/i', '', $relatedCleanName);
                                        $relatedCleanName = trim($relatedCleanName, ' -');
                                    }
                                @endphp
                                <h3 class="font-medium text-gray-900 mb-2 text-sm line-clamp-2">{{ $relatedCleanName }}</h3>
                                
                                <div class="flex flex-col">
                                    @if($relatedProduct->sale_price && $relatedProduct->sale_price < $relatedProduct->price)
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm font-bold text-red-600">
                                                Rp {{ number_format($relatedProduct->sale_price, 0, ',', '.') }}
                                            </span>
                                            <span class="text-xs text-gray-500 line-through">
                                                Rp {{ number_format($relatedProduct->price, 0, ',', '.') }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-sm font-bold text-gray-900">
                                            Rp {{ number_format($relatedProduct->price, 0, ',', '.') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endif

<!-- Toast Notification -->
<div id="toastNotification" class="fixed top-4 right-4 z-50 hidden">
    <div class="bg-white border border-gray-200 rounded-lg shadow-lg p-4 min-w-80">
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i id="toastIcon" class="fas fa-check-circle text-green-500"></i>
            </div>
            <div class="ml-3 flex-1">
                <p id="toastMessage" class="text-sm font-medium text-gray-900"></p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <button type="button" onclick="hideToast()" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Enhanced Add to Cart and Buy Now JavaScript with Authentication Check
document.addEventListener('DOMContentLoaded', function() {
    // Add to Cart functionality
    const addToCartBtn = document.getElementById('addToCartBtn');
    const addToCartForm = document.getElementById('addToCartForm');
    const buyNowBtn = document.querySelector('.buy-now-btn');

    // Add to Cart Form Handler
    if (addToCartForm && addToCartBtn) {
        addToCartForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleAddToCart();
        });
    }

    // Buy Now Button Handler
    if (buyNowBtn) {
        buyNowBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleBuyNow();
        });
    }

    // Handle Add to Cart
    function handleAddToCart() {
        const btn = addToCartBtn;
        const originalText = btn.innerHTML;
        
        // Disable button and show loading
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        const formData = new FormData(addToCartForm);
        
        fetch(addToCartForm.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            
            if (data.success) {
                // Success - show toast and update cart counter
                showToast(data.message || 'Product added to cart successfully!', 'success');
                updateCartCounter(data.cart_count || 0);
            } else {
                // Check if it's an authentication error
                if (data.redirect && data.redirect.includes('login')) {
                    // Redirect to login page
                    showToast('Please login to add items to cart', 'info');
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    // Show error message
                    showToast(data.message || 'Failed to add product to cart', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Add to Cart error:', error);
            btn.innerHTML = originalText;
            btn.disabled = false;
            showToast('Error adding product to cart. Please try again.', 'error');
        });
    }

    // Handle Buy Now
    function handleBuyNow() {
        const btn = buyNowBtn;
        const originalText = btn.innerHTML;
        
        // Get product details
        const productId = btn.getAttribute('data-product-id');
        const quantityInput = document.getElementById('quantity');
        const sizeSelect = document.getElementById('size');
        
        if (!productId) {
            showToast('Product information not found', 'error');
            return;
        }
        
        const quantity = quantityInput ? parseInt(quantityInput.value) || 1 : 1;
        const size = sizeSelect ? sizeSelect.value : null;
        
        // Disable button and show loading
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
        
        // Prepare form data
        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('quantity', quantity);
        if (size) {
            formData.append('size', size);
        }
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        
        // First add to cart, then redirect to checkout
        fetch('/cart/add', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Success - show message and redirect to checkout
                showToast('Product added to cart! Redirecting to checkout...', 'success');
                updateCartCounter(data.cart_count || 0);
                
                // Redirect to checkout after short delay
                setTimeout(() => {
                    window.location.href = '/checkout';
                }, 1000);
            } else {
                // Check if it's an authentication error
                if (data.redirect && data.redirect.includes('login')) {
                    // Redirect to login page
                    showToast('Please login to purchase items', 'info');
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    // Show error message
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    showToast(data.message || 'Failed to add product to cart', 'error');
                }
            }
        })
        .catch(error => {
            console.error('Buy Now error:', error);
            btn.innerHTML = originalText;
            btn.disabled = false;
            showToast('Error processing Buy Now request', 'error');
        });
    }

    // Utility function to show toast notifications
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toastNotification');
        const icon = document.getElementById('toastIcon');
        const messageEl = document.getElementById('toastMessage');
        
        if (!toast || !icon || !messageEl) {
            // Fallback to alert if toast elements not found
            alert(message);
            return;
        }
        
        messageEl.textContent = message;
        
        // Set icon based on type
        icon.className = 'fas ';
        switch(type) {
            case 'success':
                icon.className += 'fa-check-circle text-green-500';
                toast.className = toast.className.replace(/bg-\w+-100/, 'bg-green-100');
                toast.className = toast.className.replace(/border-\w+-400/, 'border-green-400');
                break;
            case 'error':
                icon.className += 'fa-exclamation-circle text-red-500';
                toast.className = toast.className.replace(/bg-\w+-100/, 'bg-red-100');
                toast.className = toast.className.replace(/border-\w+-400/, 'border-red-400');
                break;
            case 'info':
                icon.className += 'fa-info-circle text-blue-500';
                toast.className = toast.className.replace(/bg-\w+-100/, 'bg-blue-100');
                toast.className = toast.className.replace(/border-\w+-400/, 'border-blue-400');
                break;
            case 'warning':
                icon.className += 'fa-exclamation-triangle text-yellow-500';
                toast.className = toast.className.replace(/bg-\w+-100/, 'bg-yellow-100');
                toast.className = toast.className.replace(/border-\w+-400/, 'border-yellow-400');
                break;
            default:
                icon.className += 'fa-check-circle text-green-500';
        }
        
        // Show toast
        toast.classList.remove('hidden');
        
        // Auto-hide after 3 seconds
        setTimeout(() => hideToast(), 3000);
    }
    
    // Function to hide toast
    function hideToast() {
        const toast = document.getElementById('toastNotification');
        if (toast) {
            toast.classList.add('hidden');
        }
    }
    
    // Function to update cart counter in header/navbar
    function updateCartCounter(count) {
        const cartCounters = document.querySelectorAll('.cart-counter, [data-cart-count], .cart-badge, .cart-count');
        cartCounters.forEach(counter => {
            counter.textContent = count;
            
            if (count > 0) {
                counter.style.display = 'inline-block';
                counter.classList.remove('hidden');
                
                // Add animation class if available
                counter.classList.add('animate-pulse');
                setTimeout(() => {
                    counter.classList.remove('animate-pulse');
                }, 500);
            } else {
                // Hide counter if count is 0
                counter.style.display = 'none';
                counter.classList.add('hidden');
            }
        });
        
        // Also update any cart icons with badges
        const cartIcons = document.querySelectorAll('.cart-icon-wrapper, .relative .cart-badge');
        cartIcons.forEach(icon => {
            const badge = icon.querySelector('.cart-badge, .cart-count, [data-cart-count]');
            if (badge) {
                badge.textContent = count;
                if (count > 0) {
                    badge.style.display = 'inline-block';
                    badge.classList.remove('hidden');
                } else {
                    badge.style.display = 'none';
                    badge.classList.add('hidden');
                }
            }
        });
    }

    // Quantity controls
    const quantityInput = document.getElementById('quantity');
    const increaseBtn = document.querySelector('button[onclick="increaseQuantity()"]');
    const decreaseBtn = document.querySelector('button[onclick="decreaseQuantity()"]');

    // Global functions for quantity controls (keep existing functionality)
    window.increaseQuantity = function() {
        if (quantityInput) {
            const currentValue = parseInt(quantityInput.value) || 1;
            const maxQuantity = parseInt(quantityInput.getAttribute('max')) || 99;
            
            if (currentValue < maxQuantity) {
                quantityInput.value = currentValue + 1;
            }
        }
    }

    window.decreaseQuantity = function() {
        if (quantityInput) {
            const currentValue = parseInt(quantityInput.value) || 1;
            const minQuantity = parseInt(quantityInput.getAttribute('min')) || 1;
            
            if (currentValue > minQuantity) {
                quantityInput.value = currentValue - 1;
            }
        }
    }

    // Make functions globally available
    window.showToast = showToast;
    window.hideToast = hideToast;
    window.updateCartCounter = updateCartCounter;
});

console.log('✅ Enhanced product page loaded with authentication checks for Add to Cart and Buy Now');
</script>

<style>
    /* Earn Points & Copy Link Styles */
.earn-points-container {
    background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 0;
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.earn-points-container::before {
    display: none;
}

.points-badge {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 14px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
    margin-bottom: 12px;
}

.points-text {
    font-size: 18px;
    font-weight: 600;
    color: #374151;
    margin: 12px 0;
    line-height: 1.4;
}

.points-value {
    color: #1f2937;
    font-weight: 700;
    font-size: 19px;
}

.copy-link-container {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px;
    margin-top: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.copy-link-input {
    flex: 1;
    border: none;
    outline: none;
    font-size: 14px;
    color: #6b7280;
    background: transparent;
    padding: 4px 0;
}

.copy-btn {
    background: #000000;
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
    min-width: 110px;
    justify-content: center;
}

.copy-btn:hover {
    background: #333333;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.copy-btn.copied {
    background: #059669;
}

.tier-info {
    font-size: 13px;
    color: #6b7280;
    margin-top: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.tier-badge {
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.tier-basic { 
    background: #fef3c7; 
    color: #92400e; 
    border: 1px solid #fcd34d;
}

.tier-advance { 
    background: #dbeafe; 
    color: #1e40af; 
    border: 1px solid #93c5fd;
}

.tier-ultimate { 
    background: #f3e8ff; 
    color: #7c3aed; 
    border: 1px solid #c4b5fd;
}

.lock-icon {
    color: #6b7280;
    flex-shrink: 0;
    font-size: 16px;
}

/* Mobile Responsive */
@media (max-width: 640px) {
    .earn-points-container {
        margin: 16px -16px;
        border-radius: 0;
        border-left: none;
        border-right: none;
        padding: 16px;
    }
    
    .points-text {
        font-size: 16px;
    }
    
    .points-value {
        font-size: 16px;
    }
    
    .copy-link-container {
        flex-direction: column;
        gap: 12px;
        padding: 16px;
    }
    
    .copy-link-input {
        text-align: center;
        padding: 12px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 13px;
        width: 100%;
    }
    
    .copy-btn {
        width: 100%;
        padding: 12px;
        font-size: 15px;
    }
    
    .tier-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }
    
    .points-badge {
        font-size: 13px;
        padding: 6px 14px;
    }
}

@media (max-width: 480px) {
    .points-text {
        font-size: 15px;
    }
    
    .points-value {
        font-size: 16px;
    }
    
    .earn-points-container {
        padding: 14px;
    }
}
    /* Related Products Horizontal Scroll Styles */
.related-products-scroll {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none; /* Firefox */
    scroll-behavior: smooth;
}

.related-products-scroll::-webkit-scrollbar {
    display: none; /* Chrome, Safari */
}

/* Mobile: Tampilkan 2.5 produk */
@media (max-width: 640px) {
    .related-products-scroll {
        padding-left: 0.5rem;
        padding-right: 1rem;
        margin-left: -0.5rem;
        margin-right: -1rem;
    }
    
    .product-card-horizontal {
        width: calc(45vw - 1rem);
        min-width: calc(45vw - 1rem);
        max-width: 160px;
    }
}

/* Tablet: Tampilkan 3-4 produk */
@media (min-width: 641px) and (max-width: 1024px) {
    .product-card-horizontal {
        width: 160px;
        min-width: 160px;
    }
}

/* Desktop: Tampilkan 5-6 produk */
@media (min-width: 1025px) {
    .product-card-horizontal {
        width: 200px;
        min-width: 200px;
    }
}
/* Product detail specific styles */
.size-option {
    transition: all 0.2s ease;
    min-width: 60px;
}

.size-option:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.size-option.selected,
.size-option:hover:not(:disabled) {
    border-color: #3b82f6 !important;
    background-color: #eff6ff !important;
}

.thumbnail {
    transition: all 0.2s ease;
}

.thumbnail:hover {
    transform: scale(1.02);
}

#toastNotification {
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .size-option {
        min-width: 50px;
        padding: 10px 12px;
    }
    
    .size-option .font-semibold {
        font-size: 14px;
    }
}

/* Line clamp utility */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Color classes */
.text-green-600 { color: #059669; }
.text-red-600 { color: #dc2626; }
.text-blue-600 { color: #2563eb; }
.text-blue-700 { color: #1d4ed8; }
.text-blue-900 { color: #1e3a8a; }
.text-yellow-600 { color: #d97706; }
.text-orange-600 { color: #ea580c; }
</style>
@endsection

<!-- Toast Notification Component -->
<!-- Letakkan di bagian bawah layout atau di show.blade.php -->
<div id="toastNotification" class="fixed top-20 right-4 z-50 max-w-sm w-full bg-white border border-gray-300 rounded-lg shadow-lg p-4 hidden animate-slide-in-right">
    <div class="flex items-center">
        <div class="flex-shrink-0">
            <i id="toastIcon" class="fas fa-check-circle text-green-500"></i>
        </div>
        <div class="ml-3 flex-1">
            <p id="toastMessage" class="text-sm font-medium text-gray-900"></p>
        </div>
        <div class="ml-4 flex-shrink-0 flex">
            <button onclick="hideToast()" class="bg-white rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <span class="sr-only">Close</span>
                <i class="fas fa-times h-4 w-4"></i>
            </button>
        </div>
    </div>
</div>

<!-- CSS untuk animations -->
<style>
@keyframes slide-in-right {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slide-out-right {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

.animate-slide-in-right {
    animation: slide-in-right 0.3s ease-out;
}

.animate-slide-out-right {
    animation: slide-out-right 0.3s ease-in;
}

/* Toast variants */
.toast-success {
    @apply bg-green-50 border-green-200;
}

.toast-error {
    @apply bg-red-50 border-red-200;
}

.toast-info {
    @apply bg-blue-50 border-blue-200;
}

.toast-warning {
    @apply bg-yellow-50 border-yellow-200;
}

/* Responsive */
@media (max-width: 640px) {
    #toastNotification {
        @apply left-4 right-4 max-w-none;
    }
}

</style>

<!-- Alternative: Simple Toast without animations -->
<div id="simpleToast" class="fixed top-20 right-4 z-50 max-w-sm w-full hidden">
    <div class="bg-white border border-gray-300 rounded-lg shadow-lg p-4">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i id="simpleToastIcon" class="fas fa-check-circle text-green-500"></i>
            </div>
            <div class="ml-3 flex-1">
                <p id="simpleToastMessage" class="text-sm font-medium text-gray-900"></p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <button onclick="hideSimpleToast()" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Simple toast functions if the main JavaScript isn't loaded
// Simple toast functions if the main JavaScript isn't loaded
if (typeof showToast === 'undefined') {
    function showSimpleToast(message, type = 'success') {
        const toast = document.getElementById('simpleToast');
        const icon = document.getElementById('simpleToastIcon');
        const messageEl = document.getElementById('simpleToastMessage');
        
        if (!toast || !icon || !messageEl) return;
        
        messageEl.textContent = message;
        
        // Set icon and colors based on type
        icon.className = 'fas ';
        const toastDiv = toast.querySelector('div');
        
        switch(type) {
            case 'success':
                icon.className += 'fa-check-circle text-green-500';
                toastDiv.className = toastDiv.className.replace(/bg-\w+-50/, 'bg-green-50');
                toastDiv.className = toastDiv.className.replace(/border-\w+-300/, 'border-green-300');
                break;
            case 'error':
                icon.className += 'fa-exclamation-circle text-red-500';
                toastDiv.className = toastDiv.className.replace(/bg-\w+-50/, 'bg-red-50');
                toastDiv.className = toastDiv.className.replace(/border-\w+-300/, 'border-red-300');
                break;
            case 'info':
                icon.className += 'fa-info-circle text-blue-500';
                toastDiv.className = toastDiv.className.replace(/bg-\w+-50/, 'bg-blue-50');
                toastDiv.className = toastDiv.className.replace(/border-\w+-300/, 'border-blue-300');
                break;
            case 'warning':
                icon.className += 'fa-exclamation-triangle text-yellow-500';
                toastDiv.className = toastDiv.className.replace(/bg-\w+-50/, 'bg-yellow-50');
                toastDiv.className = toastDiv.className.replace(/border-\w+-300/, 'border-yellow-300');
                break;
        }
        
        toast.classList.remove('hidden');
        
        // Auto-hide after 3 seconds
        setTimeout(() => hideSimpleToast(), 3000);
    }
    
    function hideSimpleToast() {
        const toast = document.getElementById('simpleToast');
        if (toast) {
            toast.classList.add('hidden');
        }
    }
    
    // Make functions globally available
    window.showToast = showSimpleToast;
    window.hideToast = hideSimpleToast;
}

// Definisikan fungsi selectSize
window.selectSize = function(element) {
    var size = element.getAttribute('data-size');
    var stock = element.getAttribute('data-stock');
    var productId = element.getAttribute('data-product-id');
    var price = element.getAttribute('data-price') || '0';
    
    // Perbarui URL dengan ukuran yang dipilih (tanpa titik)
    var currentUrl = window.location.href;
    var urlParts = currentUrl.split('-');
    var lastPart = urlParts[urlParts.length - 1];
    
    // Cek apakah bagian terakhir URL adalah angka atau angka dengan titik
    if (!isNaN(lastPart) || lastPart.match(/^\d+\.\d+$/)) {
        // Ganti ukuran di URL - hapus titik
        var cleanSize = size.toString().replace('.', '');
        urlParts[urlParts.length - 1] = cleanSize;
        var newUrl = urlParts.join('-');
        
        // Refresh halaman dengan URL baru
        window.location.href = newUrl;
    }
};

// Tambahkan fungsi untuk copy URL yang benar
window.copyProductLink = function(button) {
    // Ambil input di sebelahnya
    var input = button.previousElementSibling;
    if (!input) return;
    
    // Salin teks ke clipboard
    input.select();
    document.execCommand('copy');
    
    // Tampilkan animasi copy berhasil
    button.classList.add('copied');
    button.innerHTML = '<i class="fas fa-check"></i> Copied!';
    
    // Reset setelah 2 detik
    setTimeout(function() {
        button.classList.remove('copied');
        button.innerHTML = 'Copy URL';
    }, 2000);
};
</script>
