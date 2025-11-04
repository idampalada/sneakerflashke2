<!DOCTYPE html>
<html lang="en">
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Midtrans Configuration -->
    <meta name="midtrans-client-key" content="{{ config('services.midtrans.client_key') }}">
    <meta name="midtrans-production" content="{{ config('services.midtrans.is_production') ? 'true' : 'false' }}">
    @yield('head')
    <title>@yield('title', 'SneakerFlash - Premium Sneakers')</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Vite Assets (termasuk home.css) -->
   <link rel="stylesheet" href="{{ asset('css/app.css') }}">
<link rel="stylesheet" href="{{ asset('css/home.css') }}">
<script src="{{ asset('js/app.js') }}"></script>
    <script>
  document.addEventListener('alpine:init', () => {
    Alpine.store('ui', {
      showMobileSearch: false
    });
  });
</script>


    <script>
        function carousel() {
    return {
        currentSlide: 0,
        slides: [
            @if(isset($banners) && $banners->count() > 0)
                @foreach($banners as $banner)
                    @php
                        // Prioritas: gunakan desktop/mobile images jika ada, fallback ke image_paths
                        $desktopImages = $banner->desktop_images ?? $banner->image_paths ?? [];
                        $mobileImages = $banner->mobile_images ?? $banner->image_paths ?? [];
                    @endphp
                    
                    @if(is_array($desktopImages) && count($desktopImages) > 0)
                        @foreach($desktopImages as $index => $desktopImage)
                            @php
                                $mobileImage = $mobileImages[$index] ?? $mobileImages[0] ?? $desktopImage;
                            @endphp
                            {
                                desktop: '{{ asset("storage/" . $desktopImage) }}',
                                mobile: '{{ asset("storage/" . $mobileImage) }}',
                                description: '{{ $banner->description ?? "" }}'
                            },
                        @endforeach
                    @endif
                @endforeach
            @endif
        ],
        
        init() {
            this.startAutoplay();
            // Update image source based on screen size
            this.updateImageSources();
            
            // Listen for window resize
            window.addEventListener('resize', () => {
                this.updateImageSources();
            });
            
            // Tambahkan listener untuk zoom event
            window.addEventListener('wheel', (e) => {
                if (e.ctrlKey) {
                    // User sedang zoom, pastikan banner tetap konsisten
                    this.fixImageOnZoom();
                }
            });
            
            // Tambahkan listener untuk touchscreen pinch zoom
            window.addEventListener('gesturechange', () => {
                this.fixImageOnZoom();
            });
        },
        
        updateImageSources() {
            const isMobile = window.innerWidth <= 767;
            const slideImages = document.querySelectorAll('.carousel-slide img');
            
            slideImages.forEach((img, index) => {
                if (this.slides[index]) {
                    img.src = isMobile ? this.slides[index].mobile : this.slides[index].desktop;
                    // Tambahkan styling fix untuk mencegah perubahan saat zoom
                    this.applyFixedImageStyling(img);
                }
            });
        },
        
        fixImageOnZoom() {
            // Metode ini akan dipanggil saat user melakukan zoom
            const slideImages = document.querySelectorAll('.carousel-slide img');
            slideImages.forEach(img => {
                this.applyFixedImageStyling(img);
            });
            
            // Pastikan container juga tetap dengan ukuran yang benar
            const container = document.querySelector('.carousel-container');
            if (container) {
                container.style.height = '480px';
                container.style.transform = 'translateZ(0)';
                container.style.backfaceVisibility = 'hidden';
                container.style.willChange = 'transform';
            }
        },
        
        applyFixedImageStyling(img) {
            // Terapkan styling yang konsisten ke semua gambar carousel
            img.style.objectFit = 'contain';
            img.style.objectPosition = 'center';
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.transform = 'translateZ(0)';
            img.style.backfaceVisibility = 'hidden';
            img.style.willChange = 'transform';
        },
        
        nextSlide() {
            this.currentSlide = (this.currentSlide + 1) % this.slides.length;
        },
        
        prevSlide() {
            this.currentSlide = this.currentSlide === 0 ? this.slides.length - 1 : this.currentSlide - 1;
        },
        
        goToSlide(index) {
            this.currentSlide = index;
        },
        
        startAutoplay() {
            if (this.slides.length > 1) {
                setInterval(() => {
                    this.nextSlide();
                }, 4000);
            }
        }
    }
}
        // Navigation dropdown functionality
        function navigationDropdown() {
            return {
                activeDropdown: null,
                
                toggleDropdown(dropdownName) {
                    if (this.activeDropdown === dropdownName) {
                        this.activeDropdown = null;
                    } else {
                        this.activeDropdown = dropdownName;
                    }
                },
                
                closeDropdown() {
                    this.activeDropdown = null;
                },
                
                isDropdownActive(dropdownName) {
                    return this.activeDropdown === dropdownName;
                }
            }
        }

        // User dropdown functionality
        function userDropdown() {
            return {
                open: false,
                toggle() {
                    this.open = !this.open;
                },
                close() {
                    this.open = false;
                }
            }
        }

        // Close dropdowns when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('click', function(event) {
                // Close navigation dropdowns
                const navContainer = document.querySelector('.nav-container');
                if (navContainer && !navContainer.contains(event.target)) {
                    const alpineComponent = document.querySelector('[x-data*="navigationDropdown"]');
                    if (alpineComponent && alpineComponent._x_dataStack) {
                        alpineComponent._x_dataStack[0].closeDropdown();
                    }
                }
            });
        });

        // Function untuk update cart dan wishlist count
        function updateCartCount(count) {
            const cartBadge = document.getElementById('cartCount');
            if (cartBadge) {
                if (count > 0) {
                    cartBadge.textContent = count;
                    cartBadge.style.display = 'flex';
                } else {
                    cartBadge.style.display = 'none';
                }
            }
        }


    </script>
</head>
<body class="bg-gray-50">
    <!-- Header dengan navigation menu menyatu - background PUTIH -->
    <header class="ka-header sticky top-0 z-50" x-data="{ mobileMenuOpen: false, showMobileSearch: false }">
        <!-- Mobile Menu Overlay -->
        <div class="mobile-menu-overlay" :class="{ 'open': mobileMenuOpen }" @click="mobileMenuOpen = false"></div>
        
<!-- Mobile Slide Menu - DENGAN DROPDOWN SEDERHANA -->
<div class="mobile-menu" :class="{ 'open': mobileMenuOpen }" x-data="mobileMenuDropdown()">
<!-- Menu Header -->
<div class="mobile-menu-header">
    @auth
        <div class="text-center">
            <p class="text-gray-600 text-sm">Hello, <span class="font-semibold text-gray-800">{{ auth()->user()->name }}</span></p>
        </div>
    @endauth
</div>
    
    <!-- Menu Items -->
    <div class="mobile-menu-content" :class="{ 'menu-hidden': activeSubmenu }">
        <!-- Navigation Menu dengan Dropdown -->
        
        <!-- MENS DROPDOWN -->
        <button @click="openSubmenu('mens')" class="mobile-menu-item w-full text-left flex items-center justify-between">
            <span>MENS</span>
            <i class="fas fa-chevron-right text-gray-400"></i>
        </button>
        
        <!-- WOMENS DROPDOWN -->
        <button @click="openSubmenu('womens')" class="mobile-menu-item w-full text-left flex items-center justify-between">
            <span>WOMENS</span>
            <i class="fas fa-chevron-right text-gray-400"></i>
        </button>
        
        <!-- UNISEX DROPDOWN -->
        <button @click="openSubmenu('unisex')" class="mobile-menu-item w-full text-left flex items-center justify-between">
            <span>UNISEX</span>
            <i class="fas fa-chevron-right text-gray-400"></i>
        </button>
        
        <!-- BRAND DROPDOWN -->
        <button @click="openSubmenu('brand')" class="mobile-menu-item w-full text-left flex items-center justify-between">
            <span>BRAND</span>
            <i class="fas fa-chevron-right text-gray-400"></i>
        </button>
        
        <!-- Menu tanpa dropdown -->
        <a href="/products?category=accessories" class="mobile-menu-item">ACCESSORIES</a>
        <a href="/products?sale=true" class="mobile-menu-item special">SALE</a>
        
        @auth
            <!-- My Account Section -->
            <div class="border-t border-gray-200 mt-4 pt-4">
                <div class="px-4 pb-2">
                    <h5 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">My Account</h5>
                </div>
                <a href="{{ route('cart.index') }}" class="mobile-menu-item flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-shopping-cart mr-3 text-gray-500"></i>My Cart
                    </div>
                    @php $cartCount = count(session('cart', [])); @endphp
                    @if($cartCount > 0)
                        <span class="text-xs bg-blue-500 text-white px-2 py-1 rounded-full">{{ $cartCount }}</span>
                    @endif
                </a>
            </div>
        @else
            <!-- Guest Cart -->
            <div class="border-t border-gray-200 mt-4 pt-4">
                <a href="{{ route('cart.index') }}" class="mobile-menu-item flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-shopping-cart mr-3 text-gray-500"></i>My Cart
                    </div>
                    @php $cartCount = count(session('cart', [])); @endphp
                    @if($cartCount > 0)
                        <span class="text-xs bg-blue-500 text-white px-2 py-1 rounded-full">{{ $cartCount }}</span>
                    @endif
                </a>
            </div>
        @endauth
    </div>
    
    <!-- SUBMENU MENS -->
    <div class="mobile-submenu-overlay" :class="{ 'active': activeSubmenu === 'mens' }">
        <div class="mobile-submenu-header">
            <button @click="closeSubmenu()" class="mobile-back-btn">
                <i class="fas fa-chevron-left mr-2"></i>
                <span>All</span>
            </button>
            <button @click="mobileMenuOpen = false" class="mobile-close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mobile-submenu-content">
            <h2 class="mobile-submenu-title">Men</h2>
            <a href="/products?category=mens&type=lifestyle" class="mobile-submenu-item-large">Lifestyle/Casual</a>
            <a href="/products?category=mens&type=running" class="mobile-submenu-item-large">Running</a>
            <a href="/products?category=mens&type=training" class="mobile-submenu-item-large">Training</a>
            <a href="/products?category=mens&type=basketball" class="mobile-submenu-item-large">Basketball</a>
            <a href="/products?category=mens&type=apparel" class="mobile-submenu-item-large">Apparel</a>
        </div>
    </div>
    
    <!-- SUBMENU WOMENS -->
    <div class="mobile-submenu-overlay" :class="{ 'active': activeSubmenu === 'womens' }">
        <div class="mobile-submenu-header">
            <button @click="closeSubmenu()" class="mobile-back-btn">
                <i class="fas fa-chevron-left mr-2"></i>
                <span>All</span>
            </button>
            <button @click="mobileMenuOpen = false" class="mobile-close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mobile-submenu-content">
            <h2 class="mobile-submenu-title">Women</h2>
            <a href="/products?category=womens&type=lifestyle" class="mobile-submenu-item-large">Lifestyle/Casual</a>
            <a href="/products?category=womens&type=running" class="mobile-submenu-item-large">Running</a>
            <a href="/products?category=womens&type=training" class="mobile-submenu-item-large">Training</a>
            <a href="/products?category=womens&type=apparel" class="mobile-submenu-item-large">Apparel</a>
        </div>
    </div>
    
    <!-- SUBMENU UNISEX -->
    <div class="mobile-submenu-overlay" :class="{ 'active': activeSubmenu === 'unisex' }">
        <div class="mobile-submenu-header">
            <button @click="closeSubmenu()" class="mobile-back-btn">
                <i class="fas fa-chevron-left mr-2"></i>
                <span>All</span>
            </button>
            <button @click="mobileMenuOpen = false" class="mobile-close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mobile-submenu-content">
            <h2 class="mobile-submenu-title">Unisex</h2>
            <a href="/products?category=unisex&type=lifestyle" class="mobile-submenu-item-large">Lifestyle/Casual</a>
            <a href="/products?category=unisex&type=running" class="mobile-submenu-item-large">Running</a>
            <a href="/products?category=unisex&type=training" class="mobile-submenu-item-large">Training</a>
            <a href="/products?category=unisex&type=basketball" class="mobile-submenu-item-large">Basketball</a>
            <a href="/products?category=unisex&type=apparel" class="mobile-submenu-item-large">Apparel</a>
        </div>
    </div>
    
    <!-- SUBMENU BRAND -->
    <div class="mobile-submenu-overlay" :class="{ 'active': activeSubmenu === 'brand' }">
        <div class="mobile-submenu-header">
            <button @click="closeSubmenu()" class="mobile-back-btn">
                <i class="fas fa-chevron-left mr-2"></i>
                <span>All</span>
            </button>
            <button @click="mobileMenuOpen = false" class="mobile-close-btn">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mobile-submenu-content">
            <h2 class="mobile-submenu-title">Brand</h2>
            <a href="/products?brands[]=Adidas" class="mobile-submenu-item-large">ADIDAS</a>
            <a href="/products?brands[]=Air+Jordan" class="mobile-submenu-item-large">AIR JORDAN</a>
            <a href="/products?brands[]=Asics" class="mobile-submenu-item-large">ASICS</a>
            <a href="/products?brands[]=Converse" class="mobile-submenu-item-large">CONVERSE</a>
            <a href="/products?brands[]=Crep" class="mobile-submenu-item-large">CREP</a>
            <a href="/products?brands[]=Crocs" class="mobile-submenu-item-large">CROCS</a>
            <a href="/products?brands[]=Diadora" class="mobile-submenu-item-large">DIADORA</a>
            <a href="/products?brands[]=Hoka" class="mobile-submenu-item-large">HOKA</a>
            <a href="/products?brands[]=New+Balance" class="mobile-submenu-item-large">NEW BALANCE</a>
            <a href="/products?brands[]=New+Era" class="mobile-submenu-item-large">NEW ERA</a>
            <a href="/products?brands[]=Nike" class="mobile-submenu-item-large">NIKE</a>
            <a href="/products?brands[]=Onitsuka+Tiger" class="mobile-submenu-item-large">ONITSUKA TIGER</a>
            <a href="/products?brands[]=Puma" class="mobile-submenu-item-large">PUMA</a>
            <a href="/products?brands[]=Reebok" class="mobile-submenu-item-large">REEBOK</a>
            <a href="/products?brands[]=Salomon" class="mobile-submenu-item-large">SALOMON</a>
            <a href="/products?brands[]=Skechers" class="mobile-submenu-item-large">SKECHERS</a>
            <a href="/products?brands[]=Umbro" class="mobile-submenu-item-large">UMBRO</a>
            <a href="/products?brands[]=Vans" class="mobile-submenu-item-large">VANS</a>
        </div>
    </div>
    
    <!-- Auth Buttons -->
    <div class="mobile-auth-buttons" :class="{ 'menu-hidden': activeSubmenu }">
        @auth
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="mobile-auth-btn mobile-register-btn w-full">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </button>
            </form>
        @else
            <a href="/login" class="mobile-auth-btn mobile-login-btn">Login</a>
            <a href="/register" class="mobile-auth-btn mobile-register-btn">Register</a>
        @endauth
    </div>
</div>


<!-- AKHIR MOBILE MENU -->

        <!-- Baris pertama: Mobile Layout Baru + Desktop tetap sama -->
<div class="max-w-full mx-auto px-4">
    <div class="flex items-center py-4">
        
        <!-- MOBILE LAYOUT: Hamburger - Logo - Search - Cart -->
<div class="md:hidden mobile-header-layout">
    <!-- Mobile Hamburger (KIRI) -->
    <div class="mobile-hamburger">
        <button @click="mobileMenuOpen = !mobileMenuOpen" class="text-gray-600 hover:text-gray-800 p-2">
            <i class="fas fa-bars text-xl"></i>
        </button>
    </div>
    
    <!-- Mobile Logo (TENGAH) -->
    <div class="mobile-logo-center">
        <a href="/">
            <img src="{{ asset('images/logo-sneakerflash.jpg') }}" alt="SneakerFlash Logo" class="ka-logo-img">
        </a>
    </div>
    
    <!-- Mobile Icons (KANAN): Search + Cart -->
<div class="mobile-icons-right">
    <button type="button"
        @click="$store.ui.showMobileSearch = !$store.ui.showMobileSearch"
        class="mobile-search-extended"
        title="Search Products">
  <i class="fas fa-search"></i>
  <span class="search-text">Search</span>
</button>



        <!-- Cart Button -->
            <a href="{{ route('cart.index') }}" class="icon-btn mobile-cart-hidden" title="Shopping Cart">
            <i class="fas fa-shopping-cart"></i>
            @php
                $cartCount = count(session('cart', []));
            @endphp
            @if($cartCount > 0)
                <span class="icon-badge" id="cartCount">{{ $cartCount }}</span>
            @else
                <span class="icon-badge" id="cartCount" style="display: none;">0</span>
            @endif
        </a>
    </div>
</div>
        <!-- DESKTOP LAYOUT: Logo - Search - User Menu (TETAP SAMA) -->
        <div class="hidden md:flex items-center justify-between w-full">
            <!-- Desktop Logo -->
            <div class="flex items-center">
                <a href="/">
                    <img src="{{ asset('images/logo-sneakerflash.jpg') }}" alt="SneakerFlash Logo" class="ka-logo-img">
                </a>
            </div>

            <!-- Desktop Search Bar -->
<div class="flex-1 max-w-2xl mx-8">
    <form action="{{ route('search') }}" method="GET" class="w-full ka-search-custom mx-auto">
        {{-- Hidden input untuk maintain filter lain jika ada (opsional) --}}
        @if(request()->hasAny(['category', 'brands', 'min_price', 'max_price', 'sort']))
            @foreach(request()->only(['category', 'brands', 'min_price', 'max_price', 'sort']) as $key => $value)
                @if(is_array($value))
                    @foreach($value as $val)
                        <input type="hidden" name="{{ $key }}[]" value="{{ $val }}">
                    @endforeach
                @else
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
        @endif
        
        <div class="ka-search-container">
            <div class="relative flex items-center">
                <i class="fas fa-search ka-search-icon"></i>
                <input type="text" 
                       name="q"  {{-- ⭐ UBAH: name="q" untuk query utama --}}
                       placeholder="Type any products here"
                       value="{{ request('q') }}"  {{-- ⭐ UBAH: value dari request('q') --}}
                       class="ka-search-input flex-1">
                <button type="submit" class="ka-search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>
    </form>
</div>

            <!-- Desktop User Menu -->
            <div class="flex items-center space-x-3">
                @auth
                    <!-- Desktop User Dropdown -->
                    <div class="relative" x-data="userDropdown()" @click.away="close()">
                        <button @click="toggle()" class="user-menu-btn">
                            <i class="fas fa-user-circle text-xl"></i>
                            <span class="text-sm ml-2">{{ auth()->user()->name }}</span>
                            <i class="fas fa-chevron-down text-sm ml-1"></i>
                        </button>

                        <!-- Desktop Dropdown Menu (dengan cart) -->
                        <div class="user-dropdown" :class="{ 'show': open }">
                            <a href="{{ route('cart.index') }}" class="dropdown-item">
                                <i class="fas fa-shopping-cart mr-2"></i>My Cart
                                @php $cartCount = count(session('cart', [])); @endphp
                                @if($cartCount > 0)
                                    <span class="ml-auto text-xs bg-blue-500 text-white px-2 py-1 rounded-full">{{ $cartCount }}</span>
                                @endif
                            </a>
                            <a href="{{ route('orders.index') }}" class="dropdown-item">
                                <i class="fas fa-shopping-bag mr-2"></i>My Orders
                            </a>
                            <a href="{{ route('profile.index') }}" class="dropdown-item">
                                <i class="fas fa-user mr-2"></i>Profile
                            </a>
                            <div style="border-top: 1px solid #f0f0f0; margin: 5px 0;"></div>
                            <form action="{{ route('logout') }}" method="POST" class="block">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <!-- Login/Register untuk guest -->
                    <a href="{{ route('login') }}" class="ka-auth-btn ka-login-btn">Login</a>
                    <a href="{{ route('register') }}" class="ka-auth-btn ka-register-btn">Register</a>
                @endauth
            </div>
        </div>
    </div>
</div>
<!-- Mobile Bottom Navigation - 3 Items: Home, Cart, Profile -->
<div class="mobile-bottom-nav fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-50 md:hidden">
    <div class="flex items-center justify-around py-2">
        <!-- Home -->
        <a href="/" class="flex flex-col items-center py-2 px-4 {{ request()->is('/') ? 'text-black font-bold' : 'text-gray-400' }}">
            <div class="relative">
                <i class="fas fa-home text-xl mb-1"></i>
            </div>
            <span class="text-xs font-medium">Home</span>
        </a>

        <!-- Cart -->
        <a href="{{ route('cart.index') }}" class="flex flex-col items-center py-2 px-4 {{ request()->is('cart*') ? 'text-black font-bold' : 'text-gray-400' }}">
            <div class="relative">
                <i class="fas fa-shopping-cart text-xl mb-1"></i>
                @php $cartCount = count(session('cart', [])); @endphp
                @if($cartCount > 0)
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
                        {{ $cartCount > 99 ? '99+' : $cartCount }}
                    </span>
                @endif
            </div>
            <span class="text-xs font-medium">Cart</span>
        </a>

        <!-- Profile/Account -->
        @auth
            <a href="{{ route('profile.index') }}" class="flex flex-col items-center py-2 px-4 {{ request()->is('profile*') || request()->is('orders*') ? 'text-black font-bold' : 'text-gray-400' }}">
                <div class="relative">
                    <i class="fas fa-user text-xl mb-1"></i>
                </div>
                <span class="text-xs font-medium">Profile</span>
            </a>
        @else
            <a href="{{ route('login') }}" class="flex flex-col items-center py-2 px-4 {{ request()->is('login*') || request()->is('register*') ? 'text-black font-bold' : 'text-gray-400' }}">
                <div class="relative">
                    <i class="fas fa-user text-xl mb-1"></i>
                </div>
                <span class="text-xs font-medium">Login</span>
            </a>
        @endauth
    </div>
</div>
        <!-- Baris kedua: Navigation Menu dengan Dropdown -->
        <div class="max-w-full px-4">
            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center justify-center py-0 nav-container" x-data="navigationDropdown()" @click.away="closeDropdown()">
                
                <!-- MENS Dropdown -->
                <div class="nav-item-container">
                    <button @click="toggleDropdown('mens')" 
                            class="nav-main-btn" 
                            :class="{ 'active': isDropdownActive('mens') }">
                        MENS
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </button>
                    <div class="nav-dropdown" :class="{ 'show': isDropdownActive('mens') }">
                        <!-- FOOTWEAR Section -->
                        <a href="/products?category=mens&section=footwear" class="dropdown-header-link">FOOTWEAR</a>
                        <a href="/products?category=mens&type=lifestyle" class="dropdown-item">Lifestyle/Casual</a>
                        <a href="/products?category=mens&type=running" class="dropdown-item">Running</a>
                        <a href="/products?category=mens&type=training" class="dropdown-item">Training</a>
                        <a href="/products?category=mens&type=basketball" class="dropdown-item">Basketball</a>
                        
                        <!-- APPAREL Section -->
                        <a href="/products?category=mens&type=apparel" class="dropdown-header-link">APPAREL</a>
                    </div>
                </div>

                <!-- WOMENS Dropdown -->
                <div class="nav-item-container">
                    <button @click="toggleDropdown('womens')" 
                            class="nav-main-btn" 
                            :class="{ 'active': isDropdownActive('womens') }">
                        WOMENS
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </button>
                    <div class="nav-dropdown" :class="{ 'show': isDropdownActive('womens') }">
                        <!-- FOOTWEAR Section -->
                        <a href="/products?category=womens&section=footwear" class="dropdown-header-link">FOOTWEAR</a>
                        <a href="/products?category=womens&type=lifestyle" class="dropdown-item">Lifestyle/Casual</a>
                        <a href="/products?category=womens&type=running" class="dropdown-item">Running</a>
                        <a href="/products?category=womens&type=training" class="dropdown-item">Training</a>
                        
                        <!-- APPAREL Section -->
                        <a href="/products?category=womens&type=apparel" class="dropdown-header-link">APPAREL</a>
                    </div>
                </div>

                <!-- UNISEX (Updated from KIDS) -->
                <div class="nav-item-container">
                    <button @click="toggleDropdown('unisex')" 
                            class="nav-main-btn" 
                            :class="{ 'active': isDropdownActive('unisex') }">
                        UNISEX
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </button>
                    <div class="nav-dropdown" :class="{ 'show': isDropdownActive('unisex') }">
                        <!-- FOOTWEAR Section -->
                        <a href="/products?category=unisex&section=footwear" class="dropdown-header-link">FOOTWEAR</a>
                        <a href="/products?category=unisex&type=lifestyle" class="dropdown-item">Lifestyle/Casual</a>
                        <a href="/products?category=unisex&type=running" class="dropdown-item">Running</a>
                        <a href="/products?category=unisex&type=training" class="dropdown-item">Training</a>
                        <a href="/products?category=unisex&type=basketball" class="dropdown-item">Basketball</a>
                        
                        <!-- APPAREL Section -->
                        <a href="/products?category=unisex&type=apparel" class="dropdown-header-link">APPAREL</a>
                    </div>
                </div>

                <!-- BRAND Dropdown -->
                <!-- BRAND Dropdown -->
<div class="nav-item-container">
    <button @click="toggleDropdown('brand')" 
            class="nav-main-btn" 
            :class="{ 'active': isDropdownActive('brand') }">
        BRAND
        <i class="fas fa-chevron-down dropdown-arrow"></i>
    </button>
    <div class="nav-dropdown brand-dropdown-grid" :class="{ 'show': isDropdownActive('brand') }">
        <!-- Kolom 1 -->
        <div class="brand-column">
            <a href="/products?brands[]=Nike" class="dropdown-item">
                <i class="fab fa-nike mr-2"></i>NIKE
            </a>
            <a href="/products?brands[]=Adidas" class="dropdown-item">
                <i class="fab fa-adidas mr-2"></i>ADIDAS
            </a>
            <a href="/products?brands[]=Puma" class="dropdown-item">
                <i class="fab fa-puma mr-2"></i>PUMA
            </a>
        </div>
        
        <!-- Kolom 2 -->
        <div class="brand-column">
            <a href="/products?brands[]=Converse" class="dropdown-item">
                <i class="fas fa-star mr-2"></i>CONVERSE
            </a>
            <a href="/products?brands[]=Vans" class="dropdown-item">
                <i class="fas fa-skateboard mr-2"></i>VANS
            </a>
            <a href="/products?brands[]=New Balance" class="dropdown-item">
                <i class="fas fa-balance-scale mr-2"></i>NEW BALANCE
            </a>
        </div>
        
        <!-- Kolom 3 -->
        <div class="brand-column">
            <a href="/products?brands[]=Jordan" class="dropdown-item">
                <i class="fas fa-basketball-ball mr-2"></i>JORDAN
            </a>
            <a href="/products?brands[]=Reebok" class="dropdown-item">
                <i class="fas fa-running mr-2"></i>REEBOK
            </a>
            <a href="/products?brands[]=ASICS" class="dropdown-item">
                <i class="fas fa-shoe-prints mr-2"></i>ASICS
            </a>
        </div>
        
        <!-- Kolom 4 -->
        <div class="brand-column">
            <a href="/products?brands[]=Under Armour" class="dropdown-item">
                <i class="fas fa-shield-alt mr-2"></i>UNDER ARMOUR
            </a>
            <a href="/products?brands[]=Skechers" class="dropdown-item">
                <i class="fas fa-walking mr-2"></i>SKECHERS
            </a>
            <a href="/products?brands[]=Fila" class="dropdown-item">
                <i class="fas fa-mountain mr-2"></i>FILA
            </a>
        </div>
    </div>
</div>

                <!-- ACCESSORIES Dropdown (Updated with new product types) -->
                <div class="nav-item-container">
                    <button @click="toggleDropdown('accessories')" 
                            class="nav-main-btn" 
                            :class="{ 'active': isDropdownActive('accessories') }">
                        ACCESSORIES
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </button>
                    <div class="nav-dropdown" :class="{ 'show': isDropdownActive('accessories') }">
                        <a href="/products?type=accessories" class="dropdown-header-link">ALL ACCESSORIES</a>
                        <a href="/products?type=bags" class="dropdown-item">
                            <i class="fas fa-shopping-bag mr-2"></i>Bags
                        </a>
                        <a href="/products?type=caps" class="dropdown-item">
                            <i class="fas fa-hat-cowboy mr-2"></i>Caps & Hats
                        </a>
                        <a href="/products?type=apparel" class="dropdown-item">
                            <i class="fas fa-tshirt mr-2"></i>Apparel
                        </a>
                        <a href="/products?category=accessories&type=cleaner" class="dropdown-item">
                            <i class="fas fa-spray-can mr-2"></i>Shoe Care
                        </a>
                    </div>
                </div>

                <!-- SALE -->
                <div class="nav-item-container">
                    <a href="/products?sale=true" class="nav-simple-link special">
                        SALE
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Search Dropdown (tampil saat button diklik) -->
<!-- Mobile Search Overlay (FULL SCREEN) -->
<!-- Mobile Search Overlay (slide-in from right) -->
<!-- Mobile Search Overlay (FULL SCREEN) -->
<div class="fixed inset-0 z-[100] md:hidden bg-white"
     x-data
     x-show="$store.ui.showMobileSearch"
     x-transition
     x-trap="$store.ui.showMobileSearch"
     @keydown.escape.window="$store.ui.showMobileSearch = false"
     x-init="$watch(() => $store.ui.showMobileSearch, v => v && $nextTick(() => $el.querySelector('input[name=search]')?.focus()))"
     style="display:none;">
  <!-- Header overlay -->
  <div class="p-5 flex items-center justify-between border-b">
    <h2 class="text-xl font-semibold">Search</h2>
    <button @click="$store.ui.showMobileSearch = false" class="text-3xl leading-none">&times;</button>
  </div>

  <!-- Body overlay -->
<div class="p-5">
    <form action="{{ route('search') }}" method="GET">
        {{-- Hidden input untuk maintain filter lain jika ada (opsional) --}}
        @if(request()->hasAny(['category', 'brands', 'min_price', 'max_price', 'sort']))
            @foreach(request()->only(['category', 'brands', 'min_price', 'max_price', 'sort']) as $key => $value)
                @if(is_array($value))
                    @foreach($value as $val)
                        <input type="hidden" name="{{ $key }}[]" value="{{ $val }}">
                    @endforeach
                @else
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
        @endif
        
      <div class="ka-search-container">
        <div class="relative flex items-center">
          <i class="fas fa-search ka-search-icon"></i>
          <input type="text" 
                 name="q"  {{-- ⭐ UBAH: name="q" untuk query utama --}}
                 placeholder="Type any products here"
                 value="{{ request('q') }}"  {{-- ⭐ UBAH: value dari request('q') --}}
                 class="ka-search-input flex-1" autofocus>
          <button type="submit" class="ka-search-btn">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </div>
    </form>
</div>
</div>


    <!-- Mobile Cart & Wishlist (tampil di mobile) -->
    <!-- Mobile Menu dengan Dropdown -->
<div class="md:hidden bg-white border-b px-0 py-0 mobile-nav-hidden" x-data="navigationDropdown()">
    <div class="overflow-x-auto" style="-webkit-overflow-scrolling: touch; scrollbar-width: none;">
        <div class="flex px-4 py-3 min-w-max space-x-0">
            <!-- MENS dengan dropdown -->
            <div class="relative">
                <button @click="toggleDropdown('mens')" class="px-4 py-2 text-black font-bold text-sm whitespace-nowrap hover:bg-gray-50 transition-colors flex items-center">
                    MENS
                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                </button>
                <div x-show="activeDropdown === 'mens'" @click.away="closeDropdown()" class="absolute top-full left-0 bg-white border border-gray-200 rounded-lg shadow-lg min-w-48 z-50">
                    <a href="/products?category=mens&type=lifestyle" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Lifestyle/Casual</a>
                    <a href="/products?category=mens&type=running" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Running</a>
                    <a href="/products?category=mens&type=basketball" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Basketball</a>
                </div>
            </div>
            
            <!-- WOMENS dengan dropdown -->
            <div class="relative">
                <button @click="toggleDropdown('womens')" class="px-4 py-2 text-black font-bold text-sm whitespace-nowrap hover:bg-gray-50 transition-colors flex items-center">
                    WOMENS
                    <i class="fas fa-chevron-down ml-1 text-xs"></i>
                </button>
                <div x-show="activeDropdown === 'womens'" @click.away="closeDropdown()" class="absolute top-full left-0 bg-white border border-gray-200 rounded-lg shadow-lg min-w-48 z-50">
                    <a href="/products?category=womens&type=lifestyle" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Lifestyle/Casual</a>
                    <a href="/products?category=womens&type=running" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Running</a>
                </div>
            </div>
            
            <!-- Menu lainnya tanpa dropdown -->
            <a href="/products?category=unisex" class="px-4 py-2 text-black font-bold text-sm whitespace-nowrap hover:bg-gray-50 transition-colors">
                UNISEX
            </a>
            <a href="/products?brands" class="px-4 py-2 text-black font-bold text-sm whitespace-nowrap hover:bg-gray-50 transition-colors">
                BRAND
            </a>
            <a href="/products?category=accessories" class="px-4 py-2 text-black font-bold text-sm whitespace-nowrap hover:bg-gray-50 transition-colors">
                ACCESSORIES
            </a>
            <a href="/products?sale=true" class="px-4 py-2 text-red-600 font-bold text-sm whitespace-nowrap hover:bg-red-50 transition-colors">
                SALE
            </a>
        </div>
    </div>
</div>

<script>
function mobileMenuDropdown() {
    return {
        activeSubmenu: null,
        
        openSubmenu(menu) {
            this.activeSubmenu = menu;
        },
        
        closeSubmenu() {
            this.activeSubmenu = null;
        }
    }
}
</script>
    @if(request()->is('/') || request()->routeIs('home'))
<!-- Image Carousel Slider -->
@if(isset($banners) && $banners->count() > 0)
<div class="carousel-wrapper" x-data="carousel()">
    <div class="carousel-container">
        <template x-for="(slide, index) in slides" :key="index">
            <div class="carousel-slide" :class="{ 'active': currentSlide === index }">
                <!-- Use desktop image as default, will be updated by JavaScript -->
                <img :src="slide.desktop" 
                     :alt="slide.description" 
                     loading="lazy"
                     @load="updateImageSources()">
            </div>
        </template>
        
        <!-- Navigation arrows (jika lebih dari 1 slide) -->
        <template x-if="slides.length > 1">
            <div>
                <button @click="prevSlide()" class="carousel-nav prev">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button @click="nextSlide()" class="carousel-nav next">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </template>
        
        <!-- Dots indicator -->
        <template x-if="slides.length > 1">
            <div class="carousel-indicators">
                <template x-for="(slide, index) in slides" :key="index">
                    <button @click="goToSlide(index)" 
                            class="carousel-dot" 
                            :class="{ 'active': currentSlide === index }">
                    </button>
                </template>
            </div>
        </template>
    </div>
</div>
</div>
@else
<div class="carousel-wrapper">
  <div class="carousel-container">
    <div class="flex items-center justify-center h-full text-gray-500">
      <p>No banners available</p>
    </div>
  </div>
</div>
@endif
@endif

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mx-4 mt-4" role="alert">
            <div class="flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mx-4 mt-4" role="alert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    @if(session('warning'))
        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mx-4 mt-4" role="alert">
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span>{{ session('warning') }}</span>
            </div>
        </div>
    @endif

    <!-- Main Content -->
    <main>
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-black text-white py-12" style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
    <!-- Container tanpa max-width constraint untuk logo -->
    <div class="w-full">
        <!-- Grid Layout: 2 kolom di mobile (tanpa logo), 3 kolom di desktop -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-6 md:gap-8">
            
            <!-- Company Info - BENAR-BENAR di pojok kiri tanpa margin/padding, hidden di mobile -->
            <div class="hidden md:block md:col-span-1">
                <div class="pl-4 lg:pl-8">
                    <img src="{{ asset('images/logo-sneakerflash.jpg') }}" alt="SneakerFlash Logo" class="ka-logo-img mb-4 filter brightness-0 invert">
                    <p class="text-gray-300 text-sm pr-4">
                        Premium sneakers and streetwear for everyone. Authentic products, fast delivery.
                    </p>
                </div>
            </div>

            <!-- Section 1: Information -->
            <div class="px-4 lg:px-0 group">
                <h4 class="font-semibold mb-2 text-white">Information</h4>
                <div class="w-8 h-px bg-gray-400 mb-4 transition-all duration-300 group-hover:w-40"></div>
                <ul class="space-y-2 text-sm">
                    <li><a href="/about" class="text-gray-300 hover:text-white flex items-center"><span class="mr-2">></span>About Us</a></li>
                    <li><a href="/delivery" class="text-gray-300 hover:text-white flex items-center"><span class="mr-2">></span>Delivery</a></li>
                    <li><a href="/terms" class="text-gray-300 hover:text-white flex items-center"><span class="mr-2">></span>Terms & Conditions</a></li>
                    <li><a href="/privacy" class="text-gray-300 hover:text-white flex items-center"><span class="mr-2">></span>Privacy Policy</a></li>
                    <li><a href="/flash-club" class="text-gray-300 hover:text-white flex items-center"><span class="mr-2">></span>Flash Club</a></li>
                </ul>
            </div>

<div class="px-2 lg:px-0 group">
    <h4 class="font-semibold mb-2 text-white">Contact Us</h4>
    <div class="w-8 h-px bg-gray-400 mb-4 transition-all duration-300 group-hover:w-20"></div>
    <div class="space-y-3 text-sm pr-2">
        <!-- Location - SEKARANG RATA -->
        <div class="flex items-center space-x-2">
            <i class="fas fa-map-marker-alt text-gray-300 flex-shrink-0 text-xs w-3 text-center"></i>
            <span class="text-gray-300 leading-tight">West Jakarta, Indonesia</span>
        </div>
        
        <!-- Phone - TETAP RATA -->
        <div class="flex items-center space-x-2">
            <i class="fas fa-phone text-gray-300 flex-shrink-0 text-xs w-3 text-center"></i>
            <a href="tel:081313911391" class="text-gray-300 hover:text-white">081313911391</a>
        </div>
        
        <!-- Email - SEKARANG RATA -->
        <div class="flex items-center space-x-2">
            <i class="fas fa-envelope text-gray-300 flex-shrink-0 text-xs w-3 text-center"></i>
            <div class="min-w-0 flex-1">
                <a href="mailto:hello@sneakersflash.com" class="text-gray-300 hover:text-white leading-tight break-all text-xs sm:text-sm">hello@sneakersflash.com</a>
            </div>
        </div>
    </div>
</div>
</div>


        <!-- Social Media Icons -->
        <div class="border-t border-gray-700 mt-8 pt-8 text-center">
            <div class="max-w-7xl mx-auto px-4 lg:px-8">
                <div class="flex justify-center space-x-6">
                    <a href="https://instagram.com/sneakersflash" target="_blank" class="text-gray-300 hover:text-white transition-colors">
                        <i class="fab fa-instagram text-2xl"></i>
                    </a>
                    <a href="https://tiktok.com/@sneakersflash" target="_blank" class="text-gray-300 hover:text-white transition-colors">
                        <i class="fab fa-tiktok text-2xl"></i>
                    </a>
                    <a href="https://facebook.com/sneakersflash" target="_blank" class="text-gray-300 hover:text-white transition-colors">
                        <i class="fab fa-facebook text-2xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</footer>


<!-- WhatsApp Floating Button -->
<div id="whatsapp-button" class="whatsapp-float-btn">
    <a href="https://wa.me/6281313911391?text=Halo%20SneakerFlash!%20Saya%20ingin%20bertanya%20tentang%20produk%20sneakers" 
       target="_blank" 
       rel="noopener noreferrer"
       class="whatsapp-btn-link">
        <i class="fab fa-whatsapp"></i>
        <span class="whatsapp-text">Chat WhatsApp</span>
    </a>
</div>


<!-- Optional: JavaScript untuk additional functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const whatsappBtn = document.getElementById('whatsapp-button');
    
    // Optional: Hide/show based on scroll
    let lastScrollTop = 0;
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > lastScrollTop) {
            // Scrolling down
            whatsappBtn.style.transform = 'translateY(100px)';
            whatsappBtn.style.opacity = '0.7';
        } else {
            // Scrolling up
            whatsappBtn.style.transform = 'translateY(0)';
            whatsappBtn.style.opacity = '1';
        }
        lastScrollTop = scrollTop;
    });
    
    // Optional: Click tracking (untuk analytics)
    whatsappBtn.addEventListener('click', function() {
        console.log('WhatsApp button clicked');
        // Tambahkan Google Analytics atau tracking lainnya di sini
        // gtag('event', 'click', { 'event_category': 'WhatsApp', 'event_label': 'Floating Button' });
    });
});

</script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Fungsi untuk memperbaiki spacing banner saat zoom
    function fixBannerSpacing() {
        const carouselWrapper = document.querySelector('.carousel-wrapper');
        if (carouselWrapper) {
            carouselWrapper.style.marginTop = '-1px';
            
            // Cari elemen di atas banner dan hapus margin/padding-nya
            const header = document.querySelector('header');
            if (header) header.style.marginBottom = '0';
            
            const nav = document.querySelector('nav');
            if (nav) nav.style.marginBottom = '0';
            
            // Force semua slide gambar menggunakan contain
            const slideImages = document.querySelectorAll('.carousel-slide img');
            slideImages.forEach(img => {
                img.style.objectFit = 'contain';
                img.style.objectPosition = 'center';
            });
        }
    }
    
    // Jalankan fungsi saat halaman dimuat
    fixBannerSpacing();
    
    // Jalankan fungsi saat window di-resize (termasuk zoom)
    window.addEventListener('resize', fixBannerSpacing);
    
    // Tangkap event zoom
    window.addEventListener('wheel', function(e) {
        if (e.ctrlKey) {
            fixBannerSpacing();
        }
    });
});
</script>
@stack('scripts')

</body>
</html>