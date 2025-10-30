{{-- File: resources/views/frontend/checkout/index.blade.php - COMPLETE NO TAX VERSION + VOUCHER SUPPORT --}}
@extends('layouts.app')

@section('title', 'Checkout - SneakerFlash')

@section('content')
<meta name="midtrans-client-key" content="{{ config('services.midtrans.client_key') }}">
<meta name="midtrans-production" content="{{ config('services.midtrans.is_production') ? 'true' : 'false' }}">
<meta name="cart-subtotal" content="{{ $subtotal ?? 0 }}">
<meta name="total-weight" content="{{ $totalWeight ?? 1000 }}">
<meta name="user-authenticated" content="{{ Auth::check() ? 'true' : 'false' }}">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="user-has-primary-address" content="{{ $userHasPrimaryAddress ? 'true' : 'false' }}">
<meta name="primary-address-id" content="{{ $primaryAddressId ?? 'null' }}">
<meta name="authenticated-user-name" content="{{ $authenticatedUserName ?? '' }}">
<meta name="authenticated-user-phone" content="{{ $authenticatedUserPhone ?? '' }}">
<meta name="store-origin-city" content="{{ env('STORE_ORIGIN_CITY_NAME', 'Jakarta') }}">

{{-- CRITICAL FIX: Add user email meta tag --}}
@if(Auth::check())
    <meta name="user-email" content="{{ Auth::user()->email }}">
@endif
@if($appliedVoucher ?? false)
    <meta name="applied-voucher-code" content="{{ $appliedVoucher['voucher_code'] }}">
    <meta name="applied-voucher-discount" content="{{ $appliedVoucher['discount_amount'] }}">
@endif
@php
    $primaryAddressId = null;
    $userHasPrimaryAddress = false;
    
    if(Auth::check()) {
        $primaryAddressQuery = \App\Models\UserAddress::where('user_id', Auth::id())
                                    ->where('is_primary', true)
                                    ->where('is_active', true)
                                    ->first();
        if($primaryAddressQuery) {
            $primaryAddressId = $primaryAddressQuery->id;
            $userHasPrimaryAddress = true;
        }
    }
    
    $authenticatedUserName = Auth::check() ? Auth::user()->name : '';
    $authenticatedUserPhone = Auth::check() ? Auth::user()->phone : '';
    $authenticatedUserEmail = Auth::check() ? Auth::user()->email : '';
@endphp

<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Checkout</h1>
        
        <!-- Step Indicator LAGI DI DISABLE -->
        <!-- <div class="flex justify-between mb-8 px-8">
            <div class="step active" id="step-1">
                <div class="step-number">1</div>
                <div class="step-title">Personal Information</div>
            </div>
            <div class="step" id="step-2">
                <div class="step-number">2</div>
                <div class="step-title">Delivery Address</div>
            </div>
            <div class="step" id="step-3">
                <div class="step-number">3</div>
                <div class="step-title">Shipping Method</div>
            </div>
            <div class="step" id="step-4">
                <div class="step-number">4</div>
                <div class="step-title">Payment</div>
            </div>
        </div> -->
        
        @if ($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif

        <!-- Connection Status -->
        <div id="connection-status" class="mb-4 p-3 rounded-lg border hidden">
            <span id="status-text"></span>
        </div>

        <form action="{{ route('checkout.store') }}" method="POST" id="checkout-form">
            @csrf
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column: Checkout Steps -->
                <div class="lg:col-span-2">
                    
                    <!-- Step 1: Personal Information -->
                    <div class="checkout-section active" id="section-1">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-6">Personal Information</h2>
                            
                            @if(!Auth::check())
                                <!-- Login Option -->
                                <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                                    <p class="text-sm text-gray-700 mb-3">Already have an account? 
                                        <a href="{{ route('login') }}" class="text-blue-600 hover:underline font-medium">Log in instead!</a>
                                    </p>
                                    
                                    <p class="text-sm text-gray-600 mb-3">Or connect with social account:</p>
                                    <a href="{{ route('auth.google') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
                                        Google
                                    </a>
                                </div>
                            @endif
                            
                            <!-- Gender Selection -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                                <div class="flex space-x-4">
                                    <label class="flex items-center">
                                        <input type="radio" name="gender" value="mens" class="mr-2" 
                                               {{ old('gender', Auth::user()->gender ?? '') == 'mens' ? 'checked' : '' }}>
                                        <span class="text-sm">Mens</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="gender" value="womens" class="mr-2" 
                                               {{ old('gender', Auth::user()->gender ?? '') == 'womens' ? 'checked' : '' }}>
                                        <span class="text-sm">Womens</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Name Fields - FIXED: Better auto-fill -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First name *</label>
                                    <input type="text" name="first_name" id="first_name" required
                                           value="{{ old('first_name', Auth::check() ? (explode(' ', Auth::user()->name ?? '', 2)[0] ?? '') : '') }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last name *</label>
                                    <input type="text" name="last_name" id="last_name" required
                                           value="{{ old('last_name', Auth::check() ? (explode(' ', Auth::user()->name ?? '', 2)[1] ?? '') : '') }}"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <!-- Email - FIXED: Proper auto-fill -->
                            <div class="mb-4">
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                                <input type="email" name="email" id="email" required
                                       value="{{ old('email', $authenticatedUserEmail ?? (Auth::user()->email ?? '')) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- Phone - FIXED: Proper auto-fill -->
                            <div class="mb-4">
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone *</label>
                                <input type="tel" name="phone" id="phone" required
                                       value="{{ old('phone', $authenticatedUserPhone ?? (Auth::user()->phone ?? '')) }}"
                                       placeholder="08xxxxxxxxxx"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- Birthdate -->
<div class="mb-4">
    <label for="birthdate" class="block text-sm font-medium text-gray-700 mb-2">
        <span class="text-gray-400">Optional</span> Birthdate
    </label>
    <input type="date" name="birthdate" id="birthdate"
           value="{{ old('birthdate', Auth::check() && Auth::user()->birthdate ? Auth::user()->birthdate->format('Y-m-d') : '') }}"
           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
</div>
                            
                            @if(!Auth::check())
                                <!-- Create Account Option -->
                                <div class="mb-4">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="create_account" id="create_account" value="1" 
                                               onchange="togglePassword()" class="mr-3" {{ old('create_account') ? 'checked' : '' }}>
                                        <span class="text-sm font-medium">Create an account (optional)</span>
                                    </label>
                                    <p class="text-xs text-gray-500 mt-1">Save time on your next order!</p>
                                </div>
                                
                                <!-- Password Fields -->
                                <div id="password-fields" class="hidden mb-4">
                                    <div class="mb-4">
                                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                                        <input type="password" name="password" id="password" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                        <p class="text-xs text-gray-500 mt-1">Enter a password between 8 and 72 characters</p>
                                    </div>
                                    <div>
                                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                                        <input type="password" name="password_confirmation" id="password_confirmation"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                    </div>
                                </div>
                            @endif
                            
                            <!-- Newsletter -->
                            <div class="mb-4">
                                <label class="flex items-start">
                                    <input type="checkbox" name="newsletter_subscribe" value="1" class="mr-3 mt-1" {{ old('newsletter_subscribe') ? 'checked' : '' }}>
                                    <div>
                                        <span class="text-sm font-medium">Sign up for our newsletter</span>
                                        <p class="text-xs text-gray-500 italic">*Get exclusive offers and early discounts*</p>
                                    </div>
                                </label>
                            </div>
                            
                            <!-- Privacy Policy -->
                            <div class="mb-6">
                                <label class="flex items-start">
                                    <input type="checkbox" 
                                           name="privacy_accepted" 
                                           id="privacy_accepted" 
                                           value="1"
                                           required 
                                           class="mr-3 mt-1"
                                           {{ old('privacy_accepted') ? 'checked' : '' }}>
                                    <div>
                                        <span class="text-sm font-medium">Customer data privacy *</span>
                                        <p class="text-xs text-gray-500 italic">*I agree to the processing of my personal data and accept the privacy policy.*</p>
                                    </div>
                                </label>
                            </div>
                            
                            <button type="button" onclick="nextStep(2)" 
                                    class="w-full bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                Continue
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: Delivery Information (Updated with Hierarchical) -->
<div id="section-2" class="checkout-section hidden">
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-900 mb-4">📦 Delivery Information</h2>
        
        <!-- Address Selection Options -->
        <div class="mb-6">
            <div class="flex space-x-4">
                <button type="button" id="use-saved-address-btn" 
                        class="px-4 py-2 border border-orange-500 text-orange-500 rounded-lg hover:bg-orange-50 transition-colors">
                    Use Saved Address
                </button>
                <button type="button" id="use-new-address-btn"
                        class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                    Enter New Address
                </button>
            </div>
        </div>

        <!-- Saved Addresses Section -->
        <div id="saved-addresses-section" class="hidden mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-3">Select Saved Address</h3>
            <div id="saved-addresses-list" class="space-y-3">
                <!-- Will be populated by JavaScript -->
            </div>
        </div>

        <!-- New Address Form -->
        <div id="new-address-section">
            <!-- Address Label -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-3">
                    Address Label *
                </label>
                <div class="flex space-x-4">
                    <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="radio" name="address_label" value="Kantor" class="mr-3 text-orange-500">
                        <span class="text-sm font-medium">Kantor</span>
                    </label>
                    <label class="flex items-center p-3 border border-orange-500 bg-orange-50 rounded-lg cursor-pointer">
                        <input type="radio" name="address_label" value="Rumah" class="mr-3 text-orange-500" checked>
                        <span class="text-sm font-medium">Rumah</span>
                    </label>
                </div>
            </div>

            <!-- Recipient Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="recipient_name" class="block text-sm font-medium text-gray-700 mb-2">
                        Recipient Name *
                    </label>
                    <input type="text" name="recipient_name" id="recipient_name" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
                <div>
                    <label for="phone_recipient" class="block text-sm font-medium text-gray-700 mb-2">
                        Phone Number *
                    </label>
                    <input type="tel" name="phone_recipient" id="phone_recipient" required
                           placeholder="08xxxxxxxxxx"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                </div>
            </div>

            <!-- Hierarchical Location Selection -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Location Selection</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Province -->
                    <div>
                        <label for="checkout_province_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Province *
                        </label>
                        <select name="province_id" id="checkout_province_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                            <option value="">Loading provinces...</option>
                        </select>
                        <input type="hidden" name="province_name" id="checkout_province_name">
                    </div>

                    <!-- City -->
                    <div>
                        <label for="checkout_city_id" class="block text-sm font-medium text-gray-700 mb-2">
                            City/Regency *
                        </label>
                        <select name="city_id" id="checkout_city_id" required disabled
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500 disabled:bg-gray-100">
                            <option value="">Select province first...</option>
                        </select>
                        <input type="hidden" name="city_name" id="checkout_city_name">
                    </div>

                    <!-- District -->
                    <div>
                        <label for="checkout_district_id" class="block text-sm font-medium text-gray-700 mb-2">
                            District *
                        </label>
                        <select name="district_id" id="checkout_district_id" required disabled
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500 disabled:bg-gray-100">
                            <option value="">Select city first...</option>
                        </select>
                        <input type="hidden" name="district_name" id="checkout_district_name">
                    </div>

                    <!-- Sub District -->
                    <div>
                        <label for="checkout_sub_district_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Sub District *
                        </label>
                        <select name="sub_district_id" id="checkout_sub_district_id" required disabled
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500 disabled:bg-gray-100">
                            <option value="">Select district first...</option>
                        </select>
                        <input type="hidden" name="subdistrict_name" id="checkout_subdistrict_name">
                    </div>
                </div>

                <!-- Postal Code Display -->
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Postal Code
                    </label>
                    <input type="text" id="checkout_postal_code_display" readonly
                           class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md" 
                           placeholder="Will be filled automatically">
                    <input type="hidden" name="postal_code" id="checkout_postal_code">
                </div>
            </div>

            <!-- Street Address -->
            <div class="mb-4">
                <label for="street_address" class="block text-sm font-medium text-gray-700 mb-2">
                    Street Address *
                </label>
                <textarea name="street_address" id="street_address" rows="3" required
                          placeholder="Enter complete street address, building name, house number"
                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500"></textarea>
            </div>

            <!-- Save Address Option -->
            <div class="mb-4">
                <label class="flex items-center">
                    <input type="checkbox" name="save_address" value="1" 
                           class="mr-3 h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded">
                    <span class="text-sm font-medium">Save this address for future use</span>
                </label>
            </div>
        </div>

        <!-- Hidden fields for compatibility -->
        <input type="hidden" name="destination_id" id="destination_id">
        <input type="hidden" name="destination_label" id="destination_label">

        <!-- Navigation Buttons -->
        <div class="flex space-x-4 pt-4">
            <button type="button" onclick="prevStep(1)"  
                    class="flex-1 px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                Previous
            </button>
            <button type="button" onclick="nextStep(3)" 
                    class="flex-1 px-6 py-3 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition-colors">
                Continue to Shipping
            </button>
        </div>
    </div>
</div>

                    <!-- Step 3: Shipping Method -->
                    <div class="checkout-section hidden" id="section-3">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-6">Shipping Method</h2>
                            <p class="text-sm text-gray-600 mb-4">Package weight: <strong>{{ $totalWeight ?? 1000 }}g</strong></p>
                            
                            <div id="shipping-loading" class="hidden p-4 text-center">
                                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                                <p class="text-sm text-gray-600 mt-2">Calculating shipping options...</p>
                            </div>
                            
                            <div id="shipping-options" class="space-y-3 min-h-[150px]">
                                <div class="p-4 text-center text-gray-500 border-2 border-dashed border-gray-200 rounded-lg">
                                    <p>📍 Please select your delivery location first</p>
                                </div>
                            </div>
                            
                            <input type="hidden" name="shipping_method" id="shipping_method" required>
                            <input type="hidden" name="shipping_cost" id="shipping_cost" value="0" required>
                            <!-- Voucher Hidden Inputs -->
<input type="hidden" name="applied_voucher_code" id="applied_voucher_code" value="{{ $appliedVoucher['voucher_code'] ?? '' }}">
<input type="hidden" name="applied_voucher_discount" id="applied_voucher_discount" value="{{ $appliedVoucher['discount_amount'] ?? 0 }}">
                            <div class="flex space-x-4 mt-8">
                                <button type="button" onclick="prevStep(2)" 
                                        class="flex-1 bg-gray-300 text-gray-700 py-3 rounded-lg hover:bg-gray-400 transition-colors font-medium">
                                    Previous
                                </button>
                                <button type="button" onclick="nextStep(4)" id="continue-step-3"
                                        class="flex-1 bg-blue-600 text-white py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                                    Continue
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Payment -->
                    <div class="checkout-section hidden" id="section-4">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-6">Payment Method</h2>
                            <p class="text-sm text-gray-600 mb-6">Choose your preferred payment method. All payments are processed securely through Midtrans.</p>
                            
                            <div class="space-y-3 mb-6">
                                <!-- Online Payment Option -->
                                <label class="flex items-center p-4 border-2 rounded-lg cursor-pointer hover:bg-gray-50 border-blue-200 bg-blue-50">
                                    <input type="radio" name="payment_method" value="credit_card" checked class="mr-4">
                                    <div class="flex-1">
                                        <div class="font-medium text-blue-800">Online Payment</div>
                                        <div class="text-sm text-blue-600">Credit Card, Bank Transfer, E-Wallet, and more via Midtrans</div>
                                        <div class="flex items-center mt-2 space-x-2">
                                            <span class="text-xs bg-white px-2 py-1 rounded border">💳 Credit Card</span>
                                            <span class="text-xs bg-white px-2 py-1 rounded border">🏦 Bank Transfer</span>
                                            <span class="text-xs bg-white px-2 py-1 rounded border">📱 E-Wallet</span>
                                            <span class="text-xs bg-white px-2 py-1 rounded border">+ More</span>
                                        </div>
                                    </div>
                                </label>
                            </div>
                            
                            <!-- Payment Info -->
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-blue-800">Secure Payment</h3>
                                        <p class="text-sm text-blue-700 mt-1">
                                            All online payments are processed securely through Midtrans. 
                                            You'll see all available payment methods after clicking "Continue to Payment".
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Order Notes -->
                            <div class="mb-6">
                                <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Order Notes (Optional)</label>
                                <textarea name="notes" id="notes" rows="3" 
                                          placeholder="Any special instructions for your order?"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">{{ old('notes') }}</textarea>
                            </div>
                            
                            <div class="flex space-x-4 mt-8">
                                <button type="button" onclick="prevStep(3)" 
                                        class="flex-1 bg-gray-300 text-gray-700 py-3 rounded-lg hover:bg-gray-400 transition-colors font-medium">
                                    Previous
                                </button>
                                <button type="submit" id="place-order-btn" 
                                        class="flex-1 bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 transition-colors font-medium">
                                    Continue to Payment
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Order Summary - NO TAX VERSION + VOUCHER SUPPORT -->
                <div class="lg:col-span-1">
                    <div class="lg:col-span-1">
    <div class="bg-white rounded-lg shadow-md p-6 sticky top-8 checkout-section hidden" id="order-summary-section">
                        
                        <!-- Cart Items Display - FIXED: Show actual cart items -->
                        <div class="space-y-3 mb-4">
                            @if(isset($cartItems) && $cartItems->count() > 0)
                                @foreach($cartItems as $item)
                                    @php
                                        // Clean product name display
                                        $originalName = $item['name'] ?? 'Unknown Product';
                                        $skuParent = $item['sku_parent'] ?? '';
                                        
                                        $cleanProductName = $originalName;
                                        if (!empty($skuParent)) {
                                            $cleanProductName = preg_replace('/\s*-\s*' . preg_quote($skuParent, '/') . '\s*/', '', $cleanProductName);
                                            $cleanProductName = preg_replace('/\s*' . preg_quote($skuParent, '/') . '\s*/', '', $cleanProductName);
                                        }
                                        
                                        // Remove size patterns
                                        $cleanProductName = preg_replace('/\s*-\s*Size\s+[A-Z0-9.]+\s*$/i', '', $cleanProductName);
                                        $cleanProductName = preg_replace('/\s*Size\s+[A-Z0-9.]+\s*$/i', '', $cleanProductName);
                                        $cleanProductName = preg_replace('/\s*-\s*(XS|S|M|L|XL|XXL|XXXL|[0-9]+|[0-9]+\.[0-9]+)\s*$/i', '', $cleanProductName);
                                        
                                        $cleanProductName = trim($cleanProductName, ' -');
                                        
                                        // Size information
                                        $itemSize = 'One Size';
                                        if (isset($item['size']) && !empty($item['size'])) {
                                            if (is_array($item['size'])) {
                                                $itemSize = $item['size'][0] ?? 'One Size';
                                            } else {
                                                $itemSize = (string) $item['size'];
                                            }
                                        } elseif (isset($item['product_options']['size'])) {
                                            $itemSize = $item['product_options']['size'] ?? 'One Size';
                                        }
                                        
                                        $hasValidSize = !empty($itemSize) && 
                                                      $itemSize !== 'One Size' && 
                                                      $itemSize !== 'Default' &&
                                                      !is_array($itemSize);

                                        // Image URL with proper fallback
                                        $imageUrl = asset('images/default-product.jpg');
                                        if (!empty($item['image'])) {
                                            $imagePath = $item['image'];
                                            if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                                                $imageUrl = $imagePath;
                                            } elseif (str_starts_with($imagePath, '/storage/')) {
                                                $imageUrl = config('app.url') . $imagePath;
                                            } elseif (str_starts_with($imagePath, 'products/')) {
                                                $imageUrl = config('app.url') . '/storage/' . $imagePath;
                                            } elseif (str_starts_with($imagePath, 'assets/') || str_starts_with($imagePath, 'images/')) {
                                                $imageUrl = asset($imagePath);
                                            } else {
                                                $imageUrl = config('app.url') . '/storage/products/' . $imagePath;
                                            }
                                        }
                                    @endphp
                                    
                                    <div class="flex items-center space-x-3 order-summary-item" 
                                         data-name="{{ $cleanProductName }}"
                                         data-quantity="{{ $item['quantity'] ?? 1 }}"
                                         data-price="{{ $item['price'] ?? 0 }}"
                                         data-subtotal="{{ $item['subtotal'] ?? 0 }}"
                                         data-size="{{ $hasValidSize ? $itemSize : '' }}"
                                         data-image="{{ $imageUrl }}">
                                        <img src="{{ $imageUrl }}" 
                                             alt="{{ $cleanProductName }}" 
                                             class="w-12 h-12 object-cover rounded"
                                             onerror="this.src='{{ asset('images/default-product.jpg') }}'">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">{{ $cleanProductName }}</p>
                                            @if($hasValidSize)
                                                <p class="text-xs text-gray-500">Size: {{ $itemSize }}</p>
                                            @endif
                                            <p class="text-sm text-gray-500">Qty: {{ $item['quantity'] ?? 1 }}</p>
                                        </div>
                                        <span class="text-sm font-medium text-gray-900">
                                            Rp {{ number_format($item['subtotal'] ?? 0, 0, ',', '.') }}
                                        </span>
                                    </div>
                                @endforeach
                            @else
                                <div class="p-4 text-center text-gray-500 border-2 border-dashed border-gray-200 rounded-lg">
                                    <p>No items in cart</p>
                                </div>
                            @endif
                        </div>
                        
                        <!-- VOUCHER/COUPON SECTION - NEW -->
                        <div class="border-t border-gray-200 pt-4 mb-4">
    <h4 class="text-sm font-medium text-gray-900 mb-3">🎫 Voucher Code</h4>
    
    <!-- Voucher Message Container -->
    <div id="voucher-message-container" class="hidden"></div>
    
    <!-- Applied Voucher Display -->
    <div id="applied-voucher-container" class="hidden mb-4"></div>
    
    <!-- Voucher Input Section -->
    <div id="voucher-input-section" class="mb-4">
        <div class="flex space-x-2">
            <input type="text" 
                   id="voucher-code" 
                   placeholder="Enter voucher code"
                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   maxlength="50">
            <button type="button" 
                    id="apply-voucher-btn"
                    class="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors font-medium">
                Apply
            </button>
        </div>
        <div id="voucher-validation-message" class="hidden"></div>
    </div>
    
    <!-- Available Vouchers Display -->
    <div id="available-vouchers-container" class="hidden"></div>
</div>

<!-- POINTS EXCHANGE SECTION - NEW -->
@if(Auth::check() && (Auth::user()->points_balance ?? 0) > 0)
<div class="border-t border-gray-200 pt-4 mb-4">
    <h4 class="text-sm font-medium text-gray-900 mb-3">🪙 Tukar Poin</h4>
    
    <!-- Points Message Container -->
    <div id="points-message-container" class="hidden"></div>
    
    <!-- Points Exchange Toggle -->
    <div class="flex items-center justify-between p-3 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors points-toggle-container">
        <div class="flex items-center">
            <input type="checkbox" 
                   id="use-points-toggle" 
                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                   data-user-points="{{ Auth::user()->points_balance ?? 0 }}">
            <div class="ml-3">
                <label for="use-points-toggle" class="text-sm font-medium text-gray-900 cursor-pointer">
                    Tukarkan <strong>{{ number_format(Auth::user()->points_balance ?? 0, 0, ',', '.') }} Point</strong>
                </label>
                <p class="text-xs text-gray-500">
                    Hemat Rp {{ number_format(Auth::user()->points_balance ?? 0, 0, ',', '.') }} dengan menggunakan poin Anda
                </p>
            </div>
        </div>
        <div class="text-right">
            <div class="text-sm font-semibold text-green-600">
                -Rp {{ number_format(Auth::user()->points_balance ?? 0, 0, ',', '.') }}
            </div>
            <div class="text-xs text-gray-500">Potongan</div>
        </div>
    </div>
    
    <!-- Points Details (Hidden by default) -->
    <div id="points-details" class="hidden mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
        <div class="flex items-start">
            <svg class="w-4 h-4 text-yellow-600 mt-0.5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-xs text-yellow-800">
                <p class="font-medium">Cara Kerja Penukaran Poin:</p>
                <ul class="mt-1 space-y-1">
                    <li>• 1 Poin = Rp 1 potongan harga</li>
                    <li>• Poin dapat dikombinasikan dengan voucher</li>
                    <li>• Poin yang digunakan tidak dapat dikembalikan</li>
                    <li>• Maksimal penggunaan: {{ number_format(Auth::user()->points_balance ?? 0, 0, ',', '.') }} poin</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Points Input Section (Custom Amount) -->
    <div id="points-input-section" class="hidden mt-3">
        <div class="flex space-x-2">
            <input type="number" 
                   id="points-amount" 
                   placeholder="Masukkan jumlah poin"
                   min="1"
                   max="{{ Auth::user()->points_balance ?? 0 }}"
                   class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            <button type="button" 
                    id="apply-points-btn"
                    class="px-4 py-2 bg-yellow-600 text-white text-sm rounded-md hover:bg-yellow-700 transition-colors font-medium">
                Terapkan
            </button>
        </div>
        <div class="mt-2 flex justify-between text-xs text-gray-600">
            <span>Tersedia: {{ number_format(Auth::user()->points_balance ?? 0, 0, ',', '.') }} poin</span>
            <button type="button" id="use-all-points" class="text-blue-600 hover:text-blue-800 font-medium">
                Gunakan Semua
            </button>
        </div>
    </div>
    
    <!-- Applied Points Display -->
    <div id="applied-points-container" class="hidden mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <svg class="w-4 h-4 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div>
                    <p class="text-sm font-medium text-green-800">
                        <span id="used-points-amount">0</span> poin digunakan
                    </p>
                    <p class="text-xs text-green-700">
                        Potongan: Rp <span id="points-discount-amount">0</span>
                    </p>
                </div>
            </div>
            <button type="button" 
                    id="remove-points-btn"
                    class="text-red-600 hover:text-red-800 text-sm font-medium">
                Hapus
            </button>
        </div>
    </div>
    
    <!-- Hidden Inputs for Points -->
    <input type="hidden" name="points_used" id="points_used" value="{{ $pointsUsed ?? 0 }}">
    <input type="hidden" name="points_discount" id="points_discount" value="{{ $pointsDiscount ?? 0 }}">
</div>
@endif

{{-- Update Order Totals section untuk menyertakan Points Discount --}}
<!-- Order Totals - WITH POINTS SUPPORT -->
<div class="border-t border-gray-200 pt-4 space-y-2">
    <div class="flex justify-between text-sm">
        <span class="text-gray-600">Subtotal</span>
        <span class="text-gray-900" data-subtotal-display>
            Rp {{ number_format($subtotal ?? 0, 0, ',', '.') }}
        </span>
    </div>
    
    <!-- Voucher Discount Row -->
    <div class="discount-row flex justify-between text-sm {{ ($discountAmount ?? 0) > 0 ? '' : 'hidden' }}">
        <span class="text-gray-600">Voucher Discount 
            @if($appliedVoucher ?? false)
                <span class="text-xs text-green-600">({{ $appliedVoucher['voucher_code'] }})</span>
            @endif
        </span>
        <span class="text-green-600 font-medium" data-discount-display>
            -Rp {{ number_format($discountAmount ?? 0, 0, ',', '.') }}
        </span>
    </div>
    
    <!-- Points Discount Row - NEW -->
    <div class="points-discount-row flex justify-between text-sm {{ ($pointsDiscount ?? 0) > 0 ? '' : 'hidden' }}">
        <span class="text-gray-600">Points Discount</span>
        <span class="text-yellow-600 font-medium" data-points-discount-display>
            -Rp {{ number_format($pointsDiscount ?? 0, 0, ',', '.') }}
        </span>
    </div>
    
    <!-- Shipping Cost Row -->
    <div class="flex justify-between text-sm">
        <span class="text-gray-600">Shipping</span>
        <span class="text-gray-900" id="shipping-cost-display" data-shipping-display>
            To be calculated
        </span>
    </div>
    
    <!-- Total Row -->
    <div class="border-t border-gray-200 pt-2">
        <div class="flex justify-between text-base font-medium">
            <span class="text-gray-900">Total</span>
            <span class="text-gray-900" id="total-display" data-total-display>
                Rp {{ number_format(($subtotal ?? 0) - ($discountAmount ?? 0) - ($pointsDiscount ?? 0), 0, ',', '.') }}
            </span>
        </div>
    </div>
</div>
                        
                       

                        <!-- Order Info - Additional helpful info -->
                        @if(isset($cartItems) && $cartItems->count() > 0)
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <div class="flex justify-between text-xs text-gray-500 mb-2">
                                    <span>Total Items:</span>
                                    <span>{{ $cartItems->count() }} product(s)</span>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mb-2">
                                    <span>Total Quantity:</span>
                                    <span>{{ $cartItems->sum('quantity') }} pcs</span>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Estimated Weight:</span>
                                    <span>{{ number_format($totalWeight ?? 1000) }}g</span>
                                </div>
                            </div>
                        @endif

                        <!-- Voucher Benefits Info -->
                        @if($appliedVoucher ?? false)
    <div class="mt-4 pt-4 border-t border-gray-200">
        <div class="bg-green-50 p-3 rounded-lg">
            <div class="flex items-center text-green-800 text-sm">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <strong>{{ $appliedVoucher['voucher_code'] }} Applied!</strong>
            </div>
            <p class="text-xs text-green-700 mt-1">{{ $appliedVoucher['summary'] ?? 'Discount applied to your order' }}</p>
            <p class="text-xs text-green-600 mt-1 font-medium">You saved: Rp {{ number_format($discountAmount ?? 0, 0, ',', '.') }}</p>
        </div>
    </div>
@endif

                        <!-- Security Info -->
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            <div class="flex items-center justify-center text-xs text-gray-500">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Secure checkout
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Custom Radio Button Styles -->
<style>
    /* FORCE HIDE SECTIONS - TAMBAHKAN DI AWAL */
.checkout-section {
    transition: none !important;
}

.checkout-section.hidden {
    display: none !important;
    visibility: hidden !important;
    opacity: 0 !important;
    position: absolute !important;
    left: -9999px !important;
}

.checkout-section.active {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    position: relative !important;
    left: auto !important;
}
.radio-custom {
    width: 20px;
    height: 20px;
    border: 2px solid #d1d5db;
    border-radius: 50%;
    position: relative;
    background: white;
    transition: all 0.2s ease;
}

.radio-custom::after {
    content: '';
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ea580c;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0);
    transition: transform 0.2s ease;
}

input[type="radio"]:checked + .radio-custom {
    border-color: #ea580c;
}

input[type="radio"]:checked + .radio-custom::after {
    transform: translate(-50%, -50%) scale(1);
}

label:has(input[name="address_label"]:checked) {
    border-color: #ea580c !important;
    background-color: #fff7ed !important;
}

/* Saved Address Selection Styles */
label:has(input[name="saved_address_id"]:checked) {
    border-color: #ea580c !important;
    background-color: #fff7ed !important;
}

.step {
    flex: 1;
    text-align: center;
    position: relative;
}

.step.active .step-number {
    background-color: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.step.completed .step-number {
    background-color: #10b981;
    color: white;
    border-color: #10b981;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #e5e7eb;
    color: #6b7280;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    font-weight: 600;
    border: 3px solid #e5e7eb;
    transition: all 0.3s ease;
}

.step-title {
    font-size: 0.875rem;
    color: #6b7280;
    transition: all 0.3s ease;
}

.step.active .step-title {
    color: #3b82f6;
    font-weight: 600;
}

.step.completed .step-title {
    color: #10b981;
    font-weight: 500;
}

.checkout-section.active {
    display: block !important;
}

#location-results .search-result-item {
    padding: 12px;
    cursor: pointer;
    border-bottom: 1px solid #e5e7eb;
    transition: background-color 0.2s ease;
}

#location-results .search-result-item:hover {
    background-color: #f3f4f6;
}

#location-results .search-result-item:last-child {
    border-bottom: none;
}

.shipping-option {
    transition: all 0.2s ease;
}

.shipping-option:hover {
    background-color: #f8fafc;
    border-color: #3b82f6;
}

.shipping-option input[type="radio"]:checked + .shipping-content {
    border-color: #3b82f6;
    background-color: #eff6ff;
}
body:has(#step-3.active) #section-2,
body:has(#step-4.active) #section-2 {
    display: none !important;
    visibility: hidden !important;
    position: absolute !important;
    left: -9999px !important;
}

</style>

<!-- Script to ensure Order Summary updates properly WITHOUT TAX + VOUCHER SUPPORT -->
<script>
// Global variables for checkout location
let checkoutLocationData = {
    provinces: [],
    cities: [],
    districts: [],
    subDistricts: []
};

let checkoutSelectedAddress = null;

// Define all functions in global scope
async function loadCheckoutProvinces() {
    const provinceSelect = document.getElementById('checkout_province_id');
    
    try {
        console.log('🔄 Loading provinces for checkout...');
        
        const response = await fetch('/api/addresses/provinces');
        const result = await response.json();
        
        console.log('📡 API Response:', result);
        
        if (result.success && result.data) {
            checkoutLocationData.provinces = result.data;
            
            provinceSelect.innerHTML = '<option value="">Select Province...</option>';
            
            result.data.forEach(province => {
                const option = document.createElement('option');
                option.value = province.id;
                option.textContent = province.name;
                provinceSelect.appendChild(option);
            });
            
            provinceSelect.disabled = false;
            console.log(`✅ Loaded ${result.data.length} provinces for checkout`);
        } else {
            console.error('❌ API returned error:', result);
            provinceSelect.innerHTML = '<option value="">Error loading provinces</option>';
        }
    } catch (error) {
        console.error('❌ Error loading provinces for checkout:', error);
        provinceSelect.innerHTML = '<option value="">Error loading provinces</option>';
    }
}

async function loadCheckoutCities(provinceId) {
    const citySelect = document.getElementById('checkout_city_id');
    
    try {
        console.log('🔄 Loading cities for province:', provinceId);
        const response = await fetch(`/api/addresses/cities/${provinceId}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            checkoutLocationData.cities = result.data;
            
            citySelect.innerHTML = '<option value="">Select City/Regency...</option>';
            
            result.data.forEach(city => {
                const option = document.createElement('option');
                option.value = city.id;
                option.textContent = city.name;
                citySelect.appendChild(option);
            });
            
            citySelect.disabled = false;
            console.log(`✅ Loaded ${result.data.length} cities for checkout`);
        }
    } catch (error) {
        console.error('❌ Error loading cities for checkout:', error);
        citySelect.innerHTML = '<option value="">Error loading cities</option>';
        citySelect.disabled = false;
    }
}

async function loadCheckoutDistricts(cityId) {
    const districtSelect = document.getElementById('checkout_district_id');
    
    try {
        console.log('🔄 Loading districts for city:', cityId);
        const response = await fetch(`/api/addresses/districts/${cityId}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            checkoutLocationData.districts = result.data;
            
            districtSelect.innerHTML = '<option value="">Select District...</option>';
            
            result.data.forEach(district => {
                const option = document.createElement('option');
                option.value = district.id;
                option.textContent = district.name;
                districtSelect.appendChild(option);
            });
            
            districtSelect.disabled = false;
            console.log(`✅ Loaded ${result.data.length} districts for checkout`);
        }
    } catch (error) {
        console.error('❌ Error loading districts for checkout:', error);
        districtSelect.innerHTML = '<option value="">Error loading districts</option>';
        districtSelect.disabled = false;
    }
}

async function loadCheckoutSubDistricts(districtId) {
    const subDistrictSelect = document.getElementById('checkout_sub_district_id');
    
    try {
        console.log('🔄 Loading sub-districts for district:', districtId);
        const response = await fetch(`/api/addresses/sub-districts/${districtId}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            checkoutLocationData.subDistricts = result.data;
            
            subDistrictSelect.innerHTML = '<option value="">Select Sub-District...</option>';
            
            result.data.forEach(subDistrict => {
                const option = document.createElement('option');
                option.value = subDistrict.id;
                option.textContent = subDistrict.name;
                
                if (subDistrict.zip_code) {
                    option.setAttribute('data-zip', subDistrict.zip_code);
                }
                
                subDistrictSelect.appendChild(option);
            });
            
            subDistrictSelect.disabled = false;
            console.log(`✅ Loaded ${result.data.length} sub-districts for checkout`);
        }
    } catch (error) {
        console.error('❌ Error loading sub-districts for checkout:', error);
        subDistrictSelect.innerHTML = '<option value="">Error loading sub-districts</option>';
        subDistrictSelect.disabled = false;
    }
}

function resetCheckoutSelect(selectElement, placeholder) {
    selectElement.innerHTML = `<option value="">${placeholder}</option>`;
    selectElement.disabled = true;
}

function resetAllCheckoutSelects() {
    const citySelect = document.getElementById('checkout_city_id');
    const districtSelect = document.getElementById('checkout_district_id');
    const subDistrictSelect = document.getElementById('checkout_sub_district_id');
    
    resetCheckoutSelect(citySelect, 'Select province first...');
    resetCheckoutSelect(districtSelect, 'Select city first...');
    resetCheckoutSelect(subDistrictSelect, 'Select district first...');
    
    // Clear hidden fields
    const provinceNameField = document.getElementById('checkout_province_name');
    if (provinceNameField) provinceNameField.value = '';
    clearCheckoutPostalCode();
    clearCheckoutDestination();
    
    // Clear selectedDestination
    selectedDestination = null;
}

function clearCheckoutPostalCode() {
    const postalDisplay = document.getElementById('checkout_postal_code_display');
    const postalHidden = document.getElementById('checkout_postal_code');
    if (postalDisplay) postalDisplay.value = '';
    if (postalHidden) postalHidden.value = '';
}

function clearCheckoutDestination() {
    const destId = document.getElementById('destination_id');
    const destLabel = document.getElementById('destination_label');
    const subDistName = document.getElementById('checkout_subdistrict_name');
    
    if (destId) destId.value = '';
    if (destLabel) destLabel.value = '';
    if (subDistName) subDistName.value = '';
    
    // Clear selectedDestination
    selectedDestination = null;
}

// CRITICAL FIX: Function untuk set selectedDestination dengan benar
function setSelectedDestination(subDistrictId, subDistrictName, zipCode) {
    const provinceName = document.getElementById('checkout_province_name')?.value || '';
    const cityName = document.getElementById('checkout_city_name')?.value || '';
    const districtName = document.getElementById('checkout_district_name')?.value || '';
    
    const destinationLabel = `${subDistrictName}, ${districtName}, ${cityName}, ${provinceName}`;
    
    // Set global selectedDestination untuk shipping calculation
    selectedDestination = {
        destination_id: subDistrictId,
        id: subDistrictId,
        label: destinationLabel,
        subdistrict_name: subDistrictName,
        city_name: cityName,
        province_name: provinceName,
        district_name: districtName,
        postal_code: zipCode,
        full_address: destinationLabel
    };
    
    console.log('✅ selectedDestination set:', selectedDestination);
    return selectedDestination;
}

// CRITICAL FIX: Function untuk trigger shipping calculation manual
function triggerShippingCalculation() {
    const destId = document.getElementById('destination_id')?.value;
    if (!destId) {
        console.error('❌ No destination_id found');
        return false;
    }
    
    if (!selectedDestination) {
        // Reconstruct selectedDestination dari form data
        const provinceName = document.getElementById('checkout_province_name')?.value || '';
        const cityName = document.getElementById('checkout_city_name')?.value || '';
        const districtName = document.getElementById('checkout_district_name')?.value || '';
        const subDistrictName = document.getElementById('checkout_subdistrict_name')?.value || '';
        const zipCode = document.getElementById('checkout_postal_code')?.value || '';
        
        selectedDestination = {
            destination_id: destId,
            id: destId,
            label: `${subDistrictName}, ${districtName}, ${cityName}, ${provinceName}`,
            subdistrict_name: subDistrictName,
            city_name: cityName,
            province_name: provinceName,
            district_name: districtName,
            postal_code: zipCode
        };
        
        console.log('🔧 Reconstructed selectedDestination:', selectedDestination);
    }
    
    console.log('🚚 Triggering shipping calculation with:', selectedDestination);
    
    // Call calculateShipping if it exists
    if (typeof calculateShipping === 'function') {
        calculateShipping();
        return true;
    } else {
        console.error('❌ calculateShipping function not found');
        return false;
    }
}

function initializeCheckoutLocation() {
    console.log('🚀 Initializing checkout location selection');
    
    const provinceSelect = document.getElementById('checkout_province_id');
    const citySelect = document.getElementById('checkout_city_id');
    const districtSelect = document.getElementById('checkout_district_id');
    const subDistrictSelect = document.getElementById('checkout_sub_district_id');
    
    if (!provinceSelect) {
        console.error('❌ Province select not found');
        return;
    }
    
    // Load provinces immediately
    loadCheckoutProvinces();
    
    // Province change handler
    provinceSelect.addEventListener('change', function() {
        const provinceId = this.value;
        const provinceName = this.options[this.selectedIndex].text;
        
        console.log('🔄 Province changed:', provinceId, provinceName);
        
        if (provinceId) {
            const provinceNameField = document.getElementById('checkout_province_name');
            if (provinceNameField) provinceNameField.value = provinceName;
            
            loadCheckoutCities(provinceId);
            resetCheckoutSelect(citySelect, 'Loading cities...');
            resetCheckoutSelect(districtSelect, 'Select city first...');
            resetCheckoutSelect(subDistrictSelect, 'Select district first...');
            clearCheckoutPostalCode();
            selectedDestination = null; // Clear when province changes
        } else {
            resetAllCheckoutSelects();
        }
    });
    
    // City change handler
    citySelect.addEventListener('change', function() {
        const cityId = this.value;
        const cityName = this.options[this.selectedIndex].text;
        
        console.log('🔄 City changed:', cityId, cityName);
        
        if (cityId) {
            const cityNameField = document.getElementById('checkout_city_name');
            if (cityNameField) cityNameField.value = cityName;
            
            loadCheckoutDistricts(cityId);
            resetCheckoutSelect(districtSelect, 'Loading districts...');
            resetCheckoutSelect(subDistrictSelect, 'Select district first...');
            clearCheckoutPostalCode();
            selectedDestination = null; // Clear when city changes
        } else {
            resetCheckoutSelect(districtSelect, 'Select city first...');
            resetCheckoutSelect(subDistrictSelect, 'Select district first...');
            clearCheckoutPostalCode();
            selectedDestination = null;
        }
    });
    
    // District change handler
    districtSelect.addEventListener('change', function() {
        const districtId = this.value;
        const districtName = this.options[this.selectedIndex].text;
        
        console.log('🔄 District changed:', districtId, districtName);
        
        if (districtId) {
            const districtNameField = document.getElementById('checkout_district_name');
            if (districtNameField) districtNameField.value = districtName;
            
            loadCheckoutSubDistricts(districtId);
            resetCheckoutSelect(subDistrictSelect, 'Loading sub-districts...');
            clearCheckoutPostalCode();
            selectedDestination = null; // Clear when district changes
        } else {
            resetCheckoutSelect(subDistrictSelect, 'Select district first...');
            clearCheckoutPostalCode();
            selectedDestination = null;
        }
    });
    
    // CRITICAL FIX: Sub-district change handler dengan selectedDestination setup
    subDistrictSelect.addEventListener('change', function() {
        const subDistrictId = this.value;
        const subDistrictName = this.options[this.selectedIndex].text;
        const zipCode = this.options[this.selectedIndex].getAttribute('data-zip');
        
        console.log('🔄 Sub-district changed:', subDistrictId, subDistrictName, zipCode);
        
        if (subDistrictId) {
            // Set field dengan nama yang benar sesuai controller
            const subDistNameField = document.getElementById('checkout_subdistrict_name');
            if (subDistNameField) subDistNameField.value = subDistrictName;
            
            // Set destination_id for shipping calculation
            const destIdField = document.getElementById('destination_id');
            if (destIdField) destIdField.value = subDistrictId;
            
            // Create destination label
            const provinceName = document.getElementById('checkout_province_name')?.value || '';
            const cityName = document.getElementById('checkout_city_name')?.value || '';
            const districtName = document.getElementById('checkout_district_name')?.value || '';
            
            const destinationLabel = `${subDistrictName}, ${districtName}, ${cityName}, ${provinceName}`;
            const destLabelField = document.getElementById('destination_label');
            if (destLabelField) destLabelField.value = destinationLabel;
            
            // Update postal code
            if (zipCode && zipCode !== '0') {
                const postalDisplay = document.getElementById('checkout_postal_code_display');
                const postalHidden = document.getElementById('checkout_postal_code');
                if (postalDisplay) postalDisplay.value = zipCode;
                if (postalHidden) postalHidden.value = zipCode;
            }
            
            // CRITICAL FIX: Set selectedDestination dengan data lengkap
            setSelectedDestination(subDistrictId, subDistrictName, zipCode);
            
            console.log('✅ Location selected:', {
                destination_id: subDistrictId,
                destination_label: destinationLabel,
                postal_code: zipCode,
                subdistrict_name: subDistrictName,
                selectedDestination: selectedDestination
            });
            
            // TRIGGER SHIPPING CALCULATION if we're on step 3 (shipping step)
            setTimeout(() => {
                const currentStepElement = document.querySelector('.checkout-section.active');
                const isShippingStep = currentStepElement && currentStepElement.id === 'section-3';
                
                if (isShippingStep) {
                    console.log('🚚 Auto-triggering shipping calculation (step 3)...');
                    triggerShippingCalculation();
                } else {
                    console.log('ℹ️ Not on shipping step, shipping calculation ready');
                }
            }, 500);
            
        } else {
            clearCheckoutDestination();
        }
    });
}
function resetNewAddressForm() {
  // kosongkan input teks/hidden (TERMASUK street_address)
  ['recipient_name','phone_recipient','street_address','checkout_postal_code',
   'checkout_province_name','checkout_city_name','checkout_district_name','checkout_subdistrict_name',
   'destination_id','destination_label'
  ].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = '';
  });

  // kosongkan display postal
  const postalDisplay = document.getElementById('checkout_postal_code_display');
  if (postalDisplay) postalDisplay.value = '';

  // reset radio label ke "Rumah"
  const rumah = document.querySelector('input[name="address_label"][value="Rumah"]');
  if (rumah) rumah.checked = true;

  // reset select berantai
  const province = document.getElementById('checkout_province_id');
  const city     = document.getElementById('checkout_city_id');
  const district = document.getElementById('checkout_district_id');
  const subdist  = document.getElementById('checkout_sub_district_id');
  if (province) province.value = '';
  resetCheckoutSelect(city, 'Select province first...');
  resetCheckoutSelect(district, 'Select city first...');
  resetCheckoutSelect(subdist, 'Select district first...');

  // hapus selectedDestination & shipping
  window.selectedDestination = null;
  const shipMethod = document.getElementById('shipping_method');
  const shipCost   = document.getElementById('shipping_cost');
  if (shipMethod) shipMethod.value = '';
  if (shipCost)   shipCost.value   = 0;

  // refresh tampilan biaya kirim
  const shipDisp = document.querySelector('[data-shipping-display]') || document.getElementById('shipping-cost-display');
  if (shipDisp) shipDisp.textContent = 'To be calculated';

  // bersihkan state error/validasi yg mungkin nempel di street_address
  const street = document.getElementById('street_address');
  if (street) street.classList.remove('is-invalid','error');
}


function setupAddressSelectionToggle() {
    const useSavedBtn = document.getElementById('use-saved-address-btn');
    const useNewBtn = document.getElementById('use-new-address-btn');
    const savedSection = document.getElementById('saved-addresses-section');
    const newSection = document.getElementById('new-address-section');
    
    if (!useSavedBtn || !useNewBtn) {
        console.log('ℹ️ Address toggle buttons not found, skipping setup');
        return;
    }
    
    useSavedBtn.addEventListener('click', function() {
        // Toggle button states
        useSavedBtn.classList.add('bg-orange-500', 'text-white');
        useSavedBtn.classList.remove('border-orange-500', 'text-orange-500');
        useNewBtn.classList.remove('bg-orange-500', 'text-white');
        useNewBtn.classList.add('border-orange-500', 'text-orange-500');
        
        // Toggle sections
        if (savedSection) savedSection.classList.remove('hidden');
        if (newSection) newSection.classList.add('hidden');
    });
    
    useNewBtn.addEventListener('click', function() {
        // Toggle button states
        useNewBtn.classList.add('bg-orange-500', 'text-white');
        useNewBtn.classList.remove('border-orange-500', 'text-orange-500');
        useSavedBtn.classList.remove('bg-orange-500', 'text-white');
        useSavedBtn.classList.add('border-orange-500', 'text-orange-500');
        
        // Toggle sections
        if (newSection) newSection.classList.remove('hidden');
        if (savedSection) savedSection.classList.add('hidden');
        
        // Clear saved address selection
        checkoutSelectedAddress = null;
        resetNewAddressForm();
        clearCheckoutDestination();
    });
}

async function loadSavedAddresses() {
    try {
        const response = await fetch('/api/addresses/all', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (response.ok) {
            const result = await response.json();
            
            if (result.success && result.addresses) {
                displaySavedAddresses(result.addresses);
            }
        }
    } catch (error) {
        console.error('Error loading saved addresses:', error);
    }
}

function displaySavedAddresses(addresses) {
    const container = document.getElementById('saved-addresses-list');
    
    if (!container) {
        console.log('ℹ️ Saved addresses container not found');
        return;
    }
    
    if (addresses.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No saved addresses found</p>';
        return;
    }
    
    container.innerHTML = addresses.map(address => `
        <div class="border rounded-lg p-4 cursor-pointer hover:border-orange-500 hover:bg-orange-50 transition-colors" 
             onclick="selectSavedAddress(${JSON.stringify(address).replace(/"/g, '&quot;')})">
            <div class="flex justify-between items-start">
                <div>
                    <div class="font-medium text-gray-900">${address.label}</div>
                    <div class="text-sm text-gray-600">${address.recipient_name} - ${address.phone_recipient}</div>
                    <div class="text-sm text-gray-500 mt-1">${address.full_address || address.location_string}</div>
                    ${address.is_primary ? '<span class="inline-block bg-orange-100 text-orange-800 text-xs px-2 py-1 rounded-full mt-2">Primary</span>' : ''}
                </div>
            </div>
        </div>
    `).join('');
}

function selectSavedAddress(address) {
    console.log('📍 Selected saved address:', address);
    
    checkoutSelectedAddress = address;
    
    // Fill form with selected address data
    const recipientName = document.getElementById('recipient_name');
    const phoneRecipient = document.getElementById('phone_recipient');
    const streetAddress = document.getElementById('street_address');
    const destId = document.getElementById('destination_id');
    const destLabel = document.getElementById('destination_label');
    
    if (recipientName) recipientName.value = address.recipient_name || '';
    if (phoneRecipient) phoneRecipient.value = address.phone_recipient || '';
    if (streetAddress) streetAddress.value = address.street_address || '';
    if (destId) destId.value = address.destination_id || '';
    if (destLabel) destLabel.value = address.location_string || address.full_address || '';
    
    // CRITICAL FIX: Set selectedDestination untuk saved address
    if (address.destination_id) {
        selectedDestination = {
            destination_id: address.destination_id,
            id: address.destination_id,
            label: address.location_string || address.full_address,
            subdistrict_name: address.subdistrict_name || '',
            city_name: address.city_name || '',
            province_name: address.province_name || '',
            postal_code: address.postal_code || ''
        };
        console.log('✅ selectedDestination set from saved address:', selectedDestination);
    }
    
    // Set address label
    const labelInput = document.querySelector(`input[name="address_label"][value="${address.label}"]`);
    if (labelInput) {
        labelInput.checked = true;
    }
    
    // Visual feedback
    const addressElements = document.querySelectorAll('#saved-addresses-list > div');
    addressElements.forEach(el => {
        el.classList.remove('border-orange-500', 'bg-orange-50');
        el.classList.add('border-gray-300');
    });
    
    // Highlight selected
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('border-orange-500', 'bg-orange-50');
        event.currentTarget.classList.remove('border-gray-300');
    }
}

function validateDeliveryInformation() {
    const errors = [];
    
    // Check if using saved address or new address
    const savedSection = document.getElementById('saved-addresses-section');
    const newSection = document.getElementById('new-address-section');
    
    if (savedSection && !savedSection.classList.contains('hidden')) {
        // Validate saved address selection
        if (!checkoutSelectedAddress || !document.getElementById('destination_id').value) {
            errors.push('Please select a saved address');
        }
    } else {
        // Validate new address form
        const requiredFields = [
            { id: 'recipient_name', name: 'Recipient Name' },
            { id: 'phone_recipient', name: 'Phone Number' },
            { id: 'checkout_province_id', name: 'Province' },
            { id: 'checkout_city_id', name: 'City' },
            { id: 'checkout_district_id', name: 'District' },
            { id: 'checkout_sub_district_id', name: 'Sub District' },
            { id: 'street_address', name: 'Street Address' }
        ];
        
        requiredFields.forEach(field => {
            const element = document.getElementById(field.id);
            if (!element || !element.value.trim()) {
                errors.push(`${field.name} is required`);
            }
        });
        
        // Validate destination_id is set (for shipping calculation)
        const destId = document.getElementById('destination_id');
        if (!destId || !destId.value) {
            errors.push('Please complete the location selection');
        }
        
        // Validate subdistrict_name field
        const subdistrictField = document.getElementById('checkout_subdistrict_name');
        if (!subdistrictField || !subdistrictField.value) {
            errors.push('Please select a sub-district');
        }
    }
    
    return errors;
}

// CRITICAL FIX: Override nextStep function untuk ensure shipping calculation
function nextStep(step) {
    if (typeof validateCurrentStep === 'function' && !validateCurrentStep()) {
        return;
    }
    
    if (typeof showStep === 'function') {
        showStep(step);
    }

    // Auto-calculate shipping when reaching step 3
    if (step === 3) {
        setTimeout(() => {
            const destId = document.getElementById('destination_id')?.value;
            console.log('🔄 Moving to step 3, destination_id:', destId);
            
            if (destId) {
                if (!selectedDestination) {
                    console.log('🔧 No selectedDestination, reconstructing...');
                    triggerShippingCalculation();
                } else {
                    console.log('✅ selectedDestination exists, calculating shipping...');
                    if (typeof calculateShipping === 'function') {
                        calculateShipping();
                    }
                }
            } else {
                console.log('⚠️ No destination_id found for shipping calculation');
            }
        }, 500);
    }
}

// GLOBAL FIX FUNCTION untuk debugging
window.fixShippingNow = function() {
    const destId = document.getElementById('destination_id')?.value;
    const subDistrictSelect = document.getElementById('checkout_sub_district_id');
    
    console.log('🔍 Current destination_id:', destId);
    console.log('🔍 Sub-district value:', subDistrictSelect?.value);
    console.log('🔍 Selected destination:', selectedDestination);
    
    if (!destId && subDistrictSelect?.value) {
        document.getElementById('destination_id').value = subDistrictSelect.value;
        console.log('✅ Set destination_id to:', subDistrictSelect.value);
    }
    
    // Always reconstruct selectedDestination
    return triggerShippingCalculation();
};

// Main DOMContentLoaded event listener
document.addEventListener('DOMContentLoaded', function() {
    console.log('📊 Order Summary DOM elements loaded - NO TAX VERSION + VOUCHER SUPPORT');
    
    // Get initial values from server-side data
    const initialSubtotal = {{ $subtotal ?? 0 }};
    const initialWeight = {{ $totalWeight ?? 1000 }};
    const cartItemsCount = {{ isset($cartItems) ? $cartItems->count() : 0 }};
    const initialDiscount = {{ $discountAmount ?? 0 }};
    
    console.log('📊 Initial Order Summary data (NO TAX + VOUCHER):', {
        subtotal: initialSubtotal,
        weight: initialWeight,
        itemsCount: cartItemsCount,
        discount: initialDiscount,
        taxRemoved: true,
        voucherSupport: true
    });
    
    // Store in meta tags for JavaScript access
    if (!document.querySelector('meta[name="cart-subtotal"]')) {
        const subtotalMeta = document.createElement('meta');
        subtotalMeta.name = 'cart-subtotal';
        subtotalMeta.content = initialSubtotal;
        document.head.appendChild(subtotalMeta);
    }
    
    if (!document.querySelector('meta[name="total-weight"]')) {
        const weightMeta = document.createElement('meta');
        weightMeta.name = 'total-weight';
        weightMeta.content = initialWeight;
        document.head.appendChild(weightMeta);
    }
    
    // Add discount meta tag for voucher support
    if (!document.querySelector('meta[name="discount-amount"]')) {
        const discountMeta = document.createElement('meta');
        discountMeta.name = 'discount-amount';
        discountMeta.content = initialDiscount;
        document.head.appendChild(discountMeta);
    }
    
    // Validate that totals are showing correctly
    const subtotalElements = document.querySelectorAll('[data-subtotal-display]');
    const totalElements = document.querySelectorAll('[data-total-display]');
    const discountElements = document.querySelectorAll('[data-discount-display]');
    
    if (subtotalElements.length === 0) {
        console.warn('⚠️ No subtotal display elements found');
    }
    
    if (totalElements.length === 0) {
        console.warn('⚠️ No total display elements found');
    }
    
    console.log('🎫 Discount elements found:', discountElements.length);
    
    // Check if cart items are displayed
    const orderSummaryItems = document.querySelectorAll('.order-summary-item');
    console.log('📦 Order Summary items found:', orderSummaryItems.length);
    
    if (orderSummaryItems.length !== cartItemsCount) {
        console.warn('⚠️ Mismatch between cart items count and displayed items:', {
            expected: cartItemsCount,
            displayed: orderSummaryItems.length
        });
    }
    
    // CRITICAL: Log that tax has been completely removed and voucher support added
    console.log('✅ TAX COMPLETELY REMOVED from checkout system');
    console.log('✅ VOUCHER/COUPON SUPPORT ADDED to checkout system');

    // Initialize checkout location system
    console.log('🚀 Initializing checkout location system...');
    
    // Add a small delay to ensure all DOM elements are ready
    setTimeout(() => {
        initializeCheckoutLocation();
        setupAddressSelectionToggle();
        loadSavedAddresses();
        console.log('✅ Checkout hierarchical location system loaded');
        
        // Check if there's already a destination_id and trigger shipping if needed
        setTimeout(() => {
            const destId = document.getElementById('destination_id')?.value;
            if (destId && !selectedDestination) {
                console.log('🔧 Found existing destination_id, reconstructing selectedDestination...');
                triggerShippingCalculation();
            }
        }, 1000);
    }, 100);
});

// Make functions globally available for debugging
window.loadCheckoutProvinces = loadCheckoutProvinces;
window.loadCheckoutCities = loadCheckoutCities;
window.loadCheckoutDistricts = loadCheckoutDistricts;
window.loadCheckoutSubDistricts = loadCheckoutSubDistricts;
window.checkoutLocationData = checkoutLocationData;
window.triggerShippingCalculation = triggerShippingCalculation;
window.setSelectedDestination = setSelectedDestination;

console.log('🚀 Checkout location functions defined globally with shipping fix');


</script>

<!-- Load the NO TAX JavaScript file -->
<script src="{{ asset('js/enhanced-checkout.js') }}?v={{ time() }}"></script>

<script src="{{ asset('js/voucher-checkout.js') }}"></script>

<script src="{{ asset('js/points-checkout.js') }}"></script>
@if(Auth::check())
    <meta name="user-points-balance" content="{{ Auth::user()->points_balance ?? 0 }}">
    @if(($appliedPoints ?? false) && ($pointsUsed ?? 0) > 0)
        <meta name="applied-points-used" content="{{ $pointsUsed }}">
        <meta name="applied-points-discount" content="{{ $pointsDiscount }}">
    @endif
@endif
@endsection