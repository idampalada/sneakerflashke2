<?php

use App\Http\Controllers\Frontend\HomeController;
use App\Http\Controllers\Frontend\ProductController;
use App\Http\Controllers\Frontend\CategoryController;
use App\Http\Controllers\Frontend\CartController;
use App\Http\Controllers\Frontend\WishlistController;
use App\Http\Controllers\Frontend\CheckoutController;
use App\Http\Controllers\Frontend\OrderController;
use App\Http\Controllers\Frontend\ProfileController;
use App\Http\Controllers\Frontend\AddressController;
use App\Http\Controllers\Frontend\GineeSyncController;
use App\Http\Controllers\Frontend\GineeWebhookController;
use App\Http\Controllers\Frontend\BlackFridayController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
/*
|--------------------------------------------------------------------------
| Web Routes - CLEANED & FIXED
|--------------------------------------------------------------------------
*/

// Homepage
Route::get('/', [HomeController::class, 'index'])->name('home');

// =====================================
// WEBHOOK ROUTES - TANPA CSRF PROTECTION
// =====================================
Route::withoutMiddleware(['web'])->group(function () {
    // PRIMARY WEBHOOK - Use this URL in Midtrans Dashboard
    Route::post('/checkout/payment-notification', [CheckoutController::class, 'paymentNotification'])
          ->name('checkout.payment-notification');

    // ALTERNATIVE WEBHOOK ENDPOINTS
    Route::post('/checkout/payment/notification', [CheckoutController::class, 'paymentNotification'])
          ->name('checkout.payment.notification');
    Route::post('/midtrans/notification', [CheckoutController::class, 'paymentNotification'])
          ->name('midtrans.notification');
    Route::post('/webhook/midtrans', [CheckoutController::class, 'paymentNotification'])
          ->name('webhook.midtrans');
    Route::post('/api/midtrans/webhook', [CheckoutController::class, 'paymentNotification'])
          ->name('api.midtrans.webhook');

    // TEST WEBHOOK ENDPOINTS (for debugging)
    Route::post('/api/payment/test-webhook', [CheckoutController::class, 'testWebhook'])
          ->name('api.payment.test-webhook');
    Route::post('/test-webhook-no-validation', [CheckoutController::class, 'testWebhookNoValidation'])
          ->name('test.webhook.no.validation');
    
    // SIMPLE TEST WEBHOOK
    Route::post('/test-webhook-simple', function(Request $request) {
        Log::info('=== SIMPLE WEBHOOK TEST ===', [
            'all_data' => $request->all(),
            'headers' => $request->headers->all(),
            'raw_body' => $request->getContent(),
            'timestamp' => now()
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Simple webhook test received',
            'received_data' => $request->all(),
            'order_id' => $request->get('order_id'),
            'timestamp' => now()
        ]);
    })->name('test.webhook.simple');

    // DEBUG WEBHOOK
    Route::any('/debug-webhook', function(Request $request) {
        Log::info('=== DEBUG WEBHOOK ===', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $request->headers->all(),
            'body' => $request->getContent(),
            'input' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Debug webhook received',
            'method' => $request->method(),
            'timestamp' => now(),
            'data_received' => $request->all()
        ]);
    })->name('debug.webhook');
    
    // GET INFO ENDPOINT (untuk cek di browser)
    Route::get('/checkout/payment-notification', function() {
        return response()->json([
            'status' => 'info',
            'message' => 'Webhook endpoint is working',
            'note' => 'This endpoint only accepts POST requests from Midtrans',
            'methods_allowed' => ['POST'],
            'current_time' => now(),
            'environment' => app()->environment()
        ]);
    })->name('checkout.payment-notification.info');

// Ginee Webhooks
Route::prefix('api/webhooks/ginee')->group(function () {
    Route::get('/health', fn () => response()->json(['ok'=>true,'ts'=>now()]));
    Route::post('/orders', [GineeWebhookController::class, 'orders'])->name('webhooks.ginee.orders');
    Route::post('/master-products', [GineeWebhookController::class, 'masterProducts'])->name('webhooks.ginee.master_products');
    Route::match(['GET','POST'], '/global', [GineeWebhookController::class, 'global'])->name('webhooks.ginee.global');
});

// Subdomain webhook route - HARUS DI LUAR withoutMiddleware group
Route::domain('webhook.meltedcloud.cloud')->group(function () {
    Route::post('/webhook', [GineeWebhookController::class, 'global'])
          ->name('webhook.ginee.subdomain')
          ->withoutMiddleware(['web', 'csrf']);
});
});

// =====================================
// PRODUCT ROUTES
// =====================================
Route::prefix('products')->name('products.')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::get('/variant/{productId}/{size?}', [ProductController::class, 'getVariant'])->name('get-variant');
    Route::get('/{slug}', [ProductController::class, 'show'])->name('show');
});

// Product category routes
Route::get('/mens', [ProductController::class, 'mens'])->name('products.mens');
Route::get('/womens', [ProductController::class, 'womens'])->name('products.womens');
Route::get('/unisex', [ProductController::class, 'unisex'])->name('products.unisex');
Route::get('/brand', [ProductController::class, 'brand'])->name('products.brand');
Route::get('/accessories', [ProductController::class, 'accessories'])->name('products.accessories');
Route::get('/sale', [ProductController::class, 'sale'])->name('products.sale');
Route::get('/search', [ProductController::class, 'search'])->name('search');
Route::get('/filter', [ProductController::class, 'filter'])->name('products.filter');

// Categories
Route::get('/categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');

// =====================================
// SHOPPING CART
// =====================================
Route::prefix('cart')->name('cart.')->group(function () {
    Route::get('/', [CartController::class, 'index'])->name('index');
    Route::post('/add/{productId?}', [CartController::class, 'add'])->name('add');
    Route::patch('/update/{identifier}', [CartController::class, 'update'])->name('update');
    Route::delete('/remove/{identifier}', [CartController::class, 'remove'])->name('remove');
    Route::delete('/clear', [CartController::class, 'clear'])->name('clear');
    Route::get('/count', [CartController::class, 'getCartCount'])->name('count');
    Route::get('/data', [CartController::class, 'getCartData'])->name('data');
    Route::post('/sync', [CartController::class, 'syncCart'])->name('sync');
});

// =====================================
// AUTHENTICATION ROUTES
// =====================================
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->name('register.submit');
    Route::get('/auth/google', [GoogleController::class, 'redirectToGoogle'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'handleGoogleCallback'])->name('auth.google.callback');
    //     Route::get('/auth/apple', [AppleController::class, 'redirectToApple'])->name('auth.apple');
    // Route::get('/auth/apple/callback', [AppleController::class, 'handleAppleCallback'])->name('auth.apple.callback');
});

Route::post('/logout', [GoogleController::class, 'logout'])->name('logout')->middleware('auth');
// =====================================
// PASSWORD RESET ROUTES
// =====================================
Route::middleware('guest')->group(function () {
    Route::get('/password/reset', [PasswordResetController::class, 'showResetForm'])->name('password.request');
    Route::post('/password/send', [PasswordResetController::class, 'sendResetEmail'])->name('password.send')->middleware(['throttle:5,1']);
    Route::get('/password/reset/{token}', [PasswordResetController::class, 'showResetPasswordForm'])->name('password.reset.form');
    Route::post('/password/update', [PasswordResetController::class, 'resetPassword'])->name('password.update');
});
// =====================================
// CHECKOUT & PAYMENT ROUTES - ALL PAYMENT METHODS HERE
// =====================================
Route::prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/', [CheckoutController::class, 'index'])->name('index');
    Route::post('/', [CheckoutController::class, 'store'])->name('store');
    
    // RajaOngkir integration
    Route::get('/search-destinations', [CheckoutController::class, 'searchDestinations'])->name('search-destinations');
    Route::post('/calculate-shipping', [CheckoutController::class, 'calculateShipping'])->name('calculate-shipping');
    
    // Payment flow - ALL PAYMENT METHODS
    Route::get('/payment/{orderNumber}', [CheckoutController::class, 'payment'])->name('payment');
    Route::get('/success/{orderNumber}', [CheckoutController::class, 'success'])->name('success');
    
    // RETRY PAYMENT - MOVED HERE FROM API SECTION
    Route::post('/retry-payment/{orderNumber}', [CheckoutController::class, 'retryPayment'])->name('retry-payment');
    
    // Midtrans redirect callbacks
    Route::get('/payment-success', [CheckoutController::class, 'paymentSuccess'])->name('payment-success');
    Route::get('/payment-pending', [CheckoutController::class, 'paymentPending'])->name('payment-pending');
    Route::get('/payment-error', [CheckoutController::class, 'paymentError'])->name('payment-error');
    Route::get('/finish', [CheckoutController::class, 'paymentFinish'])->name('finish');
    Route::get('/unfinish', [CheckoutController::class, 'paymentUnfinish'])->name('unfinish');
    Route::get('/error', [CheckoutController::class, 'paymentError'])->name('error');
});

// =====================================
// AUTHENTICATED USER ROUTES
// =====================================
Route::middleware(['auth', 'verified'])->group(function () {
    // Orders - NO PAYMENT METHODS, ONLY ORDER MANAGEMENT
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{orderNumber}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{orderNumber}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::get('/orders/{orderNumber}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
    Route::get('/orders/{orderNumber}/print', [OrderController::class, 'print'])->name('orders.print');
    
    // Wishlist
    Route::prefix('wishlist')->name('wishlist.')->group(function () {
        Route::get('/', [WishlistController::class, 'index'])->name('index');
        Route::post('/toggle/{productId}', [WishlistController::class, 'toggle'])->name('toggle');
        Route::delete('/remove/{productId}', [WishlistController::class, 'remove'])->name('remove');
        Route::delete('/clear', [WishlistController::class, 'clear'])->name('clear');
        Route::post('/move-to-cart/{productId}', [WishlistController::class, 'moveToCart'])->name('moveToCart');
        Route::get('/count', [WishlistController::class, 'getCount'])->name('count');
        Route::post('/check', [WishlistController::class, 'checkProducts'])->name('check');
    });
    
    // Profile Management
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'index'])->name('index');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::get('/password', [ProfileController::class, 'showChangePassword'])->name('password.change');
        Route::put('/password', [ProfileController::class, 'updatePassword'])->name('password.update');
        Route::get('/data', [ProfileController::class, 'getProfileData'])->name('data');

        // Address Management
        Route::prefix('addresses')->name('addresses.')->group(function () {
            Route::get('/', [AddressController::class, 'index'])->name('index');
            Route::get('/create', [AddressController::class, 'create'])->name('create');
            Route::post('/store', [AddressController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [AddressController::class, 'edit'])->name('edit');
            Route::put('/{id}', [AddressController::class, 'update'])->name('update');
            Route::post('/{id}/set-primary', [AddressController::class, 'setPrimary'])->name('set-primary');
            Route::delete('/{id}', [AddressController::class, 'destroy'])->name('destroy');
            Route::get('/{id}/show', [AddressController::class, 'show'])->name('show');
            Route::get('/api/all', [AddressController::class, 'getAddresses'])->name('api.all');
            Route::get('/api/primary', [AddressController::class, 'getPrimaryAddress'])->name('api.primary');
        });
    });

    // Points management
    Route::prefix('api/points')->name('api.points.')->group(function() {
        Route::post('/validate', [CheckoutController::class, 'validatePoints'])->name('validate');
        Route::post('/apply', [CheckoutController::class, 'validatePoints'])->name('apply');
        Route::post('/remove', [CheckoutController::class, 'removePoints'])->name('remove');
        Route::get('/current', [CheckoutController::class, 'getCurrentPoints'])->name('current');
        Route::get('/balance', function() {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }
            
            return response()->json([
                'success' => true,
                'balance' => $user->points_balance ?? 0,
                'formatted_balance' => number_format($user->points_balance ?? 0, 0, ',', '.')
            ]);
        })->name('balance');
    });

    // Ginee Integration
    Route::prefix('integrations/ginee')->name('ginee.')->group(function () {
        Route::post('/pull-products', [GineeSyncController::class, 'pullProducts'])->name('pull.products');
        Route::post('/push-stock', [GineeSyncController::class, 'pushStock'])->name('push.stock');
        Route::get('/ginee-stock', [GineeSyncController::class, 'getGineeStock'])->name('ginee.stock');
        Route::get('/test-endpoints', [GineeSyncController::class, 'testEndpoints'])->name('test.endpoints');
    });
});


// =====================================
// API ROUTES - CONSOLIDATED
// =====================================
Route::prefix('api')->name('api.')->group(function() {
    // Products
    Route::get('/products/search', [ProductController::class, 'quickSearch'])->name('products.search');
    Route::get('/products/{id}/variants', [ProductController::class, 'getVariants'])->name('products.variants');
    Route::get('/products/{id}/stock', [ProductController::class, 'checkStock'])->name('products.stock');
    
    // Cart
    Route::get('/cart/count', [CartController::class, 'getCartCount'])->name('cart.count');
    Route::get('/cart/data', [CartController::class, 'getCartData'])->name('cart.data');
    
    // Checkout & RajaOngkir
    Route::get('/checkout/search-destinations', [CheckoutController::class, 'searchDestinations'])->name('checkout.search-destinations');
    Route::post('/checkout/shipping', [CheckoutController::class, 'calculateShipping'])->name('checkout.shipping');
    
    Route::prefix('addresses')->name('addresses.hierarchical.')->group(function() {
    Route::get('/provinces', [AddressController::class, 'getProvinces'])->name('provinces');
    Route::get('/cities/{provinceId}', [AddressController::class, 'getCitiesByProvince'])->name('cities');
    Route::get('/districts/{cityId}', [AddressController::class, 'getDistrictsByCity'])->name('districts');
    Route::get('/sub-districts/{districtId}', [AddressController::class, 'getSubDistrictsByDistrict'])->name('sub-districts');
    Route::get('/test-endpoints', [AddressController::class, 'testEndpoints'])->name('test-endpoints');
});
    // Payment status
    Route::get('/payment/status/{orderNumber}', [CheckoutController::class, 'getPaymentStatus'])->name('payment.status');
    Route::get('/payment/check/{orderNumber}', [CheckoutController::class, 'checkPaymentStatus'])->name('payment.check');
    
    // ❌ REMOVED: Route::post('/payment/retry/{orderNumber}', [OrderController::class, 'retryPayment'])->name('payment.retry');
    // ✅ MOVED TO: /checkout/retry-payment/{orderNumber} above
    
    // Authenticated API routes
    Route::middleware('auth')->group(function() {
        // Wishlist
        Route::get('/wishlist/count', [WishlistController::class, 'getCount'])->name('wishlist.count');
        Route::post('/wishlist/toggle/{productId}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
        Route::post('/wishlist/check', [WishlistController::class, 'checkProducts'])->name('wishlist.check');
        
        // Profile API routes
        Route::get('/profile/data', [ProfileController::class, 'getProfileData'])->name('profile.data');
        Route::get('/zodiac/{zodiac}', [ProfileController::class, 'getZodiacData'])->name('zodiac.data');
        
        // Address API routes
        Route::prefix('addresses')->name('addresses.')->group(function() {
            Route::get('/all', [AddressController::class, 'getAddresses'])->name('all');
            Route::get('/primary', [AddressController::class, 'getPrimaryAddress'])->name('primary');
            Route::get('/{id}', [AddressController::class, 'show'])->name('show');
            Route::post('/{id}/set-primary', [AddressController::class, 'setPrimary'])->name('set-primary');
            Route::delete('/{id}', [AddressController::class, 'destroy'])->name('destroy');
        });

        // Vouchers
        Route::prefix('vouchers')->name('vouchers.')->group(function() {
            Route::post('/apply', [\App\Http\Controllers\Frontend\VoucherController::class, 'apply'])->name('apply');
            Route::post('/remove', [\App\Http\Controllers\Frontend\VoucherController::class, 'remove'])->name('remove');
            Route::get('/current', [\App\Http\Controllers\Frontend\VoucherController::class, 'current'])->name('current');
            Route::post('/validate', [\App\Http\Controllers\Frontend\VoucherController::class, 'validate'])->name('validate');
            Route::get('/available', [\App\Http\Controllers\Frontend\VoucherController::class, 'available'])->name('available');
        });
    });
    
    // Newsletter
    Route::post('/newsletter', function() {
        return response()->json(['success' => true, 'message' => 'Subscribed successfully!']);
    })->name('newsletter');
});

// =====================================
// STATIC PAGES
// =====================================
Route::get('/about', function() { return view('frontend.pages.about'); })->name('about');
Route::get('/contact', function() { return view('frontend.pages.contact'); })->name('contact');
Route::post('/contact', function() { return back()->with('success', 'Message sent successfully!'); })->name('contact.submit');
Route::get('/shipping-info', function() { return view('frontend.pages.shipping'); })->name('shipping.info');
Route::get('/returns', function() { return view('frontend.pages.returns'); })->name('returns');
Route::get('/size-guide', function() { return view('frontend.pages.size-guide'); })->name('size.guide');
Route::get('/terms', function() { return view('frontend.pages.terms'); })->name('terms');
Route::get('/privacy', function() { return view('frontend.pages.privacy'); })->name('privacy');

// =====================================
// DEBUG ROUTES (Only in local/staging)
// =====================================
if (app()->environment(['local', 'staging'])) {
    Route::prefix('debug')->group(function() {
        // Cart debug
        Route::get('/cart', function() {
            $cart = Session::get('cart', []);
            $cartItems = collect();
            
            foreach ($cart as $cartKey => $details) {
                $productId = $details['product_id'] ?? null;
                $product = null;
                
                if ($productId) {
                    $product = \App\Models\Product::find($productId);
                }
                
                $cartItems->push([
                    'cart_key' => $cartKey,
                    'raw_data' => $details,
                    'product_exists' => $product ? true : false,
                    'product_active' => $product ? $product->is_active : false,
                    'current_stock' => $product ? $product->stock_quantity : 0,
                    'size_info' => [
                        'cart_size' => $details['size'] ?? 'not_set',
                        'product_available_sizes' => $product ? $product->available_sizes : null,
                        'size_type' => $details['size'] ? gettype($details['size']) : 'null'
                    ]
                ]);
            }
            
            return response()->json([
                'session_cart_raw' => $cart,
                'processed_items' => $cartItems,
                'total_items' => count($cart),
                'session_id' => session()->getId(),
                'csrf_token' => csrf_token()
            ], 200, [], JSON_PRETTY_PRINT);
        })->name('debug.cart');

        Route::get('/cart/clear', function() {
            Session::flush();
            return response()->json(['message' => 'Session cleared', 'timestamp' => now()]);
        })->name('debug.cart.clear');

        // Shipping debug
        Route::prefix('shipping')->group(function() {
            Route::get('/test', function() {
                $apiKey = env('RAJAONGKIR_API_KEY');
                $baseUrl = env('RAJAONGKIR_BASE_URL', 'https://rajaongkir.komerce.id/api/v1');
                
                return response()->json([
                    'environment' => app()->environment(),
                    'api_configured' => !empty($apiKey),
                    'api_key_preview' => $apiKey ? substr($apiKey, 0, 8) . '...' : 'NOT SET',
                    'base_url' => $baseUrl,
                    'origin_id' => env('STORE_ORIGIN_CITY_ID'),
                    'origin_name' => env('STORE_ORIGIN_CITY_NAME'),
                    'timestamp' => now()->toISOString()
                ]);
            })->name('debug.shipping.test');
            
            Route::post('/direct-test', function(Request $request) {
                $apiKey = env('RAJAONGKIR_API_KEY');
                $baseUrl = env('RAJAONGKIR_BASE_URL', 'https://rajaongkir.komerce.id/api/v1');
                $originId = env('STORE_ORIGIN_CITY_ID', 17549);
                
                $destinationId = $request->input('destination_id', '66274');
                $weight = $request->input('weight', 1000);
                
                try {
                    Log::info('🧪 Debug direct shipping test', [
                        'destination_id' => $destinationId,
                        'weight' => $weight,
                        'origin_id' => $originId
                    ]);
                    
                    $startTime = microtime(true);
                    
                    $response = Http::asForm()
                        ->withHeaders([
                            'accept' => 'application/json',
                            'key' => $apiKey
                        ])
                        ->timeout(30)
                        ->post($baseUrl . '/calculate/domestic-cost', [
                            'origin' => $originId,
                            'destination' => $destinationId,
                            'weight' => $weight,
                            'courier' => 'jne'
                        ]);
                    
                    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
                    
                    return response()->json([
                        'success' => $response->successful(),
                        'status_code' => $response->status(),
                        'execution_time_ms' => $executionTime,
                        'response_data' => $response->successful() ? $response->json() : null,
                        'error_response' => !$response->successful() ? $response->body() : null,
                        'request_data' => [
                            'origin' => $originId,
                            'destination' => $destinationId,
                            'weight' => $weight,
                            'courier' => 'jne'
                        ],
                        'config' => [
                            'api_url' => $baseUrl . '/calculate/domestic-cost',
                            'api_key_set' => !empty($apiKey)
                        ]
                    ]);
                    
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'error' => $e->getMessage(),
                        'error_type' => get_class($e)
                    ], 500);
                }
            })->name('debug.shipping.direct-test');
        });
    });
}

// =====================================
// REDIRECTS & FALLBACKS
// =====================================
Route::get('/shop', function() { return redirect()->route('products.index'); });
Route::get('/category/{slug}', function($slug) { return redirect()->route('categories.show', $slug); });
Route::get('/product/{slug}', function($slug) { return redirect()->route('products.show', $slug); });

Route::fallback(function() {
    abort(404);
});

// =====================================
// EMAIL VERIFICATION ROUTES  
// =====================================
Route::middleware(['auth'])->group(function () {
    // Halaman notifikasi email verification
    Route::get('/email/verify', [VerificationController::class, 'notice'])
        ->name('verification.notice');

    // Handle verification link yang diklik dari email (simple version)
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
        ->middleware(['signed'])->name('verification.verify');

    // Resend verification email (untuk user yang sudah login)
    Route::post('/email/verification-notification', [VerificationController::class, 'resend'])
        ->middleware(['throttle:6,1'])->name('verification.send');
});

// Route untuk resend verification dari halaman login (tanpa auth)
Route::post('/email/resend-verification', [VerificationController::class, 'resend'])
    ->name('verification.resend')
    ->middleware(['throttle:3,1']);
    // Enhanced Ginee Routes
Route::group(['prefix' => 'integrations/ginee', 'middleware' => ['auth', 'web']], function() {
    Route::post('/test-single-sku-enhanced', [App\Http\Controllers\Frontend\GineeSyncController::class, 'testSingleSkuEnhanced']);
    Route::post('/sync-single-sku-enhanced', [App\Http\Controllers\Frontend\GineeSyncController::class, 'syncSingleSkuEnhanced']);
    Route::post('/compare-methods', [App\Http\Controllers\Frontend\GineeSyncController::class, 'compareAllMethods']);
});

Route::prefix('promo')->name('promo.')->group(function () {
    Route::get('/onedecade', [App\Http\Controllers\Frontend\PromoController::class, 'showOneDecade'])->name('onedecade');
    Route::get('/onedecade/result', [App\Http\Controllers\Frontend\PromoController::class, 'showOneDecadeResult'])->name('onedecade.result');
    Route::post('/onedecade/verify', [App\Http\Controllers\Frontend\PromoController::class, 'verifyOneDecade'])->name('onedecade.verify');
    
    // Route baru untuk halaman verifikasi
    Route::get('/onedecade/verification', [App\Http\Controllers\Frontend\PromoController::class, 'showOneDecadeVerification'])->name('onedecade.verification');

        Route::get('/onedecade/dataverif', [App\Http\Controllers\Frontend\DataVerifController::class, 'index'])->name('onedecade.dataverif');
    

    // TAMBAHKAN: Route untuk testing spreadsheet connection
    Route::get('/onedecade/test-spreadsheet', [App\Http\Controllers\Frontend\PromoController::class, 'testSpreadsheetConnection'])->name('onedecade.test-spreadsheet');
    Route::get('/onedecade/view-csv', [App\Http\Controllers\Frontend\PromoController::class, 'viewSpreadsheetCsv'])->name('onedecade.view-csv');
});


Route::get('/debug-undian/{code}/{order}/{platform}', function ($code, $order, $platform) {
    $controller = new \App\Http\Controllers\Frontend\PromoController();
    // Perlu membuat method public untuk testing
    return $controller->debugValidateUndian($code, $order, $platform);
});

Route::get('/promo/onedecade/finish', function () {
    return view('frontend.promo.onedecade_finish');
})->name('promo.onedecade.finish');

// // =====================================
// // BLACK FRIDAY ROUTES
// // =====================================

// // Frontend Black Friday Routes
// Route::prefix('black-friday')->name('black-friday.')->group(function () {
//     Route::get('/', [\App\Http\Controllers\Frontend\BlackFridayController::class, 'index'])->name('index');
//     Route::get('/{product:slug}', [\App\Http\Controllers\Frontend\BlackFridayController::class, 'show'])->name('show');
// });

// // API Routes for Black Friday
// Route::prefix('api/black-friday')->name('api.black-friday.')->group(function() {
//     Route::get('/search', [\App\Http\Controllers\Frontend\BlackFridayController::class, 'quickSearch'])->name('search');
//     Route::get('/flash-sale-data', [\App\Http\Controllers\Frontend\BlackFridayController::class, 'getFlashSaleData'])->name('flash-sale-data');
// });

// // Black Friday Cart Integration Routes
// Route::post('/cart/add-black-friday/{product:slug}', [\App\Http\Controllers\Frontend\CartController::class, 'addBlackFridayProduct'])->name('cart.add-black-friday');

// Apple Authentication Routes (placeholder implementation)
Route::get('/auth/apple', function() {
    return redirect()->route('login')->with('info', 'Apple authentication will be available soon.');
})->name('auth.apple');

Route::get('/auth/apple/callback', function() {
    return redirect()->route('login')->with('info', 'Apple authentication callback');
})->name('auth.apple.callback');