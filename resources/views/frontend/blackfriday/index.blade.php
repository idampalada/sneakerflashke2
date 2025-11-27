{{-- File: resources/views/frontend/blackfriday/index.blade.php --}}
{{-- Fixed pricing display to match products page exactly --}}
@extends('layouts.app')

@section('title', 'Black Friday Deals - SneakerFlash')

@section('content')
    <!-- Page Header - Exactly same as Products -->
    <section class="bg-white py-6 border-b border-gray-200">
        <div class="container mx-auto px-4">
            <nav class="text-sm mb-4">
                <ol class="flex space-x-2 text-gray-600">
                    <li><a href="/" class="hover:text-blue-600">Home</a></li>
                    <li>/</li>
                    <li class="text-gray-900">BLACK FRIDAY</li>
                </ol>
            </nav>     
        </div>
    </section>

    <div class="container mx-auto px-4 py-8">
        <div class="flex gap-8">
            <!-- Products Grid -->
            <main class="flex-1">
                <!-- Sort Options & View Toggle -->
                <div class="bg-white rounded-2xl p-6 mb-6 border border-gray-100">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center space-x-4">
                            <span class="text-gray-600 font-medium">Sort by:</span>
                            <select name="sort" onchange="updateSort(this.value)" class="px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="latest" {{ request('sort') === 'latest' || !request('sort') ? 'selected' : '' }}>Latest</option>
                                <option value="name_az" {{ request('sort') === 'name_az' ? 'selected' : '' }}>Name A-Z</option>
                                <option value="price_low" {{ request('sort') === 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                                <option value="price_high" {{ request('sort') === 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
                                <option value="featured" {{ request('sort') === 'featured' ? 'selected' : '' }}>Featured</option>
                            </select>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <button id="gridView" class="p-2 rounded-lg border border-gray-200 text-blue-600 bg-blue-50">
                                <i class="fas fa-th-large"></i>
                            </button>
                            <button id="listView" class="p-2 rounded-lg border border-gray-200 text-gray-400">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Products Grid -->
                <div id="productsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    @if(isset($products) && $products->count() > 0)
                        @foreach($products as $product)
                            @php
                                // Clean product name
                                $originalName = $product->name ?? 'Unknown Product';
                                $skuParent = $product->sku_parent ?? '';
                                
                                $cleanProductName = $originalName;
                                if (!empty($skuParent)) {
                                    $cleanProductName = preg_replace('/\s*-\s*' . preg_quote($skuParent, '/') . '\s*/', '', $cleanProductName);
                                    $cleanProductName = preg_replace('/\s*' . preg_quote($skuParent, '/') . '\s*/', '', $cleanProductName);
                                }
                                
                                $cleanProductName = preg_replace('/\s*-\s*Size\s+[A-Z0-9.]+\s*$/i', '', $cleanProductName);
                                $cleanProductName = preg_replace('/\s*Size\s+[A-Z0-9.]+\s*$/i', '', $cleanProductName);
                                $cleanProductName = preg_replace('/\s*-\s*[A-Z0-9.]+\s*$/i', '', $cleanProductName);
                                $cleanProductName = trim($cleanProductName, ' -');
                                
                                // Get image
                                $productImage = $product->first_image ?? asset('images/default-product.jpg');
                                
                                // ⭐ PRICING - Use controller calculated values for consistent display
                                $finalPrice = $product->final_price ?? ($product->price ?? 0);
                                $displayOriginalPrice = $product->display_original_price ?? null;
                                $discountPercentage = $product->calculated_discount_percentage ?? 0;
                                
                                // Size variants
                                $sizeVariants = [];
                                if (!empty($product->sku_parent)) {
                                    $variants = \App\Models\Product::where('sku_parent', $product->sku_parent)
                                        ->where('product_type', 'BLACKFRIDAY')
                                        ->where('is_active', true)
                                        ->get();
                                    
                                    foreach ($variants as $variant) {
                                        if ($variant->available_sizes) {
                                            $sizes = is_string($variant->available_sizes) 
                                                ? json_decode($variant->available_sizes, true) 
                                                : $variant->available_sizes;
                                            
                                            if (is_array($sizes)) {
                                                foreach ($sizes as $size) {
                                                    // Ultra clean size
                                                    $cleanSize = (string) $size;
                                                    $cleanSize = trim($cleanSize, '"\'');
                                                    $cleanSize = str_replace(['[', ']', '"', "'", '\\'], '', $cleanSize);
                                                    $cleanSize = preg_replace('/[\x00-\x1F\x7F]/', '', $cleanSize);
                                                    $cleanSize = trim($cleanSize);
                                                    
                                                    if (!empty($cleanSize)) {
                                                        $sizeVariants[] = [
                                                            'id' => $variant->id,
                                                            'size' => $cleanSize,
                                                            'stock' => $variant->stock_quantity ?? 0,
                                                            'price' => $variant->sale_price ?: $variant->price,
                                                            'original_price' => $variant->original_price ?? $variant->price,
                                                            'sku' => $variant->sku
                                                        ];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                
                                $hasMultipleSizes = count($sizeVariants) > 1;
                                $totalStock = $hasMultipleSizes ? collect($sizeVariants)->sum('stock') : ($product->stock_quantity ?? 0);
                            @endphp
                            
                            <!-- Product Card -->
                            <div class="product-card bg-white rounded-2xl overflow-hidden border border-gray-100 hover:shadow-lg transition-all duration-300 group h-full max-h-[540px] flex flex-col"
                                 data-product-id="{{ $product->id ?? '' }}"
                                 data-sku-parent="{{ $product->sku_parent ?? '' }}"
                                 data-product-name="{{ $cleanProductName }}">

                                <!-- Product Image -->
                                <div class="relative bg-gray-50 overflow-hidden flex items-center justify-center h-[260px] md:h-[300px]">
                                    <a href="{{ route('black-friday.show', $product->slug ?? '#') }}">
                                        <img src="{{ $productImage }}" 
                                             alt="{{ $cleanProductName }}"
                                             class="max-w-full max-h-full object-contain p-2 transition-transform duration-300 group-hover:scale-105"
                                             loading="lazy">
                                    </a>
                                    
                                    <!-- Product Badges -->
                                    <div class="absolute top-3 left-3 flex flex-col gap-2">
                                        @if($product->is_featured ?? false)
                                            <span class="bg-yellow-500 text-white text-xs px-2 py-1 rounded-full font-medium">
                                                Featured
                                            </span>
                                        @endif
                                        @if($discountPercentage > 0)
                                            <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full font-medium">
                                                -{{ $discountPercentage }}%
                                            </span>
                                        @endif
                                        @if($totalStock <= 0)
                                            <span class="bg-gray-500 text-white text-xs px-2 py-1 rounded-full font-medium">
                                                Out of Stock
                                            </span>
                                        @elseif($totalStock < 10)
                                            <span class="bg-orange-500 text-white text-xs px-2 py-1 rounded-full font-medium">
                                                Low Stock
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Wishlist Button -->
                                    <button class="wishlist-btn absolute top-3 right-3 w-8 h-8 bg-white rounded-full flex items-center justify-center shadow-md hover:shadow-lg transition-all duration-200" 
                                            data-product-id="{{ $product->id ?? '' }}"
                                            data-product-name="{{ $cleanProductName }}">
                                        <i class="wishlist-icon far fa-heart text-gray-400 transition-colors"></i>
                                    </button>
                                </div>
                                
                                <!-- Product Info -->
                                <div class="p-4 flex flex-col h-full">
                                    <div class="mb-2">
                                        <span class="text-xs text-gray-500 uppercase tracking-wide">
                                            {{ strtoupper($product->product_type ?? 'APPAREL') }}
                                            @if($product->brand ?? false)
                                                • {{ $product->brand }}
                                            @endif
                                        </span>
                                    </div>
                                    
                                    <!-- Product Title -->
                                    <h3 class="font-semibold text-gray-900 mb-3 text-sm leading-tight">
                                        <a href="{{ route('black-friday.show', $product->slug ?? '#') }}" 
                                           class="hover:text-blue-600 transition-colors">
                                            {{ $cleanProductName }}
                                        </a>
                                    </h3>
                                    
                                    <!-- Available Sizes -->
                                    @if($hasMultipleSizes)
                                        <div class="mb-3">
                                            <span class="text-xs text-gray-500 font-medium">Available Sizes:</span>
                                            <div class="flex flex-wrap gap-1 mt-1" id="sizeContainer-{{ $product->id }}">
                                                @foreach($sizeVariants as $variant)
                                                    @php
                                                        $size = $variant['size'] ?? 'Unknown';
                                                        $stock = (int) ($variant['stock'] ?? 0);
                                                        $variantId = $variant['id'] ?? '';
                                                        $sku = $variant['sku'] ?? '';
                                                        $isAvailable = $stock > 0;
                                                        $variantPrice = $variant['price'] ?? $finalPrice;
                                                        $variantOriginalPrice = $variant['original_price'] ?? $finalPrice;
                                                    @endphp
                                                    <span class="size-badge text-xs px-2 py-1 rounded border {{ $isAvailable ? 'text-gray-700 bg-gray-50 border-gray-200 hover:bg-blue-50 hover:border-blue-300' : 'text-gray-400 bg-gray-100 border-gray-200 line-through' }}" 
                                                          data-size="{{ $size }}" 
                                                          data-stock="{{ $stock }}"
                                                          data-product-id="{{ $variantId }}"
                                                          data-sku="{{ $sku }}"
                                                          data-available="{{ $isAvailable ? 'true' : 'false' }}"
                                                          data-price="{{ $variantPrice }}"
                                                          data-original-price="{{ $variantOriginalPrice }}">
                                                        {{ $size }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                    
                                    <div class="mb-4 price-display">
    @if($displayOriginalPrice && $displayOriginalPrice > $finalPrice)
        {{-- Sale Price Display - RED price + strikethrough original (horizontal) --}}
        <div class="flex items-baseline space-x-2">
            <span class="text-lg font-bold text-red-600">
                Rp {{ number_format($finalPrice, 0, ',', '.') }}
            </span>
            <span class="text-sm text-gray-500 line-through">
                Rp {{ number_format($displayOriginalPrice, 0, ',', '.') }}
            </span>
        </div>
    @else
        {{-- Regular price - BLACK --}}
        <span class="text-lg font-bold text-gray-900">
            Rp {{ number_format($finalPrice, 0, ',', '.') }}
        </span>
    @endif
</div>

                                    
                                    <!-- Stock Status -->
                                    <div class="mb-3 stock-display">
                                        @if($totalStock > 0)
                                            <span class="text-xs text-green-600 font-medium">
                                                <i class="fas fa-check-circle mr-1"></i>
                                                In Stock ({{ $totalStock }} total)
                                            </span>
                                        @else
                                            <span class="text-xs text-red-600 font-medium">
                                                <i class="fas fa-times-circle mr-1"></i>
                                                Out of Stock
                                            </span>
                                        @endif
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="mt-auto">
                                        <div class="flex gap-2">
                                            @if($totalStock > 0)
                                                @if($hasMultipleSizes)
                                                    <button type="button"
                                                            class="flex-1 bg-gray-900 text-white py-2 px-3 rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors size-select-btn"
                                                            data-product-id="{{ $product->id ?? '' }}"
                                                            data-sku-parent="{{ $product->sku_parent ?? '' }}"
                                                            data-product-name="{{ $cleanProductName }}"
                                                            data-price="{{ $finalPrice }}"
                                                            data-original-price="{{ $displayOriginalPrice ?? $finalPrice }}">
                                                        <i class="fas fa-shopping-cart mr-1"></i>
                                                        Select Size
                                                    </button>
                                                @else
                                                    <form action="{{ route('cart.add') }}" method="POST" class="add-to-cart-form flex-1">
                                                        @csrf
                                                        <input type="hidden" name="product_id" value="{{ $product->id ?? '' }}">
                                                        <input type="hidden" name="quantity" value="1">
                                                        <button type="submit" class="w-full bg-gray-900 text-white py-2 px-3 rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors">
                                                            <i class="fas fa-shopping-cart mr-1"></i>
                                                            Add to Cart
                                                        </button>
                                                    </form>
                                                @endif
                                            @else
                                                <button disabled class="flex-1 bg-gray-300 text-gray-500 py-2 px-3 rounded-lg text-sm font-medium cursor-not-allowed">
                                                    <i class="fas fa-times mr-1"></i>
                                                    Out of Stock
                                                </button>
                                            @endif

                                            <a href="{{ route('black-friday.show', $product->slug ?? '#') }}"
                                               class="px-3 py-2 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors flex items-center justify-center">
                                                <i class="fas fa-eye text-gray-600"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <!-- Empty State -->
                        <div class="col-span-full text-center py-12">
                            <i class="fas fa-shoe-prints text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">No products found</h3>
                            <p class="text-gray-500 mb-4">Try adjusting your filters or search terms</p>
                            <button onclick="clearFilters()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                Clear All Filters
                            </button>
                        </div>
                    @endif
                </div>

                <!-- Pagination -->
                @if(isset($products) && $products->hasPages())
                <div class="mt-8">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ (($products->currentPage()) - 1) * $products->perPage() + 1 }} to {{ min($products->currentPage() * $products->perPage(), $products->total()) }} of {{ $products->total() }} results
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            @if($products->currentPage() > 1)
                                <a href="{{ $products->previousPageUrl() }}" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                    Previous
                                </a>
                            @endif
                            
                            @for($i = max(1, $products->currentPage() - 2); $i <= min($products->lastPage(), $products->currentPage() + 2); $i++)
                                <a href="{{ $products->url($i) }}" 
                                   class="px-3 py-2 text-sm font-medium {{ $i == $products->currentPage() ? 'text-white bg-blue-600 border-blue-600' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50' }} border rounded-md">
                                    {{ $i }}
                                </a>
                            @endfor
                            
                            @if($products->currentPage() < $products->lastPage())
                                <a href="{{ $products->nextPageUrl() }}" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                    Next
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
            </main>
        </div>
    </div>

    <!-- Size Selection Modal -->
    <div id="sizeSelectionModal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl max-w-lg w-full mx-auto shadow-2xl transform transition-all">
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 rounded-t-2xl border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900" id="modalProductName">Select Size</h3>
                            <p class="text-sm text-gray-500 mt-1">Choose your preferred size</p>
                        </div>
                        <button id="closeModalBtn" class="text-gray-400 hover:text-gray-600 hover:bg-gray-200 rounded-full p-2 transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Body -->
                <div class="p-6">
                    <div class="mb-6">
                        <div id="sizeOptionsContainer" class="grid grid-cols-4 gap-3">
                            <!-- Size options populated here -->
                        </div>
                    </div>
                    
                    <div class="mb-6 hidden" id="selectedSizeInfo">
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-semibold text-blue-900">Selected Size:</span>
                                <span id="selectedSizeDisplay" class="text-lg font-bold text-blue-700"></span>
                            </div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-blue-600">Available Stock:</span>
                                <span id="selectedSizeStock" class="text-sm font-medium text-blue-700"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-blue-600">Price:</span>
                                <span id="selectedSizePrice" class="text-sm font-semibold text-blue-700">-</span>
                            </div>
                        </div>
                    </div>
                    
                    <form id="sizeAddToCartForm" action="{{ route('cart.add') }}" method="POST" class="hidden">
                        @csrf
                        <input type="hidden" name="product_id" id="selectedProductId">
                        <input type="hidden" name="quantity" value="1">
                        <input type="hidden" name="size" id="selectedSizeValue">
                        
                        <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-4 rounded-xl font-semibold hover:from-blue-700 hover:to-indigo-700 transition-all duration-300 transform hover:scale-105 shadow-lg">
                            <i class="fas fa-shopping-cart mr-2"></i>
                            Add to Cart
                        </button>
                    </form>
                </div>
                
                <div class="bg-gray-50 px-6 py-3 rounded-b-2xl">
                    <p class="text-xs text-center text-gray-500">
                        <i class="fas fa-info-circle mr-1"></i>
                        Select a size to continue with your purchase
                    </p>
                </div>
            </div>
        </div>
    </div>

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
                    <button onclick="hideToast()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('styles')
<style>
    /* Size badge and pricing styles */
    .size-badge {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .size-badge.line-through {
        cursor: not-allowed;
    }

    /* Modal styles */
    #sizeSelectionModal {
        backdrop-filter: blur(8px);
    }
    
    #sizeSelectionModal .relative {
        animation: modalSlideIn 0.3s ease-out;
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: scale(0.9) translateY(-20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }

    .size-option {
        transition: all 0.2s ease;
        min-width: 60px;
    }

    .size-option:hover:not(.disabled) {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .size-option.selected {
        background-color: #3b82f6 !important;
        color: white !important;
        border-color: #3b82f6 !important;
    }

    /* Color classes */
    .text-red-600 { color: #dc2626; }
    .text-green-600 { color: #059669; }
    .text-blue-600 { color: #2563eb; }
</style>
@endpush

@push('scripts')
<script>
// Sort and filter functions
function updateSort(sortValue) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortValue);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function clearFilters() {
    window.location.href = '{{ route("black-friday.index") }}';
}

// Modal and size selection
document.addEventListener('click', function(e) {
    if (e.target.closest('.size-select-btn')) {
        e.preventDefault();
        openSizeModal(e.target.closest('.size-select-btn'));
        return;
    }
    
    if (e.target.id === 'closeModalBtn' || e.target.closest('#closeModalBtn') || e.target.id === 'sizeSelectionModal') {
        closeModal();
        return;
    }
    
    if (e.target.closest('.size-option')) {
        var option = e.target.closest('.size-option');
        if (!option.classList.contains('disabled')) {
            selectSize(option);
        }
        return;
    }
});

function openSizeModal(button) {
    var productId = button.getAttribute('data-product-id');
    var productName = button.getAttribute('data-product-name');
    var defaultPrice = button.getAttribute('data-price') || '0';
    
    var modal = document.getElementById('sizeSelectionModal');
    var title = document.getElementById('modalProductName');
    var container = document.getElementById('sizeOptionsContainer');
    
    if (!modal || !container) return;
    
    if (title) title.textContent = 'Select Size - ' + productName;
    
    var productCard = button.closest('.product-card');
    var sizeContainer = productCard ? productCard.querySelector('#sizeContainer-' + productId) : null;
    
    container.innerHTML = '';
    
    if (sizeContainer) {
        var badges = sizeContainer.querySelectorAll('.size-badge');
        
        badges.forEach(function(badge) {
            var size = badge.getAttribute('data-size');
            var stock = badge.getAttribute('data-stock');
            var productVariantId = badge.getAttribute('data-product-id');
            var available = badge.getAttribute('data-available') === 'true';
            var price = badge.getAttribute('data-price') || defaultPrice;
            var originalPrice = badge.getAttribute('data-original-price') || defaultPrice;
            
            var div = document.createElement('div');
            div.className = 'size-option cursor-pointer p-4 border-2 rounded-lg text-center transition-all ' + 
                (available ? 'border-gray-300 hover:border-blue-500 hover:bg-blue-50' : 'border-gray-200 bg-gray-100 opacity-50 cursor-not-allowed disabled');
            
            div.setAttribute('data-size', size);
            div.setAttribute('data-stock', stock);
            div.setAttribute('data-product-id', productVariantId);
            div.setAttribute('data-available', available);
            div.setAttribute('data-price', price);
            div.setAttribute('data-original-price', originalPrice);
            
            div.innerHTML = 
                '<div class="text-lg font-semibold text-gray-900">' + size + '</div>' +
                '<div class="text-xs mt-1" style="color: ' + (available ? '#059669' : '#dc2626') + ';">' +
                (available ? stock + ' left' : 'Out of stock') + '</div>';
            
            container.appendChild(div);
        });
    }
    
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function selectSize(element) {
    var size = element.getAttribute('data-size');
    var stock = element.getAttribute('data-stock');
    var productId = element.getAttribute('data-product-id');
    var price = element.getAttribute('data-price') || '0';
    
    document.querySelectorAll('.size-option').forEach(function(opt) {
        opt.classList.remove('selected');
        opt.style.backgroundColor = '';
        opt.style.color = '';
        opt.style.borderColor = '';
    });
    
    element.classList.add('selected');
    element.style.backgroundColor = '#3b82f6';
    element.style.color = 'white';
    element.style.borderColor = '#3b82f6';
    
    var sizeInfo = document.getElementById('selectedSizeInfo');
    var sizeDisplay = document.getElementById('selectedSizeDisplay');
    var sizeStock = document.getElementById('selectedSizeStock');
    var sizePriceElement = document.getElementById('selectedSizePrice');
    var form = document.getElementById('sizeAddToCartForm');
    var productInput = document.getElementById('selectedProductId');
    var sizeInput = document.getElementById('selectedSizeValue');
    
    if (sizeDisplay) sizeDisplay.textContent = size;
    if (sizeStock) sizeStock.textContent = stock + ' available';
    
    if (sizePriceElement) {
        var formattedPrice = 'Rp ' + new Intl.NumberFormat('id-ID').format(parseInt(price));
        sizePriceElement.textContent = formattedPrice;
    }
    
    if (productInput) productInput.value = productId;
    if (sizeInput) sizeInput.value = size;
    
    if (sizeInfo) sizeInfo.classList.remove('hidden');
    if (form) form.classList.remove('hidden');
}

function closeModal() {
    var modal = document.getElementById('sizeSelectionModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    var form = document.getElementById('sizeAddToCartForm');
    var sizeInfo = document.getElementById('selectedSizeInfo');
    if (form) form.classList.add('hidden');
    if (sizeInfo) sizeInfo.classList.add('hidden');
    
    document.querySelectorAll('.size-option').forEach(function(opt) {
        opt.classList.remove('selected');
        opt.style.backgroundColor = '';
        opt.style.color = '';
        opt.style.borderColor = '';
    });
}

// Toast functions
function showToast(message, type = 'success') {
    const toast = document.getElementById('toastNotification');
    const icon = document.getElementById('toastIcon');
    const messageEl = document.getElementById('toastMessage');
    if (!toast || !icon || !messageEl) return;

    messageEl.textContent = message;
    icon.className = 'fas ';
    
    switch(type) {
        case 'success': icon.className += 'fa-check-circle text-green-500'; break;
        case 'error': icon.className += 'fa-exclamation-circle text-red-500'; break;
        case 'info': icon.className += 'fa-info-circle text-blue-500'; break;
        default: icon.className += 'fa-check-circle text-green-500';
    }

    toast.classList.remove('hidden');
    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(hideToast, 3000);
}

function hideToast() {
    const toast = document.getElementById('toastNotification');
    if (toast) toast.classList.add('hidden');
}

// Wishlist functionality
const WISHLIST_CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

document.addEventListener('click', function (ev) {
    const btn = ev.target.closest('.wishlist-btn');
    if (!btn) return;

    ev.preventDefault();
    ev.stopPropagation();

    const productId = btn.dataset.productId;
    const productName = btn.dataset.productName || 'Product';
    const icon = btn.querySelector('.wishlist-icon') || btn.querySelector('i');

    if (!productId) return;
    btn.disabled = true;

    fetch(`/wishlist/toggle/${productId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': WISHLIST_CSRF,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => r.ok ? r.json() : Promise.reject(r))
    .then(data => {
        if (data && data.redirect) {
            window.location.href = data.redirect;
            return;
        }

        if (!data || data.success === false) {
            showToast((data && data.message) || 'Failed to update wishlist', 'error');
            return;
        }

        const added = !!data.is_added;

        if (icon) {
            icon.classList.toggle('fas', added);
            icon.classList.toggle('far', !added);
            icon.style.color = added ? '#ef4444' : '';
        }

        if ('wishlist_count' in data) {
            document.querySelectorAll('[data-wishlist-count], .wishlist-badge')
                .forEach(el => el.textContent = data.wishlist_count);
        }

        showToast(`${productName} ${added ? 'added to' : 'removed from'} wishlist`,
                  added ? 'success' : 'info');
    })
    .catch(() => {
        showToast('Error updating wishlist.', 'error');
    })
    .finally(() => {
        btn.disabled = false;
    });
});
</script>
@endpush