@extends('layouts.app')

@section('title', 'Products - SneakerFlash')

@section('content')
@php
// Function to get product URL with smallest size - FIXED VERSION
if (!function_exists('getProductUrlWithSize')) {
    function getProductUrlWithSize($product) {
        $originalSlug = $product->slug ?? '#';
        
        // ⭐ FIX: Remove existing size pattern from slug first
        $baseSlug = $originalSlug;
        if (preg_match('/^(.+)-size-(\d+(?:\.\d+)?)$/', $originalSlug, $matches)) {
            $baseSlug = $matches[1]; // Extract base slug without size
        }
        
        // Collect all available sizes
        $allSizes = collect();
        
        // Add current product sizes
        if (isset($product->available_sizes) && is_array($product->available_sizes)) {
            foreach ($product->available_sizes as $size) {
                $allSizes->push(floatval($size));
            }
        }
        
        // Add size variants if exists
        if (isset($product->size_variants) && is_array($product->size_variants)) {
            foreach ($product->size_variants as $variant) {
                if (isset($variant['size'])) {
                    $allSizes->push(floatval($variant['size']));
                }
            }
        } elseif (isset($product->size_variants) && method_exists($product->size_variants, 'pluck')) {
            $variantSizes = $product->size_variants->pluck('size')->map(function($size) {
                return floatval($size);
            });
            $allSizes = $allSizes->merge($variantSizes);
        }
        
        // Get smallest size and create URL
        if ($allSizes->isNotEmpty()) {
            $smallestSize = $allSizes->min();
            return route('products.show', $baseSlug . '-size-' . $smallestSize);
        }
        
        // Fallback to base slug (without size)
        return route('products.show', $baseSlug);
    }
}
@endphp
    <!-- Page Header -->
    <section class="bg-white py-6 border-b border-gray-200">
        <div class="container mx-auto px-4">
            <nav class="text-sm mb-4">
    <ol class="flex space-x-2 text-gray-600">
        <li><a href="/" class="hover:text-blue-600">Home</a></li>
        <li>/</li>

        @if(request('category'))
            <li><a href="{{ route('products.index') }}" class="hover:text-blue-600">Products</a></li>
            <li>/</li>
            <li class="text-gray-900 uppercase">{{ strtoupper(request('category')) }}</li>

            @if(request('type'))
                <li>/</li>
                <li class="text-gray-900 capitalize">
                    {{ ucfirst(str_replace('_', ' ', request('type'))) }}
                </li>
            @endif

        @else
            <li><a href="{{ route('products.index') }}" class="hover:text-blue-600">Products</a></li>

            @if(request()->has('brands'))
                <li>/</li>
                <li class="text-gray-900 capitalize">
                    {{ implode(', ', array_map('ucwords', request('brands'))) }}
                </li>
            @endif
        @endif
    </ol>
</nav>
     
        </div>
    </section>

    <div class="container mx-auto px-4 py-8">
        <div class="flex gap-8">
            <!-- Filters Sidebar -->
            <aside id="filterSidebar" class="w-72 flex-shrink-0 hidden">
                <div class="bg-white rounded-2xl p-6 border border-gray-100">
                    <h3 class="font-semibold text-gray-900 mb-4">Filters</h3>
                    <!-- Add your filter content here -->
                </div>
            </aside>

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
                    @if(isset($paginatedProducts) && $paginatedProducts->count() > 0)
                        @foreach($paginatedProducts as $product)
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
                                
                                // Safe data handling
                                $productImages = is_array($product->images ?? null) ? $product->images : [];
                                $sizeVariants = [];
                                
                                if (isset($product->size_variants)) {
                                    if (is_array($product->size_variants)) {
                                        $sizeVariants = $product->size_variants;
                                    } elseif (is_object($product->size_variants)) {
                                        $sizeVariants = method_exists($product->size_variants, 'toArray') 
                                            ? $product->size_variants->toArray() 
                                            : (array) $product->size_variants;
                                    }
                                }
                                
                                $hasMultipleSizes = count($sizeVariants) > 1;
                                $productPrice = $product->price ?? 0;
                                $salePrice = $product->sale_price ?? null;
                                $totalStock = $product->total_stock ?? 0;
                            @endphp
                            
                            <div class="product-card {{ $totalStock <= 0 ? 'out-of-stock' : '' }} bg-white rounded-2xl overflow-hidden border border-gray-100 hover:shadow-lg transition-all duration-300 group h-full max-h-[540px] flex flex-col"
     data-product-id="{{ $product->id ?? '' }}"
     data-sku-parent="{{ $product->sku_parent ?? '' }}"
     data-product-name="{{ $cleanProductName }}">

                                
                                <!-- Product Image -->
                                <div class="relative bg-gray-50 overflow-hidden flex items-center justify-center h-[260px] md:h-[300px]">
    @if(!empty($productImages))
        <a href="{{ getProductUrlWithSize($product) }}">
            <img src="{{ $productImages[0] }}" 
                 alt="{{ $cleanProductName }}"
                 class="max-w-full max-h-full object-contain p-2 transition-transform duration-300 group-hover:scale-105"
                 loading="lazy">
        </a>
    @else
        <div class="w-full h-full flex items-center justify-center bg-gray-100">
            <i class="fas fa-shoe-prints text-4xl text-gray-300"></i>
        </div>
    @endif
                                    
                                    <!-- Product Badges -->
                                    <div class="absolute top-3 left-3 flex flex-col gap-2">
                                        @if($product->is_featured ?? false)
                                            <span class="bg-yellow-500 text-white text-xs px-2 py-1 rounded-full font-medium">
                                                Featured
                                            </span>
                                        @endif
                                        @if($salePrice && $salePrice < $productPrice)
                                            @php
                                                $discount = round((($productPrice - $salePrice) / $productPrice) * 100);
                                            @endphp
                                            <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full font-medium">
                                                -{{ $discount }}%
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
                                    
                                    {{-- ⭐ FIXED: Use clean product name --}}
                                    <h3 class="font-semibold text-gray-900 mb-3 text-sm leading-tight">
                                        <a href="{{ getProductUrlWithSize($product) }}" 
   class="hover:text-blue-600 transition-colors">
                                            {{ $cleanProductName }}
                                        </a>
                                    </h3>
                                    
                                    <!-- Size Options -->
                                    @if($hasMultipleSizes)
                                        <div class="mb-3">
                                            <span class="text-xs text-gray-500 font-medium">Available Sizes:</span>
                                            <div class="flex flex-wrap gap-1 mt-1" id="sizeContainer-{{ $product->id }}">
                                                @foreach($sizeVariants as $variant)
                                                    @php
                                                        $variantData = is_array($variant) ? $variant : (array) $variant;
                                                        $size = $variantData['size'] ?? 'Unknown';
                                                        $stock = (int) ($variantData['stock'] ?? 0);
                                                        $variantId = $variantData['id'] ?? '';
                                                        $sku = $variantData['sku'] ?? '';
                                                        $isAvailable = $stock > 0;
                                                        // 🔥 TAMBAHKAN PRICE DATA
                                                        $variantPrice = $variantData['price'] ?? $productPrice;
                                                        $variantOriginalPrice = $variantData['original_price'] ?? $productPrice;
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
                                    
                                    <!-- Price -->
                                    <div class="mb-4 price-display">
                                        @if($salePrice && $salePrice < $productPrice)
                                            <div class="flex items-center space-x-2">
                                                <span class="text-lg font-bold text-red-600">
                                                    Rp {{ number_format($salePrice, 0, ',', '.') }}
                                                </span>
                                                <span class="text-sm text-gray-400 line-through">
                                                    Rp {{ number_format($productPrice, 0, ',', '.') }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="text-lg font-bold text-gray-900">
                                                Rp {{ number_format($productPrice, 0, ',', '.') }}
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
                                    
                                    <!-- Action Buttons (pin ke bawah) -->
                                    <div class="mt-auto">   {{-- <- kunci: dorong block ini ke paling bawah --}}
                                        <div class="flex gap-2">
                                            @if($totalStock > 0)
                                                @if($hasMultipleSizes)
                                                    <button type="button"
                                                            class="flex-1 bg-gray-900 text-white py-2 px-3 rounded-lg text-sm font-medium hover:bg-gray-800 transition-colors size-select-btn"
                                                            data-product-id="{{ $product->id ?? '' }}"
                                                            data-sku-parent="{{ $product->sku_parent ?? '' }}"
                                                            data-product-name="{{ $cleanProductName }}"
                                                            data-price="{{ $salePrice ?: $productPrice }}"
                                                            data-original-price="{{ $productPrice }}">
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

                                            <a href="{{ getProductUrlWithSize($product) }}"
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
                @if(isset($total) && $total > 0)
                <div class="mt-8">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            Showing {{ (($currentPage ?? 1) - 1) * ($perPage ?? 12) + 1 }} to {{ min(($currentPage ?? 1) * ($perPage ?? 12), $total) }} of {{ $total }} results
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            @if(($currentPage ?? 1) > 1)
                                <a href="{{ request()->fullUrlWithQuery(['page' => ($currentPage ?? 1) - 1]) }}" 
                                   class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                    Previous
                                </a>
                            @endif
                            
                            @for($i = max(1, ($currentPage ?? 1) - 2); $i <= min(($lastPage ?? 1), ($currentPage ?? 1) + 2); $i++)
                                <a href="{{ request()->fullUrlWithQuery(['page' => $i]) }}" 
                                   class="px-3 py-2 text-sm font-medium {{ $i == ($currentPage ?? 1) ? 'text-white bg-blue-600 border-blue-600' : 'text-gray-500 bg-white border-gray-300 hover:bg-gray-50' }} border rounded-md">
                                    {{ $i }}
                                </a>
                            @endfor
                            
                            @if(($currentPage ?? 1) < ($lastPage ?? 1))
                                <a href="{{ request()->fullUrlWithQuery(['page' => ($currentPage ?? 1) + 1]) }}" 
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

    <!-- Enhanced Size Selection Modal -->
    <div id="sizeSelectionModal" class="fixed inset-0 z-50 hidden">
        <!-- Background Overlay -->
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        
        <!-- Modal Container -->
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
                    <!-- Size Options Grid -->
                    <div class="mb-6">
                        <div id="sizeOptionsContainer" class="grid grid-cols-4 gap-3">
                            <!-- Size options will be populated here -->
                        </div>
                    </div>
                    
                    <!-- Selected Size Info -->
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
                    
                    <!-- Add to Cart Form -->
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
                
                <!-- Modal Footer -->
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

    <!-- ENHANCED JAVASCRIPT - Size Selection with Clean Product Names -->
<!-- ENHANCED JAVASCRIPT - Size Selection with Clean Product Names -->
<script>
console.log('🚀 Enhanced JavaScript with Price Support...');

window.addEventListener('load', function() {
    setupSizeSelection();
});

// ⭐ BARU: Fungsi untuk update sort by (mengatasi masalah dropdown tidak berjalan)
function updateSort(sortValue) {
    console.log('🔄 Updating sort to:', sortValue);
    
    // Buat URL baru berdasarkan current URL
    const url = new URL(window.location.href);
    
    // Set parameter sort baru
    url.searchParams.set('sort', sortValue);
    
    // Reset page ke 1 saat sort berubah (standar UX, agar tidak stuck di page lama)
    url.searchParams.delete('page');
    
    // Redirect ke URL baru (reload halaman dengan sorting baru)
    window.location.href = url.toString();
}

// ⭐ BARU: Fungsi clear filters (untuk empty state button)
function clearFilters() {
    console.log('🧹 Clearing all filters...');
    
    // Redirect ke products index tanpa parameter apa pun
    window.location.href = '{{ route("products.index") }}';
}

function setupSizeSelection() {
    document.addEventListener('click', handleClick);
}

function handleClick(e) {
    // Size select button
    if (e.target.closest('.size-select-btn')) {
        e.preventDefault();
        openSizeModal(e.target.closest('.size-select-btn'));
        return;
    }
    
    // Close modal
    if (e.target.id === 'closeModalBtn' || e.target.closest('#closeModalBtn') || e.target.id === 'sizeSelectionModal') {
        closeModal();
        return;
    }
    
    // Size option
    if (e.target.closest('.size-option')) {
        var option = e.target.closest('.size-option');
        if (!option.classList.contains('disabled')) {
            selectSize(option);
        }
        return;
    }
}

function openSizeModal(button) {
    var productId = button.getAttribute('data-product-id');
    var productName = button.getAttribute('data-product-name');
    var defaultPrice = button.getAttribute('data-price') || '0';
    
    console.log('Opening modal for:', productName, 'Price:', defaultPrice);
    
    var modal = document.getElementById('sizeSelectionModal');
    var title = document.getElementById('modalProductName');
    var container = document.getElementById('sizeOptionsContainer');
    
    if (!modal || !container) return;
    
    // Set title
    if (title) title.textContent = 'Select Size - ' + productName;
    
    // Get sizes from product card
    var productCard = button.closest('.product-card');
    var sizeContainer = productCard ? productCard.querySelector('#sizeContainer-' + productId) : null;
    
    // Clear container
    container.innerHTML = '';
    
    if (sizeContainer) {
        var badges = sizeContainer.querySelectorAll('.size-badge');
        
        badges.forEach(function(badge) {
            var size = badge.getAttribute('data-size');
            var stock = badge.getAttribute('data-stock');
            var productVariantId = badge.getAttribute('data-product-id');
            var available = badge.getAttribute('data-available') === 'true';
            
            // 🔥 GET PRICE from badge
            var price = badge.getAttribute('data-price') || defaultPrice;
            var originalPrice = badge.getAttribute('data-original-price') || defaultPrice;
            
            var div = document.createElement('div');
            div.className = 'size-option cursor-pointer p-4 border-2 rounded-lg text-center transition-all ' + 
                (available ? 'border-gray-300 hover:border-blue-500 hover:bg-blue-50' : 'border-gray-200 bg-gray-100 opacity-50 cursor-not-allowed disabled');
            
            // Set data attributes INCLUDING PRICE
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
            
            console.log('Size option created:', size, 'Price:', price);
        });
    }
    
    // Show modal
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function selectSize(element) {
    var size = element.getAttribute('data-size');
    var stock = element.getAttribute('data-stock');
    var productId = element.getAttribute('data-product-id');
    var price = element.getAttribute('data-price') || '0';
    
    console.log('Size selected:', size, 'Price:', price);
    
    // Clear selections
    document.querySelectorAll('.size-option').forEach(function(opt) {
        opt.classList.remove('selected');
        opt.style.backgroundColor = '';
        opt.style.color = '';
        opt.style.borderColor = '';
    });
    
    // Mark selected
    element.classList.add('selected');
    element.style.backgroundColor = '#3b82f6';
    element.style.color = 'white';
    element.style.borderColor = '#3b82f6';
    
    // Update UI elements
    var sizeInfo = document.getElementById('selectedSizeInfo');
    var sizeDisplay = document.getElementById('selectedSizeDisplay');
    var sizeStock = document.getElementById('selectedSizeStock');
    var sizePriceElement = document.getElementById('selectedSizePrice');
    var form = document.getElementById('sizeAddToCartForm');
    var productInput = document.getElementById('selectedProductId');
    var sizeInput = document.getElementById('selectedSizeValue');
    
    if (sizeDisplay) sizeDisplay.textContent = size;
    if (sizeStock) sizeStock.textContent = stock + ' available';
    
    // 🔥 UPDATE PRICE - INI YANG PALING PENTING
    if (sizePriceElement) {
        var formattedPrice = 'Rp ' + new Intl.NumberFormat('id-ID').format(parseInt(price));
        sizePriceElement.textContent = formattedPrice;
        console.log('💰 Price updated to:', formattedPrice);
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
    
    // Reset form
    var form = document.getElementById('sizeAddToCartForm');
    var sizeInfo = document.getElementById('selectedSizeInfo');
    if (form) form.classList.add('hidden');
    if (sizeInfo) sizeInfo.classList.add('hidden');
    
    // Clear selections
    document.querySelectorAll('.size-option').forEach(function(opt) {
        opt.classList.remove('selected');
        opt.style.backgroundColor = '';
        opt.style.color = '';
        opt.style.borderColor = '';
    });
}

const WISHLIST_CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

// Delegasi klik untuk semua tombol wishlist di grid
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
        // jika backend mengirim redirect (belum login)
        if (data && data.redirect) {
            window.location.href = data.redirect;
            return;
        }

        if (!data || data.success === false) {
            showToast((data && data.message) || 'Gagal mengubah wishlist', 'error');
            return;
        }

        const added = !!data.is_added;

        // Toggle ikon hati (solid merah saat added)
        if (icon) {
            icon.classList.toggle('fas', added);
            icon.classList.toggle('far', !added);
            icon.style.color = added ? '#ef4444' : '';
        }

        // Update badge jumlah wishlist (opsional jika backend kirim)
        if ('wishlist_count' in data) {
            document.querySelectorAll('[data-wishlist-count], .wishlist-badge')
                .forEach(el => el.textContent = data.wishlist_count);
        }

        showToast(`${productName} ${added ? 'ditambahkan ke' : 'dihapus dari'} wishlist`,
                  added ? 'success' : 'info');
    })
    .catch(() => {
        showToast('Terjadi kesalahan saat toggle wishlist.', 'error');
    })
    .finally(() => {
        btn.disabled = false;
    });
});

// ==== Toast utilities (pakai elemen #toastNotification yang sudah ada) ====
function showToast(message, type = 'success') {
    const toast = document.getElementById('toastNotification');
    const icon = document.getElementById('toastIcon');
    const messageEl = document.getElementById('toastMessage');
    if (!toast || !icon || !messageEl) return;

    messageEl.textContent = message;

    icon.className = 'fas ';
    switch(type) {
        case 'success': icon.className += 'fa-check-circle text-green-500'; break;
        case 'error':   icon.className += 'fa-exclamation-circle text-red-500'; break;
        case 'info':    icon.className += 'fa-info-circle text-blue-500'; break;
        default:        icon.className += 'fa-check-circle text-green-500';
    }

    toast.classList.remove('hidden');
    // auto-hide
    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(hideToast, 3000);
}

function hideToast() {
    const toast = document.getElementById('toastNotification');
    if (toast) toast.classList.add('hidden');
}
</script>

@endsection

@push('styles')
<style>
    /* Category Pills */
    .category-pill {
        display: inline-flex;
        align-items: center;
        padding: 8px 16px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        color: #6c757d;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .category-pill:hover {
        background: #e9ecef;
        color: #495057;
        border-color: #adb5bd;
    }

    .category-pill.active {
        background: #000000;
        color: #ffffff;
        border-color: #000000;
    }

    .category-pill.special {
        color: #ff4757;
        border-color: #ff4757;
    }

    .category-pill.special.active {
        background: #ff4757;
        color: #ffffff;
    }

    /* Size Badge Styles */
    .size-badge {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .size-badge.line-through {
        cursor: not-allowed;
    }

    /* Enhanced Size Selection Modal Styles */
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

    /* Size Options Connected with Separator */
    #sizeOptionsContainer {
        display: flex;
        justify-content: center;
        max-width: 300px;
        margin: 0 auto;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .size-option {
        flex: 1;
        padding: 16px 12px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
        background: white;
        min-height: 60px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        position: relative;
        border: none;
        border-radius: 0;
    }

    /* Separator line between options */
    .size-option:not(:last-child)::after {
        content: '';
        position: absolute;
        right: 0;
        top: 10%;
        bottom: 10%;
        width: 2px;
        background-color: #d1d5db;
        transition: background-color 0.2s ease;
    }

    .size-option:hover:not(.disabled) {
        background-color: #f0f9ff;
        color: #1d4ed8;
    }

    .size-option:hover:not(.disabled):not(:last-child)::after {
        background-color: #3b82f6;
    }

    .size-option.selected {
        background: #2563eb !important;
        color: white !important;
    }

    .size-option.selected:not(:last-child)::after {
        background-color: #1d4ed8;
    }

    .size-option.selected .size-label {
        color: white !important;
        font-weight: 700;
    }

    .size-option.selected .stock-info {
        color: rgba(255, 255, 255, 0.9) !important;
    }

    .size-option.disabled {
        background-color: #f9fafb;
        color: #9ca3af;
        cursor: not-allowed;
        opacity: 0.6;
    }

    .size-option.disabled:not(:last-child)::after {
        background-color: #e5e7eb;
    }

    .size-option .size-label {
        font-weight: 600;
        font-size: 16px;
        margin-bottom: 4px;
        letter-spacing: 0.5px;
    }

    .size-option .stock-info {
        font-size: 11px;
        font-weight: 500;
        color: #6b7280;
    }
    
    /* Mobile adjustments */
    @media (max-width: 640px) {
        #sizeSelectionModal .relative {
            margin: 1rem;
            max-width: none;
        }
        
        #sizeOptionsContainer {
            max-width: 250px;
        }
        
        .size-option {
            min-height: 50px;
            padding: 12px 8px;
        }
        
        .size-option .size-label {
            font-size: 14px;
        }
        
        .size-option .stock-info {
            font-size: 10px;
        }
    }
    
    /* Mobile adjustments */
    @media (max-width: 640px) {
        #sizeSelectionModal .relative {
            margin: 1rem;
            max-width: none;
        }
        
        #sizeOptionsContainer {
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        
        .size-option {
            min-height: 70px;
            padding: 12px 6px;
        }
        
        .size-option .size-label {
            font-size: 14px;
        }
    }

    /* Wishlist button active state */
    .wishlist-btn.active {
        background-color: #fef2f2;
        border-color: #fecaca;
    }

    /* Color classes */
    .text-green-600 { color: #059669; }
    .text-red-600 { color: #dc2626; }
    .text-blue-600 { color: #2563eb; }
    .text-blue-700 { color: #1d4ed8; }
    .text-blue-900 { color: #1e3a8a; }
    /* Out of Stock Card Dimmed Effect */
.product-card.out-of-stock {
    opacity: 0.5;
    position: relative;
}

.product-card.out-of-stock::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.15);
    z-index: 1;
    pointer-events: none;
    border-radius: 1rem;
}

.product-card.out-of-stock img {
    filter: grayscale(80%) brightness(0.85) !important;
}

.product-card.out-of-stock:hover {
    box-shadow: none !important;
    transform: none !important;
}

.product-card.out-of-stock:hover img {
    transform: scale(1) !important;
}

/* Ensure all child elements respect the dimming */
.product-card.out-of-stock * {
    position: relative;
    z-index: 2;
}

    /* Toast Animation */
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .fixed.top-4.right-4 { animation: slideInRight 0.3s ease-out; }
</style>
@endpush