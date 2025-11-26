@extends('layouts.app')

@section('title', 'Black Friday Sale - SneakerFlash')

@section('content')
<!-- Black Friday Hero Section -->
<section class="bg-gradient-to-r from-black via-gray-900 to-red-600 text-white py-16 relative overflow-hidden">
    <div class="absolute inset-0 bg-black opacity-50"></div>
    <div class="container mx-auto px-4 relative z-10">
        <div class="text-center">
            <h1 class="text-6xl md:text-8xl font-black mb-4 text-yellow-400">
                BLACK FRIDAY
            </h1>
            <p class="text-2xl md:text-4xl font-bold mb-6 text-red-400">
                MEGA SALE
            </p>
            <p class="text-xl md:text-2xl mb-8">
                Up to 70% OFF on Premium Sneakers & Accessories
            </p>
            <div class="flex flex-col md:flex-row items-center justify-center gap-4 mb-8">
                <div class="bg-red-600 px-6 py-3 rounded-lg">
                    <span class="text-lg font-bold">Limited Time Only</span>
                </div>
                <div class="bg-yellow-500 text-black px-6 py-3 rounded-lg">
                    <span class="text-lg font-bold">Free Shipping</span>
                </div>
            </div>
            
            <!-- Countdown Timer -->
            <div id="countdown-timer" class="flex justify-center gap-4 text-center">
                <div class="bg-white text-black p-4 rounded-lg min-w-[80px]">
                    <div class="text-2xl font-bold" id="days">00</div>
                    <div class="text-sm">Days</div>
                </div>
                <div class="bg-white text-black p-4 rounded-lg min-w-[80px]">
                    <div class="text-2xl font-bold" id="hours">00</div>
                    <div class="text-sm">Hours</div>
                </div>
                <div class="bg-white text-black p-4 rounded-lg min-w-[80px]">
                    <div class="text-2xl font-bold" id="minutes">00</div>
                    <div class="text-sm">Minutes</div>
                </div>
                <div class="bg-white text-black p-4 rounded-lg min-w-[80px]">
                    <div class="text-2xl font-bold" id="seconds">00</div>
                    <div class="text-sm">Seconds</div>
                </div>
            </div>
        </div>
    </div>
</section>

@if($flashSaleItems && $flashSaleItems->count() > 0)
<!-- Flash Sale Section -->
<section class="bg-red-600 text-white py-8">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-3xl font-bold">⚡ FLASH SALE</h2>
                <p class="text-lg opacity-90">Limited stock, grab them now!</p>
            </div>
            <div class="bg-yellow-400 text-black px-4 py-2 rounded-lg">
                <span class="font-bold text-lg">Ends Soon!</span>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
            @foreach($flashSaleItems as $item)
                <div class="bg-white text-black rounded-lg overflow-hidden shadow-lg hover:shadow-xl transition-shadow">
                    <div class="relative">
                        <img src="{{ $item->first_image }}" alt="{{ $item->name }}" 
                             class="w-full h-48 object-cover">
                        @if($item->calculated_discount_percentage > 0)
                            <div class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 rounded text-sm font-bold">
                                -{{ $item->calculated_discount_percentage }}%
                            </div>
                        @endif
                        @if($item->limited_stock && $item->limited_stock <= 10)
                            <div class="absolute top-2 right-2 bg-yellow-400 text-black px-2 py-1 rounded text-sm font-bold">
                                Only {{ $item->limited_stock }} left!
                            </div>
                        @endif
                    </div>
                    <div class="p-4">
                        <h3 class="font-bold text-sm mb-2 line-clamp-2">{{ $item->name }}</h3>
                        <div class="flex flex-col gap-1">
                            @if($item->original_price)
                                <span class="text-gray-500 line-through text-sm">{{ $item->formatted_original_price }}</span>
                            @endif
                            <span class="text-red-600 font-bold text-lg">{{ $item->formatted_price }}</span>
                        </div>
                        <a href="{{ route('black-friday.show', $item->slug) }}" 
                           class="block mt-3 bg-red-600 text-white text-center py-2 rounded hover:bg-red-700 transition-colors text-sm font-bold">
                            BUY NOW
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Filters & Products Section -->
<section class="bg-gray-50 py-8">
    <div class="container mx-auto px-4">
        <!-- Filters Bar -->
        <div class="bg-white rounded-lg p-6 mb-6 shadow-sm">
            <div class="flex flex-col lg:flex-row gap-4">
                <!-- Search -->
                <div class="flex-1">
                    <form method="GET" action="{{ route('black-friday.index') }}" class="flex gap-2">
                        <input type="text" 
                               name="search" 
                               value="{{ request('search') }}"
                               placeholder="Search Black Friday deals..." 
                               class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition-colors">
                            Search
                        </button>
                        @if(request()->hasAny(['search', 'brand', 'type', 'gender']))
                            <a href="{{ route('black-friday.index') }}" 
                               class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                                Clear
                            </a>
                        @endif
                    </form>
                </div>
                
                <!-- Filters -->
                <div class="flex flex-wrap gap-4">
                    <!-- Brand Filter -->
                    <select name="brand" onchange="updateFilter('brand', this.value)" 
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500">
                        <option value="">All Brands</option>
                        @foreach($brands as $brand)
                            <option value="{{ $brand }}" {{ request('brand') === $brand ? 'selected' : '' }}>
                                {{ $brand }}
                            </option>
                        @endforeach
                    </select>
                    
                    <!-- Type Filter -->
                    <select name="type" onchange="updateFilter('type', this.value)"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500">
                        <option value="">All Types</option>
                        @foreach($types as $type)
                            <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>
                                {{ ucfirst(str_replace('_', ' ', $type)) }}
                            </option>
                        @endforeach
                    </select>
                    
                    <!-- Sort -->
                    <select name="sort" onchange="updateFilter('sort', this.value)"
                            class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500">
                        <option value="featured" {{ request('sort', 'featured') === 'featured' ? 'selected' : '' }}>Featured</option>
                        <option value="discount" {{ request('sort') === 'discount' ? 'selected' : '' }}>Biggest Discount</option>
                        <option value="price_low" {{ request('sort') === 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                        <option value="price_high" {{ request('sort') === 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
                        <option value="name_az" {{ request('sort') === 'name_az' ? 'selected' : '' }}>Name A-Z</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        @if($products && $products->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                @foreach($products as $product)
                    <div class="bg-white rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-shadow group">
                        <div class="relative">
                            <a href="{{ route('black-friday.show', $product->slug) }}">
                                <img src="{{ $product->first_image }}" 
                                     alt="{{ $product->name }}" 
                                     class="w-full h-64 object-cover group-hover:scale-105 transition-transform duration-300">
                            </a>
                            
                            @if($product->calculated_discount_percentage > 0)
                                <div class="absolute top-3 left-3 bg-red-500 text-white px-3 py-1 rounded-full text-sm font-bold">
                                    -{{ $product->calculated_discount_percentage }}%
                                </div>
                            @endif
                            
                            @if($product->is_flash_sale)
                                <div class="absolute top-3 right-3 bg-yellow-400 text-black px-2 py-1 rounded text-xs font-bold">
                                    ⚡ FLASH
                                </div>
                            @endif
                        </div>
                        
                        <div class="p-4">
                            <p class="text-sm text-gray-600 mb-1">{{ $product->brand }}</p>
                            <h3 class="font-bold text-gray-900 mb-2 line-clamp-2">
                                <a href="{{ route('black-friday.show', $product->slug) }}" class="hover:text-red-600">
                                    {{ $product->name }}
                                </a>
                            </h3>
                            
                            <div class="flex flex-col gap-1 mb-3">
                                @if($product->original_price)
                                    <span class="text-gray-500 line-through text-sm">{{ $product->formatted_original_price }}</span>
                                @endif
                                <span class="text-red-600 font-bold text-xl">{{ $product->formatted_price }}</span>
                                @if($product->discount_amount > 0)
                                    <span class="text-green-600 text-sm font-medium">
                                        Save Rp {{ number_format($product->discount_amount, 0, ',', '.') }}
                                    </span>
                                @endif
                            </div>
                            
                            <!-- Stock indicator -->
                            @if($product->stock_quantity <= 5 && $product->stock_quantity > 0)
                                <p class="text-orange-600 text-sm font-medium mb-2">
                                    Only {{ $product->stock_quantity }} left in stock!
                                </p>
                            @elseif($product->stock_quantity === 0)
                                <p class="text-red-600 text-sm font-medium mb-2">Out of stock</p>
                            @endif
                            
                            <a href="{{ route('black-friday.show', $product->slug) }}" 
                               class="block w-full bg-red-600 text-white text-center py-2 rounded-lg hover:bg-red-700 transition-colors font-bold {{ $product->stock_quantity === 0 ? 'opacity-50 cursor-not-allowed' : '' }}">
                                {{ $product->stock_quantity === 0 ? 'Out of Stock' : 'Shop Now' }}
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <!-- Pagination -->
            <div class="flex justify-center">
                {{ $products->links() }}
            </div>
        @else
            <div class="text-center py-16">
                <div class="mb-4">
                    <i class="fas fa-search text-6xl text-gray-400"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-2">No Black Friday deals found</h3>
                <p class="text-gray-600 mb-6">Try adjusting your filters or search terms</p>
                <a href="{{ route('black-friday.index') }}" 
                   class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition-colors">
                    View All Deals
                </a>
            </div>
        @endif
    </div>
</section>

@endsection

@push('scripts')
<script>
// Filter functions
function updateFilter(type, value) {
    const url = new URL(window.location);
    if (value) {
        url.searchParams.set(type, value);
    } else {
        url.searchParams.delete(type);
    }
    window.location.href = url.toString();
}

// Countdown timer
function initCountdown() {
    // Set Black Friday end date (you can modify this)
    const blackFridayEnd = new Date('2024-11-29T23:59:59').getTime();
    
    function updateCountdown() {
        const now = new Date().getTime();
        const distance = blackFridayEnd - now;
        
        if (distance > 0) {
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            document.getElementById('days').textContent = String(days).padStart(2, '0');
            document.getElementById('hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
            document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');
        } else {
            // Sale ended
            document.getElementById('countdown-timer').innerHTML = '<div class="text-2xl font-bold text-red-500">Sale Ended!</div>';
        }
    }
    
    updateCountdown();
    setInterval(updateCountdown, 1000);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', initCountdown);
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

.bg-gradient-to-r {
    background: linear-gradient(135deg, #000000 0%, #1f2937 50%, #dc2626 100%);
}

/* Animation for hero section */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.8; }
}

.animate-pulse {
    animation: pulse 2s infinite;
}
</style>
@endpush