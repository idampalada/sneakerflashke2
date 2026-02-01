@extends('layouts.app')

@section('title', 'SneakersFlash - Premium Sneakers for Everyone')

@section('content')

    <!-- Best Sellers Section - Horizontal Scroll -->
<section class="pt-0 pb-0 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4">Best Sellers</h2>
            <p class="text-gray-600 text-lg">Most popular products from our customers</p>
        </div>

        @php
            // Get products without complex joins first
            try {
                $bestSellersQuery = \App\Models\Product::where('is_active', true)
                    ->where('stock_quantity', '>', 0)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->orderByDesc('is_featured')
                    ->orderByDesc('created_at')
                    ->get();

                // GROUP BY SKU_PARENT to remove duplicates
                $bestSellers = $bestSellersQuery->groupBy('sku_parent')->map(function ($productGroup, $skuParent) {
                    if (empty($skuParent)) {
                        return $productGroup->first(); // Individual products
                    }
                    
                    // For grouped products, return the representative
                    $representative = $productGroup->first();
                    
                    // Clean product name
                    if (!empty($skuParent)) {
                        $cleanName = preg_replace('/\s*-\s*' . preg_quote($skuParent, '/') . '\s*/', '', $representative->name);
                        $cleanName = preg_replace('/\s*-\s*Size\s+[A-Z0-9.]+\s*$/i', '', $cleanName);
                        $cleanName = preg_replace('/\s*Size\s+[A-Z0-9.]+\s*$/i', '', $cleanName);
                        $representative->name = trim($cleanName, ' -');
                    }
                    
                    return $representative;
                })->values()->take(10); // Ambil lebih banyak untuk scroll
            } catch (\Exception $e) {
                $bestSellers = collect(); // Empty collection on error
            }
        @endphp

        @if($bestSellers && $bestSellers->isNotEmpty())
            <!-- Horizontal Scrollable Products -->
            <div class="relative">
                <div class="best-sellers-scroll overflow-x-auto scrollbar-hide">
                    <div class="flex space-x-3 pb-4 pr-4" style="width: max-content;">
                        @foreach($bestSellers as $product)
                            <div class="product-card-horizontal bg-white rounded-lg border border-gray-200 overflow-hidden hover:border-gray-300 transition-colors flex-shrink-0 relative">
                                <!-- Sale Badge -->
                                @php
                                    $hasDiscount = (isset($product->discount_percentage) && $product->discount_percentage > 0);
                                    $hasSalePrice = (isset($product->sale_price) && $product->sale_price && isset($product->price) && $product->sale_price < $product->price);
                                @endphp
                                @if($hasDiscount || $hasSalePrice)
                                    <div class="absolute top-2 left-2 z-10">
                                        <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded">
                                            Sale
                                        </span>
                                    </div>
                                @endif

                                <div class="relative">
                                    <a href="/products/{{ $product->slug ?? 'product-' . $product->id }}">
                                        @php
                                            // Handle different image formats safely
                                            $hasImages = false;
                                            $imageUrl = '';
                                            
                                            // Check if images is a Collection with count method
                                            if (isset($product->images) && is_object($product->images) && method_exists($product->images, 'count') && $product->images->count() > 0) {
                                                $hasImages = true;
                                                $imageUrl = asset('storage/' . $product->images->first()->image_path);
                                            }
                                            // Check if images is an array
                                            elseif (isset($product->images) && is_array($product->images) && count($product->images) > 0) {
                                                $hasImages = true;
                                                $firstImage = $product->images[0];
                                                $imageUrl = filter_var($firstImage, FILTER_VALIDATE_URL) 
                                                    ? $firstImage 
                                                    : asset('storage/' . ltrim($firstImage, '/'));
                                            }
                                        @endphp
                                        
                                        @if($hasImages)
                                            <img src="{{ $imageUrl }}" 
                                                 alt="{{ $product->name ?? 'Product' }}" 
                                                 class="w-full h-48 object-cover"
                                                 onerror="this.src='{{ asset('images/default-product.png') }}'">
                                        @else
                                            <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                                                <i class="fas fa-image text-2xl text-gray-400"></i>
                                            </div>
                                        @endif
                                    </a>
                                </div>
                                
                                <div class="p-4">
                                    <h3 class="font-medium text-gray-900 mb-2 text-sm line-clamp-2" title="{{ $product->name ?? 'Product' }}">
                                        {{ $product->name ?? 'Product' }}
                                    </h3>
                                    
                                    <!-- Price -->
                                    <div class="flex flex-col">
                                        @if(isset($product->sale_price) && $product->sale_price && isset($product->price) && $product->sale_price < $product->price)
                                            <div class="flex items-center space-x-2">
                                                <span class="text-sm font-bold text-red-600">
                                                    Rp {{ number_format($product->sale_price, 0, ',', '.') }}
                                                </span>
                                                <span class="text-xs text-gray-500 line-through">
                                                    Rp {{ number_format($product->price, 0, ',', '.') }}
                                                </span>
                                            </div>
                                        @elseif(isset($product->price))
                                            <span class="text-sm font-bold text-gray-900">
                                                Rp {{ number_format($product->price, 0, ',', '.') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <!-- Fallback Best Sellers -->
            <div class="relative">
                <div class="best-sellers-scroll overflow-x-auto scrollbar-hide">
                    <div class="flex space-x-3 pb-4 pr-4" style="width: max-content;">
                        @for($i = 1; $i <= 8; $i++)
                            <div class="product-card-horizontal bg-white rounded-lg border border-gray-200 overflow-hidden flex-shrink-0">
                                <div class="relative">
                                    <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                                        <i class="fas fa-image text-2xl text-gray-400"></i>
                                    </div>
                                </div>
                                
                                <div class="p-4">
                                    <h3 class="font-medium text-gray-900 mb-2 text-sm">Best Product {{ $i }}</h3>
                                    <span class="text-sm font-bold text-gray-900">
                                        Rp {{ number_format(rand(400000, 1500000), 0, ',', '.') }}
                                    </span>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        @endif

<div class="text-center mt-12">
    <a href="/products" class="inline-block bg-black text-white px-8 py-3 rounded-lg hover:bg-gray-800 transition-colors font-medium">
        View All Best Sellers
    </a>
</div>
    </div>
</section>

<style>
/* Best Sellers Horizontal Scroll Styles */
.best-sellers-scroll {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none; /* Firefox */
    scroll-behavior: smooth;
}

.best-sellers-scroll::-webkit-scrollbar {
    display: none; /* Chrome, Safari */
}

.scrollbar-hide {
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

.product-card-horizontal {
    width: 180px; /* Fixed width untuk setiap card */
    min-width: 180px;
}

/* Mobile: Tampilkan 2 produk + sedikit produk ke-3 */
@media (max-width: 640px) {
    .best-sellers-scroll {
        padding-left: 0.5rem;
        padding-right: 1rem;
        margin-left: -0.5rem;
        margin-right: -1rem;
    }
    
    .product-card-horizontal {
        width: calc(45vw - 1rem); /* Lebih kecil agar produk ke-3 terlihat */
        min-width: calc(45vw - 1rem);
        max-width: 160px;
    }
    
    .flex.space-x-4 {
        padding-left: 0.5rem; /* Kurangi padding kiri */
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

/* Line clamp utility */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>

    <!-- Featured Products - Horizontal Scroll -->
<section class="pt-16 pb-0 bg-gray">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4">Featured Products</h2>
            <p class="text-gray-600 text-lg">Hand-picked favorites from our collection</p>
        </div>
        
        @if(isset($featuredProducts) && $featuredProducts->count() > 0)
            <!-- Horizontal Scrollable Products -->
            <div class="relative">
                <div class="featured-products-scroll overflow-x-auto scrollbar-hide">
                    <div class="flex space-x-3 pb-4 pr-4" style="width: max-content;">
                        @foreach($featuredProducts as $product)
                            <div class="product-card-horizontal bg-white rounded-lg border border-gray-200 overflow-hidden hover:border-gray-300 transition-colors flex-shrink-0 relative">
                                <!-- Sale Badge -->
                                @php
                                    $hasDiscount = (isset($product->discount_percentage) && $product->discount_percentage > 0);
                                    $hasSalePrice = (isset($product->sale_price) && $product->sale_price && isset($product->price) && $product->sale_price < $product->price);
                                @endphp
                                @if($hasDiscount || $hasSalePrice)
                                    <div class="absolute top-2 left-2 z-10">
                                        <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded">
                                            Sale
                                        </span>
                                    </div>
                                @endif

                                <div class="relative">
                                    <a href="/products/{{ $product->slug ?? 'product-' . $product->id }}">
                                        @if(isset($product->images) && is_array($product->images) && count($product->images) > 0)
                                            @php
                                                $firstImage = $product->images[0];
                                                $imageUrl = filter_var($firstImage, FILTER_VALIDATE_URL) 
                                                    ? $firstImage 
                                                    : asset('storage/' . ltrim($firstImage, '/'));
                                            @endphp
                                            <img src="{{ $imageUrl }}" 
                                                 alt="{{ $product->name ?? 'Product' }}"
                                                 class="w-full h-40 sm:h-44 object-cover"
                                                 loading="lazy"
                                                 onerror="this.src='{{ asset('images/default-product.jpg') }}'">
                                        @elseif(isset($product->featured_image) && $product->featured_image)
                                            @php
                                                $imageUrl = filter_var($product->featured_image, FILTER_VALIDATE_URL) 
                                                    ? $product->featured_image 
                                                    : asset('storage/' . ltrim($product->featured_image, '/'));
                                            @endphp
                                            <img src="{{ $imageUrl }}" 
                                                 alt="{{ $product->name ?? 'Product' }}"
                                                 class="w-full h-40 sm:h-44 object-cover"
                                                 loading="lazy"
                                                 onerror="this.src='{{ asset('images/default-product.jpg') }}'">
                                        @else
                                            <div class="w-full h-40 sm:h-44 bg-gray-200 flex items-center justify-center">
                                                <i class="fas fa-image text-3xl text-gray-400"></i>
                                            </div>
                                        @endif
                                    </a>
                                </div>

                                <div class="p-3">
                                    <a href="/products/{{ $product->slug ?? 'product-' . $product->id }}">
                                        @php
                                            // Clean product name
                                            $cleanName = $product->name ?? 'Unknown Product';
                                            $skuParent = $product->sku_parent ?? '';
                                            
                                            if (!empty($skuParent)) {
                                                $cleanName = preg_replace('/\s*-\s*' . preg_quote($skuParent, '/') . '\s*/', '', $cleanName);
                                                $cleanName = preg_replace('/\s*-\s*Size\s+[A-Z0-9.]+\s*$/i', '', $cleanName);
                                                $cleanName = preg_replace('/\s*Size\s+[A-Z0-9.]+\s*$/i', '', $cleanName);
                                                $cleanName = trim($cleanName, ' -');
                                            }
                                        @endphp
                                        <h3 class="font-medium text-gray-900 text-sm line-clamp-2 mb-2 hover:text-blue-600 transition-colors">
                                            {{ $cleanName }}
                                        </h3>
                                    </a>

                                    <div class="space-y-2">
                                        <!-- Price -->
                                        <div class="flex items-center space-x-2">
                                            @if($hasSalePrice)
                                                <span class="text-sm font-bold text-red-600">
                                                    Rp {{ number_format($product->sale_price, 0, ',', '.') }}
                                                </span>
                                                <span class="text-xs text-gray-400 line-through">
                                                    Rp {{ number_format($product->price, 0, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-sm font-bold text-gray-900">
                                                    Rp {{ number_format($product->price ?? 0, 0, ',', '.') }}
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Stock Status -->
                                        @if(isset($product->stock_quantity))
                                            @if($product->stock_quantity > 0)
                                                <p class="text-xs text-green-600">
                                                    <i class="fas fa-check-circle mr-1"></i>
                                                    In stock
                                                </p>
                                            @else
                                                <p class="text-xs text-red-600">
                                                    <i class="fas fa-times-circle mr-1"></i>
                                                    Out of stock
                                                </p>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <!-- Fallback: Display sample products for Featured -->
            <div class="relative">
                <div class="featured-products-scroll overflow-x-auto scrollbar-hide">
                    <div class="flex space-x-3 pb-4 pr-4" style="width: max-content;">
                        @for($i = 1; $i <= 8; $i++)
                            <div class="product-card-horizontal bg-white rounded-lg border border-gray-200 overflow-hidden hover:border-gray-300 transition-colors flex-shrink-0">
                                <div class="relative">
                                    <div class="w-full h-40 sm:h-44 bg-gradient-to-br from-blue-100 to-purple-100 flex items-center justify-center">
                                        <i class="fas fa-star text-3xl text-blue-400"></i>
                                    </div>
                                </div>
                                <div class="p-3">
                                    <h3 class="font-medium text-gray-900 text-sm line-clamp-2 mb-2">
                                        Featured Sneaker {{ $i }}
                                    </h3>
                                    <div class="space-y-2">
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm font-bold text-gray-900">
                                                Rp {{ number_format(rand(800000, 2500000), 0, ',', '.') }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-green-600">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            In stock
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        @endif

        <div class="text-center mt-12">
            <a href="/products?featured=1" class="inline-block bg-black text-white px-8 py-3 rounded-lg hover:bg-gray-800 transition-colors font-medium">
                View All Featured Products
            </a>
        </div>
    </div>
</section>
    <!-- Latest Products - Horizontal Scroll -->
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-4">Latest Arrivals</h2>
            <p class="text-gray-600 text-lg">Fresh kicks just dropped</p>
        </div>
        
        @if(isset($latestProducts) && $latestProducts->count() > 0)
            <!-- Horizontal Scrollable Products -->
            <div class="relative">
                <div class="latest-products-scroll overflow-x-auto scrollbar-hide">
                    <div class="flex space-x-3 pb-4 pr-4" style="width: max-content;">
                        @foreach($latestProducts as $product)
                            <div class="product-card-horizontal bg-white rounded-lg border border-gray-200 overflow-hidden hover:border-gray-300 transition-colors flex-shrink-0 relative">
                                <!-- New Badge -->
                                <div class="absolute top-2 left-2 z-10">
                                    <span class="bg-green-500 text-white text-xs font-bold px-2 py-1 rounded">
                                        New
                                    </span>
                                </div>

                                <!-- Sale Badge (if applicable) -->
                                @php
                                    $hasDiscount = (isset($product->discount_percentage) && $product->discount_percentage > 0);
                                    $hasSalePrice = (isset($product->sale_price) && $product->sale_price && isset($product->price) && $product->sale_price < $product->price);
                                @endphp
                                @if($hasDiscount || $hasSalePrice)
                                    <div class="absolute top-2 right-2 z-10">
                                        <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded">
                                            Sale
                                        </span>
                                    </div>
                                @endif

                                <div class="relative">
                                    <a href="/products/{{ $product->slug ?? 'product-' . $product->id }}">
                                        @if(isset($product->images) && is_array($product->images) && count($product->images) > 0)
                                            @php
                                                $firstImage = $product->images[0];
                                                $imageUrl = filter_var($firstImage, FILTER_VALIDATE_URL) 
                                                    ? $firstImage 
                                                    : asset('storage/' . ltrim($firstImage, '/'));
                                            @endphp
                                            <img src="{{ $imageUrl }}" 
                                                 alt="{{ $product->name ?? 'Product' }}"
                                                 class="w-full h-40 sm:h-44 object-cover"
                                                 loading="lazy"
                                                 onerror="this.src='{{ asset('images/default-product.jpg') }}'">
                                        @elseif(isset($product->featured_image) && $product->featured_image)
                                            @php
                                                $imageUrl = filter_var($product->featured_image, FILTER_VALIDATE_URL) 
                                                    ? $product->featured_image 
                                                    : asset('storage/' . ltrim($product->featured_image, '/'));
                                            @endphp
                                            <img src="{{ $imageUrl }}" 
                                                 alt="{{ $product->name ?? 'Product' }}"
                                                 class="w-full h-40 sm:h-44 object-cover"
                                                 loading="lazy"
                                                 onerror="this.src='{{ asset('images/default-product.jpg') }}'">
                                        @else
                                            <div class="w-full h-40 sm:h-44 bg-gray-200 flex items-center justify-center">
                                                <i class="fas fa-image text-3xl text-gray-400"></i>
                                            </div>
                                        @endif
                                    </a>
                                </div>

                                <div class="p-3">
                                    <a href="/products/{{ $product->slug ?? 'product-' . $product->id }}">
                                        @php
                                            // Clean product name
                                            $cleanName = $product->name ?? 'Unknown Product';
                                            $skuParent = $product->sku_parent ?? '';
                                            
                                            if (!empty($skuParent)) {
                                                $cleanName = preg_replace('/\s*-\s*' . preg_quote($skuParent, '/') . '\s*/', '', $cleanName);
                                                $cleanName = preg_replace('/\s*-\s*Size\s+[A-Z0-9.]+\s*$/i', '', $cleanName);
                                                $cleanName = preg_replace('/\s*Size\s+[A-Z0-9.]+\s*$/i', '', $cleanName);
                                                $cleanName = trim($cleanName, ' -');
                                            }
                                        @endphp
                                        <h3 class="font-medium text-gray-900 text-sm line-clamp-2 mb-2 hover:text-blue-600 transition-colors">
                                            {{ $cleanName }}
                                        </h3>
                                    </a>

                                    <div class="space-y-2">
                                        <!-- Price -->
                                        <div class="flex items-center space-x-2">
                                            @if($hasSalePrice)
                                                <span class="text-sm font-bold text-red-600">
                                                    Rp {{ number_format($product->sale_price, 0, ',', '.') }}
                                                </span>
                                                <span class="text-xs text-gray-400 line-through">
                                                    Rp {{ number_format($product->price, 0, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-sm font-bold text-gray-900">
                                                    Rp {{ number_format($product->price ?? 0, 0, ',', '.') }}
                                                </span>
                                            @endif
                                        </div>

                                        <!-- Stock Status -->
                                        @if(isset($product->stock_quantity))
                                            @if($product->stock_quantity > 0)
                                                <p class="text-xs text-green-600">
                                                    <i class="fas fa-check-circle mr-1"></i>
                                                    In stock
                                                </p>
                                            @else
                                                <p class="text-xs text-red-600">
                                                    <i class="fas fa-times-circle mr-1"></i>
                                                    Out of stock
                                                </p>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <!-- Fallback: Display sample products for Latest -->
            <div class="relative">
                <div class="latest-products-scroll overflow-x-auto scrollbar-hide">
                    <div class="flex space-x-3 pb-4 pr-4" style="width: max-content;">
                        @for($i = 1; $i <= 8; $i++)
                            <div class="product-card-horizontal bg-white rounded-lg border border-gray-200 overflow-hidden hover:border-gray-300 transition-colors flex-shrink-0 relative">
                                <!-- New Badge -->
                                <div class="absolute top-2 left-2 z-10">
                                    <span class="bg-green-500 text-white text-xs font-bold px-2 py-1 rounded">
                                        New
                                    </span>
                                </div>
                                
                                <div class="relative">
                                    <div class="w-full h-40 sm:h-44 bg-gradient-to-br from-green-100 to-blue-100 flex items-center justify-center">
                                        <i class="fas fa-bolt text-3xl text-green-400"></i>
                                    </div>
                                </div>
                                <div class="p-3">
                                    <h3 class="font-medium text-gray-900 text-sm line-clamp-2 mb-2">
                                        Latest Sneaker {{ $i }}
                                    </h3>
                                    <div class="space-y-2">
                                        <div class="flex items-center space-x-2">
                                            <span class="text-sm font-bold text-gray-900">
                                                Rp {{ number_format(rand(900000, 2800000), 0, ',', '.') }}
                                            </span>
                                        </div>
                                        <p class="text-xs text-green-600">
                                            <i class="fas fa-check-circle mr-1"></i>
                                            In stock
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endfor
                    </div>
                </div>
            </div>
        @endif

        <div class="text-center mt-12">
            <a href="/products?sort=latest" class="inline-block bg-black text-white px-8 py-3 rounded-lg hover:bg-gray-800 transition-colors font-medium">
                View All Latest Products
            </a>
        </div>
    </div>
</section>

    <!-- TAMBAHKAN CSS UNTUK FEATURED PRODUCTS -->
<style>
/* Featured Products Horizontal Scroll Styles - Sama seperti Best Sellers */
.featured-products-scroll {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none; /* Firefox */
    scroll-behavior: smooth;
}

.featured-products-scroll::-webkit-scrollbar {
    display: none; /* Chrome, Safari */
}

/* Reuse product-card-horizontal styles yang sudah ada */
/* Mobile: Tampilkan 2 produk + sedikit produk ke-3 */
@media (max-width: 640px) {
    .featured-products-scroll {
        padding-left: 0.5rem;
        padding-right: 1rem;
        margin-left: -0.5rem;
        margin-right: -1rem;
    }
}

/* Line clamp utility jika belum ada */
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Latest Products Horizontal Scroll Styles - Sama seperti Best Sellers & Featured */
.latest-products-scroll {
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none; /* Firefox */
    scroll-behavior: smooth;
}

.latest-products-scroll::-webkit-scrollbar {
    display: none; /* Chrome, Safari */
}

/* Mobile responsiveness sama seperti section lainnya */
@media (max-width: 640px) {
    .latest-products-scroll {
        padding-left: 0.5rem;
        padding-right: 1rem;
        margin-left: -0.5rem;
        margin-right: -1rem;
    }
}
</style>

@endsection