<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\UserAddress;
use App\Services\MidtransService;
use App\Services\RajaOngkirService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class CheckoutController extends Controller
{
    private $rajaOngkirApiKey;
    private $rajaOngkirBaseUrl;
    private $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
        
        // RajaOngkir API V2 via Komerce
        $this->rajaOngkirApiKey = config('services.rajaongkir.api_key') ?: env('RAJAONGKIR_API_KEY');
        $this->rajaOngkirBaseUrl = 'https://rajaongkir.komerce.id/api/v1';
        
        Log::info('RajaOngkir V2 Controller initialized with Address Integration + Voucher System', [
            'base_url' => $this->rajaOngkirBaseUrl,
            'api_key_set' => !empty($this->rajaOngkirApiKey),
            'origin_city' => env('STORE_ORIGIN_CITY_NAME', 'Not configured'),
            'origin_city_id' => env('STORE_ORIGIN_CITY_ID', 'Not configured'),
            'midtrans_configured' => !empty(config('services.midtrans.server_key'))
        ]);
    }

    /**
     * Index method with proper cart item handling and voucher info
     */
    public function index()
{
    // Keep existing code...
    $cart = Session::get('cart', []);
    
    if (empty($cart)) {
        return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
    }

    $cartItems = $this->getCartItems($cart);
    $subtotal = $cartItems->sum('subtotal');
    $totalWeight = $this->calculateTotalWeight($cartItems);
    $provinces = $this->getProvinces();
    $majorCities = $this->getMajorCities();

    // User addresses and authentication data (keep existing)...
    $userAddresses = collect();
    $primaryAddress = null;
    $primaryAddressId = null;
    $userHasPrimaryAddress = false;
    $authenticatedUserName = '';
    $authenticatedUserPhone = '';
    $authenticatedUserEmail = '';
    
    if (Auth::check()) {
        $user = Auth::user();
        
        $userAddresses = UserAddress::where('user_id', $user->id)
                            ->where('is_active', true)
                            ->orderBy('is_primary', 'desc')
                            ->orderBy('created_at', 'desc')
                            ->get();
        
        $primaryAddress = UserAddress::where('user_id', $user->id)
                            ->where('is_primary', true)
                            ->where('is_active', true)
                            ->first();
        
        if ($primaryAddress) {
            $primaryAddressId = $primaryAddress->id;
            $userHasPrimaryAddress = true;
        }
        
        $authenticatedUserName = $user->name ?? '';
        $authenticatedUserPhone = $user->phone ?? '';
        $authenticatedUserEmail = $user->email ?? '';
    }

    // VOUCHER SYSTEM (keep existing)
    $appliedVoucher = Session::get('applied_voucher', null);
    $discountAmount = 0;

    if ($appliedVoucher && isset($appliedVoucher['discount_amount'])) {
        $discountAmount = (float) $appliedVoucher['discount_amount'];
    }

    // POINTS SYSTEM - NEW
    $appliedPoints = Session::get('applied_points', null);
    $pointsDiscount = 0;
    $pointsUsed = 0;

    if ($appliedPoints && isset($appliedPoints['discount'])) {
        $pointsDiscount = (float) $appliedPoints['discount'];
        $pointsUsed = (int) $appliedPoints['points_used'];
    }

    Log::info('Checkout initialized with Points Support', [
        'cart_count' => count($cart),
        'subtotal' => $subtotal,
        'discount_amount' => $discountAmount,
        'points_discount' => $pointsDiscount,
        'points_used' => $pointsUsed,
        'total_weight' => $totalWeight,
        'user_authenticated' => Auth::check(),
        'applied_voucher' => $appliedVoucher ? $appliedVoucher['voucher_code'] : null,
        'user_points_balance' => Auth::check() ? (Auth::user()->points_balance ?? 0) : 0
    ]);

    return view('frontend.checkout.index', compact(
        'cartItems', 
        'subtotal', 
        'provinces', 
        'majorCities', 
        'totalWeight',
        'userAddresses',
        'primaryAddress',
        'primaryAddressId',
        'userHasPrimaryAddress',
        'authenticatedUserName',
        'authenticatedUserPhone',
        'authenticatedUserEmail',
        'appliedVoucher',
        'discountAmount',
        'appliedPoints',
        'pointsDiscount',
        'pointsUsed'
    ));
}

/**
 * Validate and apply points - NEW
 */
public function validatePoints(Request $request)
{
    try {
        $request->validate([
            'points_amount' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated'
            ], 401);
        }

        $pointsAmount = $request->points_amount;
        $userBalance = $user->points_balance ?? 0;

        // Validate points availability
        if ($pointsAmount > $userBalance) {
            return response()->json([
                'success' => false,
                'message' => "Poin tidak mencukupi. Tersedia: " . number_format($userBalance, 0, ',', '.') . " poin"
            ]);
        }

        // Calculate discount (1 point = 1 rupiah)
        $discount = $pointsAmount;

        // Store in session
        Session::put('applied_points', [
            'points_used' => $pointsAmount,
            'discount' => $discount,
            'applied_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'points_used' => $pointsAmount,
            'discount_amount' => $discount,
            'formatted_discount' => 'Rp ' . number_format($discount, 0, ',', '.'),
            'remaining_balance' => $userBalance - $pointsAmount,
            'message' => number_format($pointsAmount, 0, ',', '.') . " poin berhasil diterapkan"
        ]);

    } catch (\Exception $e) {
        Log::error('Error validating points', [
            'user_id' => Auth::id(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan sistem'
        ], 500);
    }
}

/**
 * Remove applied points - NEW
 */
public function removePoints(Request $request)
{
    try {
        // Clear points from session
        Session::forget('applied_points');

        return response()->json([
            'success' => true,
            'message' => 'Penggunaan poin dibatalkan'
        ]);

    } catch (\Exception $e) {
        Log::error('Error removing points', [
            'user_id' => Auth::id(),
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Gagal membatalkan penggunaan poin'
        ], 500);
    }
}

/**
 * Get current applied points - NEW
 */
public function getCurrentPoints(Request $request)
{
    try {
        $appliedPoints = Session::get('applied_points');

        if (!$appliedPoints) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada poin yang diterapkan'
            ]);
        }

        return response()->json([
            'success' => true,
            'points' => $appliedPoints
        ]);

    } catch (\Exception $e) {
        Log::error('Error getting current points', [
            'user_id' => Auth::id(),
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan sistem'
        ], 500);
    }
}


    // Keep all existing methods exactly the same until store method
    private function getCartItems($cart)
    {
        $cartItems = collect();
        
        foreach ($cart as $cartKey => $details) {
            $productId = $details['product_id'] ?? $details['id'] ?? null;
            $product = null;
            
            if ($productId) {
                $product = Product::find($productId);
            }
            
            $currentStock = $product ? ($product->stock_quantity ?? 0) : 0;
            
            if (!$product || !$product->is_active) {
                continue;
            }
            
            $itemName = $details['name'] ?? ($product->name ?? 'Unknown Product');
            $itemPrice = $details['price'] ?? ($product->sale_price ?: ($product->price ?? 0));
            $itemOriginalPrice = $details['original_price'] ?? ($product->price ?? 0);
            $itemQuantity = min($details['quantity'] ?? 1, $currentStock);
            $itemImage = $details['image'] ?? ($product->images[0] ?? '/images/default-product.jpg');
            $itemSlug = $details['slug'] ?? ($product->slug ?? '');
            $itemBrand = $details['brand'] ?? ($product->brand ?? 'Unknown Brand');
            $itemCategory = $details['category'] ?? ($product->category->name ?? 'Unknown Category');
            $itemSku = $details['sku'] ?? ($product->sku ?? '');
            $itemSkuParent = $details['sku_parent'] ?? ($product->sku_parent ?? '');
            
            $itemSize = 'One Size';
            if (isset($details['size']) && !empty($details['size'])) {
                if (is_array($details['size'])) {
                    $itemSize = $details['size'][0] ?? 'One Size';
                } else {
                    $itemSize = (string) $details['size'];
                }
            } elseif (isset($details['product_options']['size'])) {
                $itemSize = $details['product_options']['size'] ?? 'One Size';
            } elseif ($product && !empty($product->available_sizes)) {
                if (is_array($product->available_sizes)) {
                    $itemSize = $product->available_sizes[0] ?? 'One Size';
                } else {
                    $itemSize = (string) $product->available_sizes;
                }
            }
            
            $productOptions = $details['product_options'] ?? [];
            if (!is_array($productOptions)) {
                $productOptions = [
                    'size' => $itemSize,
                    'color' => $details['color'] ?? 'Default'
                ];
            }
            
            $cartItems->push([
                'cart_key' => $cartKey,
                'id' => $productId,
                'name' => $itemName,
                'price' => $itemPrice,
                'original_price' => $itemOriginalPrice,
                'quantity' => $itemQuantity,
                'image' => $itemImage,
                'slug' => $itemSlug,
                'brand' => $itemBrand,
                'category' => $itemCategory,
                'stock' => $currentStock,
                'sku' => $itemSku,
                'sku_parent' => $itemSkuParent,
                'size' => $itemSize,
                'color' => $details['color'] ?? 'Default',
                'weight' => $details['weight'] ?? ($product->weight ?? 500),
                'product_options' => $productOptions,
                'subtotal' => $itemPrice * $itemQuantity
            ]);
        }
        
        return $cartItems;
    }

    private function calculateTotalWeight($cartItems)
    {
        $totalWeight = 0;
        
        foreach ($cartItems as $item) {
            $itemWeight = $item['weight'] ?? 500;
            $totalWeight += $itemWeight * $item['quantity'];
        }
        
        return max($totalWeight, 1000);
    }

    // Keep all location and shipping methods exactly the same...
    public function searchDestinations(Request $request)
{
    $search = $request->get('search');
    
    if (!$search || strlen($search) < 2) {
        return response()->json([
            'success' => true,
            'total' => 0,
            'data' => []
        ]);
    }

    try {
        Log::info('🔍 Search destinations request', [
            'search_term' => $search,
            'search_length' => strlen($search)
        ]);

        // Generate search variations with smart targeting
        $searchTerms = $this->generateSmartSearchVariations($search);
        $allResults = [];
        
        Log::info('🔍 Generated smart search variations', [
            'original' => $search,
            'variations' => $searchTerms
        ]);
        
        foreach ($searchTerms as $term) {
            $response = Http::timeout(10)->withHeaders([
                'key' => $this->rajaOngkirApiKey
            ])->get($this->rajaOngkirBaseUrl . '/destination/domestic-destination', [
                'search' => $term,
                'limit' => 25, // Increase limit to get more comprehensive results
                'offset' => 0
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['data']) && is_array($data['data'])) {
                    $allResults = array_merge($allResults, $data['data']);
                    
                    Log::info('🔍 Search variation results', [
                        'term' => $term,
                        'results_count' => count($data['data']),
                        'sample_ids' => array_slice(array_column($data['data'], 'id'), 0, 5)
                    ]);
                }
            }
        }
        
        // Smart filter and sort with quality-based ranking
        $filteredResults = $this->smartFilterAndSort($allResults, $search);
        
        Log::info('🎯 Final search results', [
            'raw_results_count' => count($allResults),
            'filtered_results_count' => count($filteredResults),
            'final_ids' => array_slice(array_column($filteredResults, 'id'), 0, 5),
            'top_labels' => array_slice(array_column($filteredResults, 'label'), 0, 3)
        ]);
        
        return response()->json([
            'success' => true,
            'total' => count($filteredResults),
            'data' => array_slice($filteredResults, 0, 12) // Return top 12 results
        ]);
        
    } catch (\Exception $e) {
        Log::error('RajaOngkir search error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Location search temporarily unavailable',
            'total' => 0,
            'data' => []
        ]);
    }
}

private function generateSearchVariations($search)
{
    $searchLower = strtolower(trim($search));
    $variations = [$searchLower];
    
    // For Bandung searches, prioritize Jawa Barat variations
    if (stripos($searchLower, 'bandung') !== false) {
        $variations[] = $searchLower . ' jawa barat';
        $variations[] = $searchLower . ' jabar';
        $variations[] = 'kota ' . $searchLower;
        $variations[] = $searchLower . ' kota';
    }
    
    // Add regional variations
    $regions = ['jakarta', 'selatan', 'utara', 'barat', 'timur', 'pusat'];
    foreach ($regions as $region) {
        $variations[] = $searchLower . ' ' . $region;
    }
    
    // If input contains comma, try to parse parts
    if (strpos($searchLower, ',') !== false) {
        $parts = array_map('trim', explode(',', $searchLower));
        $variations = array_merge($variations, $parts);
    }
    
    // Add variations with and without spaces
    if (strpos($searchLower, ' ') !== false) {
        $variations[] = str_replace(' ', '', $searchLower);
    }
    
    return array_unique(array_filter($variations, function($v) {
        return strlen(trim($v)) >= 2;
    }));
}

private function filterAndSortResultsWithValidation($results, $originalSearch)
{
    if (empty($results)) {
        return [];
    }
    
    $searchLower = strtolower(trim($originalSearch));
    $scored = [];
    $processedIds = []; // Track duplicates
    
    // Known working IDs (from successful tests) - prioritize these
    $knownWorkingIds = ['66274']; // Add more as we discover them
    
    foreach ($results as $result) {
        // Skip duplicates based on ID
        if (in_array($result['id'], $processedIds)) {
            continue;
        }
        $processedIds[] = $result['id'];
        
        $score = 0;
        $displayText = strtolower($result['subdistrict_name'] . ' ' . 
                                 $result['district_name'] . ' ' . 
                                 $result['city_name']);
        
        // CRITICAL: Prioritize known working IDs
        if (in_array($result['id'], $knownWorkingIds)) {
            $score += 1000; // Massive boost for known working IDs
            Log::info('🎯 Found known working ID', [
                'id' => $result['id'],
                'label' => $result['label']
            ]);
        }
        
        // Exact match in subdistrict name (kelurahan) = high score
        if (strtolower($result['subdistrict_name']) === $searchLower) {
            $score += 500;
        }
        
        // Partial match in subdistrict name
        if (strpos(strtolower($result['subdistrict_name']), $searchLower) !== false) {
            $score += 300;
        }
        
        // Match in district name (kecamatan)
        if (strpos(strtolower($result['district_name']), $searchLower) !== false) {
            $score += 200;
        }
        
        // Match in city name
        if (strpos(strtolower($result['city_name']), $searchLower) !== false) {
            $score += 100;
        }
        
        // Prefer Jawa Barat for Bandung searches (working region)
        if (stripos($searchLower, 'bandung') !== false && 
            stripos($result['province_name'], 'jawa barat') !== false) {
            $score += 200;
            Log::info('🎯 Bandung in Jawa Barat found', [
                'id' => $result['id'],
                'label' => $result['label']
            ]);
        }
        
        // Deprioritize problematic regions/patterns if we identify them
        if (stripos($result['province_name'], 'jawa timur') !== false && 
            stripos($searchLower, 'bandung') !== false) {
            $score -= 100; // Lower score for Bandung in Jawa Timur (seems problematic)
            Log::info('⚠️ Deprioritizing Bandung in Jawa Timur', [
                'id' => $result['id'],
                'label' => $result['label']
            ]);
        }
        
        // Match anywhere in display text
        if (strpos($displayText, $searchLower) !== false) {
            $score += 50;
        }
        
        // Add bonus for complete, properly formatted addresses
        if (strlen($result['label']) > 20 && strpos($result['label'], ',') !== false) {
            $score += 25;
        }
        
        if ($score > 0) {
            $scored[] = [
                'data' => $result,
                'score' => $score,
                'debug_info' => [
                    'id' => $result['id'],
                    'score' => $score,
                    'is_known_working' => in_array($result['id'], $knownWorkingIds),
                    'province' => $result['province_name']
                ]
            ];
        }
    }
    
    // Sort by score descending (highest score first)
    usort($scored, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    // Log top results for debugging
    $topResults = array_slice($scored, 0, 5);
    Log::info('🏆 Top 5 search results by score', [
        'results' => array_map(function($item) {
            return [
                'id' => $item['data']['id'],
                'score' => $item['score'],
                'label' => $item['data']['label'],
                'province' => $item['data']['province_name']
            ];
        }, $topResults)
    ]);
    
    // Extract final data
    $unique = [];
    foreach ($scored as $item) {
        $unique[] = $item['data'];
    }
    
    return $unique;
}



public function calculateShipping(Request $request)
{
    try {
        $destinationId = $request->input('destination_id');
        $destinationLabel = $request->input('destination_label', '');
        $weight = $request->input('weight', 1000);

        Log::info('🚢 Web Shipping calculation request', [
            'destination_id' => $destinationId,
            'destination_label' => $destinationLabel,
            'weight' => $weight,
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'user_agent' => $request->userAgent(),
            'all_input' => $request->all()
        ]);

        // CRITICAL: Strict validation for web requests
        if (!$destinationId || empty(trim($destinationId)) || !is_numeric($destinationId)) {
            Log::error('❌ Web: Invalid destination_id', [
                'provided_destination_id' => $destinationId,
                'is_numeric' => is_numeric($destinationId),
                'is_empty' => empty(trim($destinationId)),
                'type' => gettype($destinationId)
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'INVALID_DESTINATION',
                'message' => 'Please select a valid delivery location',
                'debug' => [
                    'destination_id' => $destinationId,
                    'destination_label' => $destinationLabel,
                    'validation_failed' => 'destination_id must be numeric and not empty'
                ]
            ], 422);
        }

        // Get origin with validation
        $originId = env('STORE_ORIGIN_CITY_ID', 17549);
        
        if (!$originId || !is_numeric($originId)) {
            Log::error('❌ Web: Invalid origin configuration', [
                'origin_id' => $originId,
                'env_value' => env('STORE_ORIGIN_CITY_ID')
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'CONFIGURATION_ERROR',
                'message' => 'Store location configuration error'
            ], 500);
        }

        // Ensure minimum weight
        $weight = max(1000, (int) $weight);
        
        Log::info('🎯 Web: Starting shipping calculation', [
            'origin_id' => $originId,
            'destination_id' => $destinationId,
            'weight' => $weight,
            'api_url' => $this->rajaOngkirBaseUrl . '/calculate/domestic-cost'
        ]);

        // Make API request using EXACT same format as successful command
        $startTime = microtime(true);
        
        $response = Http::asForm()
            ->withHeaders([
                'accept' => 'application/json',
                'key' => $this->rajaOngkirApiKey,
                'user-agent' => 'Laravel-Web-Request'
            ])
            ->timeout(30)
            ->retry(2, 1000)
            ->post($this->rajaOngkirBaseUrl . '/calculate/domestic-cost', [
                'origin' => $originId,
                'destination' => $destinationId,
                'weight' => $weight,
                'courier' => 'jne'
            ]);

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::info("📡 Web: RajaOngkir API Response", [
            'status_code' => $response->status(),
            'successful' => $response->successful(),
            'execution_time_ms' => $executionTime,
            'response_size' => strlen($response->body()),
            'content_type' => $response->header('content-type')
        ]);

        // Handle API errors
        if (!$response->successful()) {
            $errorBody = $response->body();
            $statusCode = $response->status();
            
            Log::error("❌ Web: RajaOngkir API Error", [
                'status_code' => $statusCode,
                'error_response' => $errorBody,
                'request_data' => [
                    'origin' => $originId,
                    'destination' => $destinationId,
                    'weight' => $weight,
                    'courier' => 'jne'
                ]
            ]);

            return response()->json([
                'success' => false,
                'error' => 'API_REQUEST_FAILED',
                'message' => $this->getWebErrorMessage($statusCode),
                'debug' => [
                    'api_status' => $statusCode,
                    'execution_time_ms' => $executionTime
                ]
            ], 422);
        }

        // Parse successful response
        $data = $response->json();
        
        Log::info("✅ Web: API Success Response", [
            'has_data' => isset($data['data']),
            'data_count' => isset($data['data']) ? count($data['data']) : 0,
            'execution_time_ms' => $executionTime,
            'meta_message' => $data['meta']['message'] ?? 'No meta message'
        ]);

        // Validate response structure
        if (!isset($data['data']) || !is_array($data['data']) || empty($data['data'])) {
            Log::error("❌ Web: Invalid or empty response", [
                'has_data_key' => isset($data['data']),
                'is_array' => isset($data['data']) ? is_array($data['data']) : false,
                'data_count' => isset($data['data']) ? count($data['data']) : 0,
                'response_structure' => array_keys($data ?? [])
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'NO_SHIPPING_OPTIONS',
                'message' => 'No shipping services available for this destination',
                'debug' => [
                    'api_response_meta' => $data['meta'] ?? null,
                    'execution_time_ms' => $executionTime
                ]
            ], 422);
        }

        // Parse shipping options with enhanced validation
        $shippingOptions = [];
        
        foreach ($data['data'] as $index => $option) {
            if (!is_array($option)) {
                Log::warning("Web: Skipping invalid option at index {$index}", [
                    'option_type' => gettype($option)
                ]);
                continue;
            }
            
            $cost = (int) ($option['cost'] ?? 0);
            $service = trim($option['service'] ?? '');
            
            if ($cost <= 0 || empty($service)) {
                Log::warning("Web: Skipping invalid option", [
                    'index' => $index,
                    'cost' => $cost,
                    'service' => $service
                ]);
                continue;
            }
            
            $shippingOptions[] = [
                'courier' => strtoupper($option['code'] ?? 'JNE'),
                'courier_name' => $option['name'] ?? 'Jalur Nugraha Ekakurir (JNE)',
                'service' => $service,
                'description' => $option['description'] ?? $service,
                'cost' => $cost,
                'formatted_cost' => 'Rp ' . number_format($cost, 0, ',', '.'),
                'etd' => $option['etd'] ?? 'N/A',
                'formatted_etd' => $option['etd'] ?? 'N/A',
                'recommended' => $index === 0, // First option is recommended
                'type' => 'real_api'
            ];
        }

        // ⭐ FILTER: Hapus layanan JNE yang tidak diinginkan
        $shippingOptions = $this->filterCheckoutJNEServices($shippingOptions);

        if (empty($shippingOptions)) {
            Log::error("❌ Web: No valid shipping options after parsing and filtering", [
                'raw_options_count' => count($data['data']),
                'sample_raw_option' => $data['data'][0] ?? null
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'NO_SHIPPING_OPTIONS_AFTER_FILTERING',
                'message' => 'No shipping services available for this destination after filtering',
                'debug' => [
                    'raw_options_count' => count($data['data'])
                ]
            ], 422);
        }

        Log::info("🎯 Web: Shipping calculation successful", [
            'options_count' => count($shippingOptions),
            'sample_options' => array_map(function($opt) {
                return $opt['service'] . ' - Rp ' . number_format($opt['cost']);
            }, array_slice($shippingOptions, 0, 3)),
            'execution_time_ms' => $executionTime
        ]);

        return response()->json([
            'success' => true,
            'options' => $shippingOptions,
            'message' => 'Shipping options calculated successfully',
            'meta' => [
                'options_count' => count($shippingOptions),
                'execution_time_ms' => $executionTime,
                'destination_label' => $destinationLabel
            ]
        ]);
        
    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        Log::error('❌ Web: Connection timeout', [
            'error' => $e->getMessage(),
            'destination_id' => $destinationId ?? 'unknown'
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'CONNECTION_TIMEOUT',
            'message' => 'Connection to shipping service timed out. Please try again.'
        ], 408);
        
    } catch (\Exception $e) {
        Log::error('❌ Web: Unexpected error', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'destination_id' => $destinationId ?? 'unknown'
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'UNEXPECTED_ERROR',
            'message' => 'An unexpected error occurred. Please try again.'
        ], 500);
    }
}


private function generateSmartSearchVariations($search)
{
    $searchLower = strtolower(trim($search));
    $variations = [$searchLower];
    
    // Smart variations for major cities
    if (stripos($searchLower, 'bandung') !== false) {
        // For Bandung, prioritize Jawa Barat searches
        $variations = [
            $searchLower . ' jawa barat',
            $searchLower . ' jabar', 
            'kota ' . $searchLower,
            'bandung jawa barat',
            'kota bandung',
            $searchLower
        ];
    } elseif (stripos($searchLower, 'jakarta') !== false) {
        // For Jakarta, prioritize DKI variants
        $variations = [
            $searchLower . ' dki',
            $searchLower . ' jakarta',
            'dki ' . $searchLower,
            $searchLower
        ];
    } elseif (stripos($searchLower, 'surabaya') !== false) {
        // For Surabaya, prioritize Jawa Timur
        $variations = [
            $searchLower . ' jawa timur',
            $searchLower . ' jatim',
            'kota ' . $searchLower,
            $searchLower
        ];
    } else {
        // Generic variations
        $regions = ['jakarta', 'jawa barat', 'jawa timur', 'jawa tengah'];
        foreach ($regions as $region) {
            $variations[] = $searchLower . ' ' . $region;
        }
    }
    
    // Add common prefixes/suffixes
    $variations[] = 'kota ' . $searchLower;
    $variations[] = 'kabupaten ' . $searchLower;
    $variations[] = $searchLower . ' kota';
    
    // Parse comma-separated input
    if (strpos($searchLower, ',') !== false) {
        $parts = array_map('trim', explode(',', $searchLower));
        $variations = array_merge($variations, $parts);
        
        // Try reverse order
        if (count($parts) >= 2) {
            $variations[] = $parts[1] . ' ' . $parts[0];
        }
    }
    
    // Remove duplicates and filter
    return array_unique(array_filter($variations, function($v) {
        return strlen(trim($v)) >= 2;
    }));
}

/**
 * Smart filter and sort with quality-based ranking
 */
private function smartFilterAndSort($results, $originalSearch)
{
    if (empty($results)) {
        return [];
    }
    
    $searchLower = strtolower(trim($originalSearch));
    $scored = [];
    $processedIds = []; // Track duplicates
    
    foreach ($results as $result) {
        // Skip duplicates based on ID
        if (in_array($result['id'], $processedIds)) {
            continue;
        }
        $processedIds[] = $result['id'];
        
        $score = $this->calculateLocationQualityScore($result, $searchLower);
        
        if ($score > -50) { // Only include locations with reasonable scores
            $scored[] = [
                'data' => $result,
                'score' => $score,
                'debug_info' => [
                    'id' => $result['id'],
                    'score' => $score,
                    'province' => $result['province_name'],
                    'city' => $result['city_name']
                ]
            ];
        }
    }
    
    // Sort by score descending (highest quality first)
    usort($scored, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    // Log top results for debugging
    $topResults = array_slice($scored, 0, 8);
    Log::info('🏆 Top search results by quality score', [
        'search_term' => $originalSearch,
        'results' => array_map(function($item) {
            return [
                'id' => $item['data']['id'],
                'score' => $item['score'],
                'label' => $item['data']['label'],
                'province' => $item['data']['province_name'],
                'city' => $item['data']['city_name']
            ];
        }, $topResults)
    ]);
    
    // Extract final data
    $unique = [];
    foreach ($scored as $item) {
        $unique[] = $item['data'];
    }
    
    return $unique;
}

/**
 * Calculate quality score for a location based on multiple factors
 */
private function calculateLocationQualityScore($location, $searchTerm)
{
    $score = 0;
    
    // Get location components
    $subdistrict = strtolower($location['subdistrict_name'] ?? '');
    $district = strtolower($location['district_name'] ?? '');
    $city = strtolower($location['city_name'] ?? '');
    $province = strtolower($location['province_name'] ?? '');
    $label = strtolower($location['label'] ?? '');
    
    // CRITICAL: Province-City consistency checks
    if (stripos($searchTerm, 'bandung') !== false) {
        if (stripos($province, 'jawa barat') !== false && stripos($city, 'bandung') !== false) {
            $score += 1000; // Massive boost for correct Bandung in Jawa Barat
            Log::info('🎯 Found correct Bandung in Jawa Barat', [
                'id' => $location['id'],
                'label' => $location['label']
            ]);
        } elseif (stripos($province, 'jawa timur') !== false) {
            $score -= 500; // Massive penalty for wrong province
            Log::info('⚠️ Penalizing Bandung in wrong province (Jawa Timur)', [
                'id' => $location['id'],
                'label' => $location['label']
            ]);
        }
    }
    
    if (stripos($searchTerm, 'jakarta') !== false) {
        if (stripos($province, 'dki') !== false || stripos($province, 'jakarta') !== false) {
            $score += 1000; // Boost for correct Jakarta province
        } elseif (stripos($city, 'jakarta') !== false) {
            $score += 500; // Good city match
        }
    }
    
    // Text matching scores
    if ($subdistrict === $searchTerm) {
        $score += 800; // Exact subdistrict match
    } elseif (strpos($subdistrict, $searchTerm) !== false) {
        $score += 400; // Partial subdistrict match
    }
    
    if ($district === $searchTerm) {
        $score += 600; // Exact district match
    } elseif (strpos($district, $searchTerm) !== false) {
        $score += 300; // Partial district match
    }
    
    if ($city === $searchTerm) {
        $score += 500; // Exact city match
    } elseif (strpos($city, $searchTerm) !== false) {
        $score += 250; // Partial city match
    }
    
    // Label quality factors
    if (strpos($label, $searchTerm) !== false) {
        $score += 100; // General label match
    }
    
    // Quality indicators
    if ($subdistrict && $subdistrict !== '-' && strlen($subdistrict) > 1) {
        $score += 50; // Has proper subdistrict
    } else {
        $score -= 25; // Missing or invalid subdistrict
    }
    
    if ($district && $district !== '-' && strlen($district) > 1) {
        $score += 30; // Has proper district
    }
    
    // Postal code in label indicates completeness
    if (preg_match('/\d{5}/', $label)) {
        $score += 25; // Has postal code
    }
    
    // Penalize obviously incomplete or invalid entries
    if (strpos($label, '-, ') !== false || strpos($label, ' -, ') !== false) {
        $score -= 50; // Has missing components
    }
    
    // Length and completeness
    if (strlen($label) > 30 && substr_count($label, ',') >= 3) {
        $score += 20; // Well-formatted complete address
    }
    
    return $score;
}

/**
 * Parse REAL shipping response - strict validation
 */
private function parseRealShippingResponse($data)
{
    $options = [];
    
    try {
        if (!isset($data['data']) || !is_array($data['data'])) {
            Log::error('Invalid data structure for parsing', [
                'data_structure' => gettype($data),
                'has_data_key' => isset($data['data'])
            ]);
            return [];
        }
        
        foreach ($data['data'] as $index => $option) {
            // Strict validation for each option
            if (!is_array($option)) {
                Log::warning("Skipping invalid option at index {$index}", [
                    'option_type' => gettype($option),
                    'option_value' => $option
                ]);
                continue;
            }
            
            $cost = (int) ($option['cost'] ?? 0);
            $service = trim($option['service'] ?? '');
            $code = trim($option['code'] ?? '');
            
            // Skip options without valid cost or service
            if ($cost <= 0 || empty($service)) {
                Log::warning("Skipping invalid shipping option", [
                    'index' => $index,
                    'cost' => $cost,
                    'service' => $service,
                    'raw_option' => $option
                ]);
                continue;
            }
            
            $parsedOption = [
                'courier' => strtoupper($code ?: 'JNE'),
                'courier_name' => $option['name'] ?? 'Jalur Nugraha Ekakurir (JNE)',
                'service' => $service,
                'description' => $option['description'] ?? $service,
                'cost' => $cost,
                'formatted_cost' => 'Rp ' . number_format($cost, 0, ',', '.'),
                'etd' => $option['etd'] ?? 'N/A',
                'formatted_etd' => $option['etd'] ?? 'N/A',
                'recommended' => false, // Can be set based on business logic
                'type' => 'real_api'
            ];
            
            $options[] = $parsedOption;
            
            Log::debug("Parsed shipping option {$index}", [
                'service' => $parsedOption['service'],
                'cost' => $parsedOption['cost'],
                'etd' => $parsedOption['etd']
            ]);
        }
        
        Log::info('Shipping options parsing completed', [
            'total_raw_options' => count($data['data']),
            'valid_parsed_options' => count($options)
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error parsing shipping response', [
            'error' => $e->getMessage(),
            'data_sample' => array_slice($data['data'] ?? [], 0, 2)
        ]);
        return [];
    }
    
    return $options;
}

/**
 * Get specific error message based on API status code
 */
private function getWebErrorMessage($statusCode)
{
    switch ($statusCode) {
        case 400:
            return 'Invalid request parameters. Please check your destination selection.';
        case 401:
            return 'API authentication failed. Please contact support.';
        case 403:
            return 'API access forbidden. Please contact support.';
        case 404:
            return 'Shipping service endpoint not found. Please contact support.';
        case 422:
            return 'Invalid destination or shipping parameters.';
        case 429:
            return 'Too many requests. Please wait a moment and try again.';
        case 500:
            return 'Shipping service is temporarily unavailable. Please try again in a few minutes.';
        case 502:
        case 503:
        case 504:
            return 'Shipping service is temporarily down. Please try again later.';
        default:
            return "Shipping service error (HTTP {$statusCode}). Please try again.";
    }
}

    /**
     * CRITICAL FIX: Store method with VOUCHER integration that works
     */

public function store(Request $request)
{
    Log::info('Checkout request received with points support', [
        'payment_method' => $request->payment_method,
        'applied_voucher_code' => $request->get('applied_voucher_code'),
        'points_used' => $request->get('points_used', 0),
        'points_discount' => $request->get('points_discount', 0)
    ]);

    // Validation (tambahkan fields points)
    $request->validate([
        'gender' => 'nullable|in:mens,womens,kids',
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'phone' => 'required|string|max:20',
        'birthdate' => 'nullable|date|before:today',
        
        // Address fields
        'saved_address_id' => 'nullable|string',
        'address_label' => 'required_without:saved_address_id|nullable|in:Kantor,Rumah',
        'recipient_name' => 'required|string|max:255',
        'phone_recipient' => 'required|string|max:20|regex:/^[0-9+\-\s\(\)]{10,}$/',
        'province_name' => 'required|string|max:100',
        'city_name' => 'required|string|max:100',
        'subdistrict_name' => 'required|string|max:100',
        'postal_code' => 'required|string|size:5|regex:/^[0-9]{5}$/',
        'destination_id' => 'nullable|string|max:50',
        'street_address' => 'required|string|min:10|max:500',
        
        'shipping_method' => 'required|string',
        'shipping_cost' => 'required|numeric|min:0',
        'payment_method' => 'required|in:bank_transfer,credit_card,ewallet',
        
        // Voucher fields
        'applied_voucher_code' => 'nullable|string|max:50',
        'applied_voucher_discount' => 'nullable|numeric|min:0',
        
        // Points fields
        'points_used' => 'nullable|integer|min:0',
        'points_discount' => 'nullable|numeric|min:0',
        
        'privacy_accepted' => 'required|boolean',
    ]);
    
    // CRITICAL FIX: Initialize ALL variables at the start
    $pointsUsed = 0;
    $pointsDiscount = 0;
    $user = Auth::user();
    $orderNumber = null;
    $order = null;
    
    try {
        DB::beginTransaction();

        $cart = Session::get('cart', []);
        
        if (empty($cart)) {
            throw new \Exception('Cart is empty');
        }

        $cartItems = $this->getCartItems($cart);
        
        if ($cartItems->isEmpty()) {
            throw new \Exception('No valid items in cart');
        }

        $subtotal = $cartItems->sum('subtotal');
        $shippingCost = (float) $request->shipping_cost;
        
        // VOUCHER HANDLING
        $discountAmount = 0;
        $voucherInfo = null;

        if ($request->get('applied_voucher_code') && $request->get('applied_voucher_discount')) {
            $discountAmount = (float) $request->get('applied_voucher_discount');
            $voucherInfo = [
                'voucher_code' => $request->get('applied_voucher_code'),
                'discount_amount' => $discountAmount,
                'source' => 'form_data'
            ];
        } else {
            $sessionVoucher = Session::get('applied_voucher', null);
            if ($sessionVoucher && isset($sessionVoucher['discount_amount'])) {
                $discountAmount = (float) $sessionVoucher['discount_amount'];
                $voucherInfo = [
                    'voucher_code' => $sessionVoucher['voucher_code'] ?? 'unknown',
                    'discount_amount' => $discountAmount,
                    'source' => 'session'
                ];
            }
        }
        
        // POINTS HANDLING - FIXED: Proper initialization
        if ($request->get('points_used') && $request->get('points_discount')) {
            $pointsUsed = (int) $request->get('points_used');
            $pointsDiscount = (float) $request->get('points_discount');
            
            // Validate user has enough points
            if ($user && $pointsUsed > ($user->points_balance ?? 0)) {
                throw new \Exception('Poin tidak mencukupi');
            }
        } else {
            // Fallback to session
            $sessionPoints = Session::get('applied_points', null);
            if ($sessionPoints) {
                $pointsUsed = (int) ($sessionPoints['points_used'] ?? 0);
                $pointsDiscount = (float) ($sessionPoints['discount'] ?? 0);
            }
        }
        
        $tax = 0; // No tax as per existing system
        $totalAmount = $subtotal + $shippingCost - $discountAmount - $pointsDiscount;
        $totalAmount = max(0, $totalAmount);

        Log::info('Order totals calculated with points system', [
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'discount_amount' => $discountAmount,
            'points_discount' => $pointsDiscount,
            'total_amount' => $totalAmount,
            'voucher_applied' => !empty($voucherInfo),
            'points_used' => $pointsUsed
        ]);

        // Handle user and address
        $user = $this->handleUserAccountCreationOrUpdate($request);
        $addressData = $this->handleAddressData($request, $user);

        // Generate order number
        do {
            $orderNumber = 'SF-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        } while (Order::where('order_number', $orderNumber)->exists());

        // Order data with points support
        $orderData = [
            'order_number' => $orderNumber,
            'user_id' => $user ? $user->id : null,
            'customer_name' => $request->first_name . ' ' . $request->last_name,
            'customer_email' => $request->email,
            'customer_phone' => $request->phone,
            
            'shipping_address' => $addressData['full_address'],
            'billing_address' => $addressData['full_address'],
            'shipping_destination_id' => $addressData['destination_id'] ?? $request->destination_id,
            'shipping_destination_label' => $addressData['location_string'] ?? $request->destination_label,
            'shipping_postal_code' => $addressData['postal_code'],
            
            'payment_method' => $request->payment_method,
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'tax_amount' => 0,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'currency' => 'IDR',
            
            'status' => 'pending',
            'store_origin' => env('STORE_ORIGIN_CITY_NAME', 'Jakarta'),
            'notes' => trim(($request->notes ?? '') . "\n" . "Shipping: " . $request->shipping_method),
            
            'meta_data' => json_encode([
                'shipping_method' => $request->shipping_method,
                'destination_info' => [
                    'id' => $addressData['destination_id'] ?? $request->destination_id,
                    'label' => $addressData['location_string'] ?? $request->destination_label,
                    'postal_code' => $addressData['postal_code'],
                    'full_address' => $addressData['full_address']
                ],
                'address_info' => [
                    'address_id' => $addressData['address_id'] ?? null,
                    'label' => $addressData['label'],
                    'recipient_name' => $addressData['recipient_name'],
                    'phone_recipient' => $addressData['phone_recipient'],
                    'province_name' => $addressData['province_name'],
                    'city_name' => $addressData['city_name'],
                    'subdistrict_name' => $addressData['subdistrict_name'],
                    'street_address' => $addressData['street_address'],
                ],
                'customer_info' => [
                    'gender' => $request->gender ?? null,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'birthdate' => $request->birthdate ?? null,
                    'newsletter_subscribe' => $request->newsletter_subscribe ?? false,
                ],
                'voucher_info' => $voucherInfo,
                'points_info' => [
                    'points_used' => $pointsUsed,
                    'points_discount' => $pointsDiscount,
                    'user_points_balance_before' => $user ? ($user->points_balance ?? 0) : 0,
                ],
                'checkout_info' => [
                    'created_via' => 'web_checkout_with_points_support',
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                    'checkout_timestamp' => now()->toISOString(),
                    'tax_rate' => 0,
                    'cart_items_count' => $cartItems->count(),
                    'total_weight' => $cartItems->sum(function($item) { 
                        return ($item['weight'] ?? 500) * $item['quantity']; 
                    }),
                    'subtotal_breakdown' => [
                        'items_subtotal' => $subtotal,
                        'shipping_cost' => $shippingCost,
                        'tax_amount' => 0,
                        'discount_amount' => $discountAmount,
                        'points_discount' => $pointsDiscount,
                        'total_amount' => $totalAmount
                    ]
                ]
            ]),
            
            'created_at' => now(),
            'updated_at' => now()
        ];

        // Filter existing columns
        $existingColumns = [
            'order_number', 'user_id', 'customer_name', 'customer_email', 'customer_phone',
            'status', 'subtotal', 'tax_amount', 'shipping_cost', 'discount_amount', 
            'total_amount', 'currency', 'shipping_address', 'billing_address', 
            'store_origin', 'payment_method', 'payment_token', 
            'payment_url', 'tracking_number', 'shipped_at', 'delivered_at', 
            'notes', 'meta_data', 'created_at', 'updated_at',
            'shipping_destination_id', 'shipping_destination_label', 'shipping_postal_code',
            'snap_token', 'payment_response'
        ];

        $filteredOrderData = array_intersect_key($orderData, array_flip($existingColumns));

        Log::info('Creating order with points support', [
            'order_number' => $orderNumber,
            'customer_email' => $request->email,
            'payment_method' => $request->payment_method,
            'total_amount' => $totalAmount,
            'discount_amount' => $discountAmount,
            'points_discount' => $pointsDiscount,
            'points_used' => $pointsUsed,
            'voucher_applied' => !empty($voucherInfo),
            'initial_status' => 'pending',
            'user_id' => $user ? $user->id : null,
        ]);

        $order = Order::create($filteredOrderData);

        // Create order items
        foreach ($cartItems as $item) {
            $product = Product::lockForUpdate()->find($item['id']);
            
            if (!$product || $product->stock_quantity < $item['quantity']) {
                throw new \Exception("Insufficient stock for {$item['name']}");
            }

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['id'],
                'product_name' => $item['name'],
                'product_sku' => $item['sku'] ?? '',
                'product_price' => (float) $item['price'],
                'quantity' => (int) $item['quantity'],
                'total_price' => (float) $item['subtotal']
            ]);
            
            // PENTING: Komentar baris berikut untuk tidak mengurangi stok saat checkout awal
            // $product->decrement('stock_quantity', $item['quantity']);
        }

        // DEDUCT POINTS FROM USER
        if ($pointsUsed > 0 && $user) {
            $user->decrement('points_balance', $pointsUsed);
            
            Log::info('Points deducted from user', [
                'user_id' => $user->id,
                'points_used' => $pointsUsed,
                'points_discount' => $pointsDiscount,
                'order_number' => $order->order_number,
                'remaining_balance' => $user->fresh()->points_balance
            ]);
        }

        DB::commit();

        // Clear session data
        Session::forget(['cart', 'applied_voucher', 'applied_points']);

        Log::info('Order created successfully with points support', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_method' => $request->payment_method,
            'total_amount' => $totalAmount,
            'discount_amount' => $discountAmount,
            'points_discount' => $pointsDiscount,
            'points_used' => $pointsUsed,
            'status' => $order->status,
            'customer_email' => $request->email,
            'user_id' => $user ? $user->id : null,
        ]);

        // Create Midtrans payment
        $midtrans = $this->createMidtransPayment($order, $cartItems, $request);

        if ($midtrans && (isset($midtrans['token']) || isset($midtrans['force_hosted']))) {
            
            // Handle successful token creation
            if (isset($midtrans['token'])) {
                $snapToken = $midtrans['token'];
                $redirectUrl = $midtrans['redirect_url'] ?? null;
                $preferHosted = $midtrans['prefer_hosted'] ?? false;
                $forceHosted = $midtrans['force_hosted'] ?? false;
                $networkInfo = $midtrans['network_info'] ?? null;

                $order->update([
                    'snap_token' => $snapToken,
                    'payment_url' => $redirectUrl,
                ]);

                Log::info('Midtrans token created successfully with enhanced handling', [
                    'order_number' => $order->order_number,
                    'snap_token_length' => strlen($snapToken),
                    'final_amount' => $totalAmount,
                    'points_discount_applied' => $pointsDiscount,
                    'prefer_hosted' => $preferHosted,
                    'force_hosted' => $forceHosted,
                    'has_redirect_url' => !empty($redirectUrl),
                    'network_can_load_popup' => $networkInfo['can_load_popup'] ?? 'unknown'
                ]);

                if ($request->ajax() || $request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Order created successfully. Opening payment gateway...',
                        'order_number' => $order->order_number,
                        'customer_name' => $order->customer_name,
                        'snap_token' => $snapToken,
                        'redirect_url' => $redirectUrl ?: route('checkout.payment', ['orderNumber' => $order->order_number]),
                        'prefer_hosted' => $preferHosted,     // 🆕 Network detection signal
                        'force_hosted' => $forceHosted,       // 🆕 Force hosted flag
                        'network_info' => $networkInfo,       // 🆕 Network information
                        'fallback_strategy' => $preferHosted ? 'hosted_payment' : 'popup_with_fallback'
                    ]);
                }

                return redirect()
                    ->route('checkout.payment', ['orderNumber' => $order->order_number])
                    ->with('snap_token', $snapToken)
                    ->with('prefer_hosted', $preferHosted)
                    ->with('force_hosted', $forceHosted);
                    
            } 
            // Handle fallback scenarios (no token but has fallback info)
            elseif (isset($midtrans['force_hosted']) && $midtrans['force_hosted']) {
                $errorMessage = $midtrans['error'] ?? 'Payment gateway temporarily unavailable';
                $fallbackUrl = $midtrans['fallback_url'] ?? route('checkout.payment', ['orderNumber' => $order->order_number]);
                
                Log::warning('Midtrans token creation failed, using fallback strategy', [
                    'order_number' => $order->order_number,
                    'error' => $errorMessage,
                    'fallback_url' => $fallbackUrl,
                    'prefer_hosted' => $midtrans['prefer_hosted'] ?? true
                ]);

                if ($request->ajax() || $request->expectsJson()) {
                    return response()->json([
                        'success' => true, // Still success because order was created
                        'message' => 'Order created successfully. Redirecting to secure payment page...',
                        'order_number' => $order->order_number,
                        'customer_name' => $order->customer_name,
                        'snap_token' => null,
                        'redirect_url' => $fallbackUrl,
                        'prefer_hosted' => true,
                        'force_hosted' => true,
                        'fallback_strategy' => 'hosted_payment_only',
                        'warning' => 'Using secure payment page due to connectivity'
                    ]);
                }

                return redirect($fallbackUrl)
                      ->with('warning', 'Payment gateway opened in secure mode. Your order has been created successfully.');
            }
            
        } else {
            // Complete failure - no token and no fallback
            Log::error('Complete Midtrans payment creation failure', [
                'order_number' => $order->order_number,
                'payment_method' => $request->payment_method,
                'total_amount' => $totalAmount,
                'midtrans_response' => $midtrans
            ]);

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to create payment session. Please try again or contact support.',
                    'order_number' => $order->order_number,
                    'fallback_url' => route('checkout.payment', ['orderNumber' => $order->order_number])
                ], 500);
            }

            return redirect()->route('checkout.success', ['orderNumber' => $order->order_number])
                          ->with('error', 'Order created but payment session failed. Please contact support to complete payment.');
        }

    } catch (\Exception $e) {
        DB::rollback();
        
        // REFUND POINTS IF ORDER FAILED - Variables are now properly initialized
        if ($pointsUsed > 0 && $user) {
            $user->increment('points_balance', $pointsUsed);
            Log::info('Points refunded due to order failure', [
                'user_id' => $user->id,
                'points_refunded' => $pointsUsed,
                'new_balance' => $user->fresh()->points_balance
            ]);
        }
        
        Log::error('Checkout error with points support: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
            'payment_method' => $request->payment_method ?? 'unknown',
            'customer_email' => $request->email ?? 'unknown',
            'points_used' => $pointsUsed,
            'order_number' => $orderNumber ?? 'not_generated',
        ]);
        
        $errorMessage = 'Failed to process checkout: ' . $e->getMessage();
        
        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => $errorMessage
            ], 500);
        }
        
        return back()->withInput()->with('error', $errorMessage);
    }
}

/**
 * Process stock reduction for paid orders and send to Ginee
 */
/**
 * Process stock reduction for paid orders and send to Ginee
 */
private function processStockReductionForPaidOrder(Order $order)
{
    try {
        $orderItems = $order->orderItems()->with('product')->get();
        $stockUpdates = [];
        
        foreach ($orderItems as $item) {
            if (!$item->product) {
                continue;
            }
            
            // Kurangi stok di database lokal
            $oldStock = $item->product->stock_quantity;
            $newStock = max(0, $oldStock - $item->quantity);
            
            $item->product->update([
                'stock_quantity' => $newStock
            ]);
            
            Log::info("📉 Stock reduced for product", [
                'product_id' => $item->product_id,
                'sku' => $item->product->sku,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'quantity' => $item->quantity,
                'order_id' => $order->order_number
            ]);
            
            // Siapkan data untuk Ginee API - GUNAKAN FORMAT STOCK UPDATE BIASA
            if ($item->product->sku) {
                $stockUpdates[] = [
                    'masterSku' => $item->product->sku,
                    'quantity' => $newStock // Set langsung ke nilai absolut
                ];
            }
        }
        
        // Kirim perubahan stok ke Ginee menggunakan updateStock()
        if (!empty($stockUpdates)) {
            $gineeClient = app(\App\Services\GineeClient::class);
            
            // Gunakan updateStock API sebagai alternatif
            $result = $gineeClient->updateStock($stockUpdates);
            
            Log::info("🔄 Absolute stock update sent to Ginee", [
                'order_id' => $order->order_number,
                'updates' => count($stockUpdates),
                'result' => $result
            ]);
        }
    } catch (\Exception $e) {
        Log::error("❌ Error processing stock reduction", [
            'order_id' => $order->order_number,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Helper function to get enabled warehouse ID
 */
private function getEnabledWarehouseId()
{
    return config('services.ginee.warehouse_id');
}

/**
 * Process stock restoration for cancelled orders
 */
private function processStockRestoreForCancelledOrder(Order $order)
{
    try {
        $orderItems = $order->orderItems()->with('product')->get();
        $stockUpdates = [];
        
        foreach ($orderItems as $item) {
            if (!$item->product) {
                continue;
            }
            
            // Kembalikan stok di database lokal
            $oldStock = $item->product->stock_quantity;
            $newStock = $oldStock + $item->quantity;
            
            $item->product->update([
                'stock_quantity' => $newStock
            ]);
            
            Log::info("📈 Stock restored for product", [
                'product_id' => $item->product_id,
                'sku' => $item->product->sku,
                'old_stock' => $oldStock,
                'new_stock' => $newStock,
                'quantity' => $item->quantity,
                'order_id' => $order->order_number
            ]);
            
            // Siapkan data untuk Ginee API
            if ($item->product->sku) {
                $stockUpdates[] = [
                    'masterSku' => $item->product->sku,
                    'action' => 'INCREASE',
                    'quantity' => $item->quantity,
                    'remark' => "Order {$order->order_number} cancelled"
                ];
            }
        }
        
        // Kirim perubahan stok ke Ginee jika ada
        if (!empty($stockUpdates)) {
            $gineeClient = app(\App\Services\GineeClient::class);
            
            // Gunakan available-stock API untuk mengembalikan stok
            $result = $gineeClient->updateAvailableStock($stockUpdates);
            
            Log::info("🔄 Stock restoration sent to Ginee", [
                'order_id' => $order->order_number,
                'updates' => count($stockUpdates),
                'result' => $result
            ]);
        }
    } catch (\Exception $e) {
        Log::error("❌ Error processing stock restoration", [
            'order_id' => $order->order_number,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

    /**
     * Handle address data processing - keep same as working version
     */
    private function handleAddressData(Request $request, $user)
    {
        if (!empty($request->saved_address_id) && $request->saved_address_id !== 'new' && $user) {
            $savedAddress = $user->addresses()
                                ->where('id', $request->saved_address_id)
                                ->where('is_active', true)
                                ->first();
                                
            if ($savedAddress) {
                Log::info('Using saved address for checkout', [
                    'user_id' => $user->id,
                    'address_id' => $savedAddress->id,
                    'address_label' => $savedAddress->label,
                    'recipient_name' => $savedAddress->recipient_name
                ]);
                
                return [
                    'address_id' => $savedAddress->id,
                    'label' => $savedAddress->label,
                    'recipient_name' => $savedAddress->recipient_name,
                    'phone_recipient' => $savedAddress->phone_recipient,
                    'province_name' => $savedAddress->province_name,
                    'city_name' => $savedAddress->city_name,
                    'subdistrict_name' => $savedAddress->subdistrict_name,
                    'postal_code' => $savedAddress->postal_code,
                    'destination_id' => $savedAddress->destination_id,
                    'street_address' => $savedAddress->street_address,
                    'full_address' => $savedAddress->full_address,
                    'location_string' => $savedAddress->location_string,
                ];
            }
        }
        
        $addressData = [
            'address_id' => null,
            'label' => $request->address_label ?? 'Rumah',
            'recipient_name' => trim($request->recipient_name),
            'phone_recipient' => preg_replace('/[^0-9+\-\s\(\)]/', '', $request->phone_recipient),
            'province_name' => $request->province_name,
            'city_name' => $request->city_name,
            'subdistrict_name' => $request->subdistrict_name,
            'postal_code' => $request->postal_code,
            'destination_id' => $request->destination_id ?? null,
            'street_address' => trim($request->street_address),
        ];
        
        $addressData['full_address'] = $addressData['street_address'] . ', ' . 
                                      $addressData['subdistrict_name'] . ', ' . 
                                      $addressData['city_name'] . ', ' . 
                                      $addressData['province_name'] . ' ' . 
                                      $addressData['postal_code'];
        
        $addressData['location_string'] = $addressData['province_name'] . ', ' . 
                                         $addressData['city_name'] . ', ' . 
                                         $addressData['subdistrict_name'] . ', ' . 
                                         $addressData['postal_code'];
        
        if ($user && ($request->save_address ?? false)) {
            try {
                $newAddress = $user->addresses()->create([
                    'label' => $addressData['label'],
                    'recipient_name' => $addressData['recipient_name'],
                    'phone_recipient' => $addressData['phone_recipient'],
                    'province_name' => $addressData['province_name'],
                    'city_name' => $addressData['city_name'],
                    'subdistrict_name' => $addressData['subdistrict_name'],
                    'postal_code' => $addressData['postal_code'],
                    'destination_id' => $addressData['destination_id'],
                    'street_address' => $addressData['street_address'],
                    'is_primary' => false,
                    'is_active' => true,
                ]);
                
                if ($request->set_as_primary ?? false) {
                    $newAddress->setPrimary();
                }
                
                $addressData['address_id'] = $newAddress->id;
                
                Log::info('New address saved during checkout', [
                    'user_id' => $user->id,
                    'address_id' => $newAddress->id,
                    'label' => $newAddress->label,
                    'is_primary' => $newAddress->is_primary,
                ]);
                
            } catch (\Exception $e) {
                Log::warning('Failed to save address during checkout', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'address_data' => $addressData
                ]);
            }
        }
        
        return $addressData;
    }

    /**
     * Handle user account creation or update - keep same as working version
     */
    private function handleUserAccountCreationOrUpdate(Request $request)
    {
        $user = null;
        
        if ($request->create_account && !Auth::check()) {
            $existingUser = User::where('email', $request->email)->first();
            if ($existingUser) {
                throw new \Exception('Email already exists. Please login or use different email.');
            }

            $user = User::create([
                'name' => $request->first_name . ' ' . $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'email_verified_at' => now(),
                'password' => Hash::make($request->password),
                'gender' => $request->gender,
                'birthdate' => $request->birthdate,
            ]);

            Auth::login($user);

            Log::info('New user account created during checkout', [
                'user_id' => $user->id,
                'email' => $user->email,
                'gender' => $user->gender,
                'birthdate' => $user->birthdate,
                'phone' => $user->phone
            ]);
            
        } elseif (Auth::check()) {
            $user = Auth::user();
            
            $userModel = User::find($user->id);
            
            if ($userModel) {
                $updateData = [];
                
                if ($request->gender && (!$userModel->gender || $userModel->gender !== $request->gender)) {
                    $updateData['gender'] = $request->gender;
                }
                
                if ($request->birthdate && !$userModel->birthdate) {
                    $updateData['birthdate'] = $request->birthdate;
                }
                
                if ($request->phone && (!$userModel->phone || $userModel->phone !== $request->phone)) {
                    $updateData['phone'] = $request->phone;
                }
                
                if (!empty($updateData)) {
                    try {
                        $updateResult = $userModel->update($updateData);
                        
                        Log::info('Updated existing user data from checkout', [
                            'user_id' => $userModel->id,
                            'email' => $userModel->email,
                            'updated_fields' => array_keys($updateData),
                            'updated_data' => $updateData,
                            'update_result' => $updateResult
                        ]);
                        
                        $user = $userModel;
                        
                    } catch (\Exception $updateError) {
                        Log::error('Failed to update user data', [
                            'user_id' => $userModel->id,
                            'error' => $updateError->getMessage(),
                            'update_data' => $updateData
                        ]);
                    }
                }
            }
        }

        return $user;
    }

    /**
     * CRITICAL FIX: Create Midtrans payment with VOUCHER support that works
     */
private function createMidtransPayment($order, $cartItems, $request)
{
    try {
        Log::info('Creating Midtrans payment session with enhanced fallback', [
            'order_number' => $order->order_number,
            'total_amount' => $order->total_amount,
            'discount_amount' => $order->discount_amount ?? 0,
            'payment_method' => $request->payment_method,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip()
        ]);

        // Prepare item details (keep existing logic)
        $itemDetails = [];
        
        foreach ($cartItems as $item) {
            $itemDetails[] = [
                'id' => (string) $item['id'],
                'price' => (int) $item['price'],
                'quantity' => (int) $item['quantity'],
                'name' => substr($item['name'], 0, 50)
            ];
        }
        
        // Add shipping as item
        if ($order->shipping_cost > 0) {
            $shippingMethodName = 'Shipping Cost';
            
            if ($order->meta_data) {
                $metaData = json_decode($order->meta_data, true);
                if (isset($metaData['shipping_method'])) {
                    $shippingMethodName = 'Shipping - ' . substr($metaData['shipping_method'], 0, 30);
                }
            }
            
            $itemDetails[] = [
                'id' => 'shipping',
                'price' => (int) $order->shipping_cost,
                'quantity' => 1,
                'name' => $shippingMethodName
            ];
        }
        
        // Add voucher discount as negative item
        $discountAmount = (float) ($order->discount_amount ?? 0);
        if ($discountAmount > 0) {
            $discountName = 'Voucher Discount';
            
            if ($order->meta_data) {
                $metaData = json_decode($order->meta_data, true);
                if (isset($metaData['voucher_info']['voucher_code'])) {
                    $discountName = 'Voucher (' . $metaData['voucher_info']['voucher_code'] . ')';
                }
            }
            
            $itemDetails[] = [
                'id' => 'voucher_discount',
                'price' => -((int) $discountAmount),
                'quantity' => 1,
                'name' => $discountName
            ];
        }
        
        // Add points discount as negative item - NEW
        $pointsDiscount = 0;
        $pointsUsed = 0;
        if ($order->meta_data) {
            $metaData = json_decode($order->meta_data, true);
            $pointsDiscount = (float) ($metaData['points_info']['points_discount'] ?? 0);
            $pointsUsed = (int) ($metaData['points_info']['points_used'] ?? 0);
            
            if ($pointsDiscount > 0) {
                $itemDetails[] = [
                    'id' => 'points_discount',
                    'price' => -((int) $pointsDiscount),
                    'quantity' => 1,
                    'name' => 'Points Discount (' . number_format($pointsUsed, 0, ',', '.') . ' poin)'
                ];
                
                Log::info('Added points discount item to Midtrans payload', [
                    'points_used' => $pointsUsed,
                    'points_discount' => -((int) $pointsDiscount)
                ]);
            }
        }
        
        // Verification and adjustment (keep existing logic)
        $calculatedSum = 0;
        foreach ($itemDetails as $item) {
            $calculatedSum += $item['price'] * $item['quantity'];
        }
        
        $expectedTotal = (int) $order->total_amount;
        
        if ($calculatedSum !== $expectedTotal) {
            $difference = $expectedTotal - $calculatedSum;
            
            Log::warning('Midtrans amounts mismatch, adding adjustment', [
                'difference' => $difference,
                'calculated_sum' => $calculatedSum,
                'expected_total' => $expectedTotal,
                'voucher_discount' => $discountAmount,
                'points_discount' => $pointsDiscount
            ]);
            
            $itemDetails[] = [
                'id' => 'adjustment',
                'price' => $difference,
                'quantity' => 1,
                'name' => 'Price Adjustment'
            ];
        }

        // Customer details (keep existing logic)
        $customerDetails = [
            'first_name' => $request->first_name ?? explode(' ', $order->customer_name)[0],
            'last_name' => $request->last_name ?? (explode(' ', $order->customer_name, 2)[1] ?? ''),
            'email' => $request->email ?? $order->customer_email,
            'phone' => $request->phone ?? $order->customer_phone,
            'billing_address' => [
                'first_name' => $request->first_name ?? explode(' ', $order->customer_name)[0],
                'last_name' => $request->last_name ?? (explode(' ', $order->customer_name, 2)[1] ?? ''),
                'address' => $request->street_address ?? substr($order->shipping_address, 0, 200),
                'city' => substr($request->city_name ?? 'Jakarta', 0, 20),
                'postal_code' => $request->postal_code ?? '10000',
                'phone' => $request->phone ?? $order->customer_phone,
                'country_code' => 'IDN'
            ],
            'shipping_address' => [
                'first_name' => $request->first_name ?? explode(' ', $order->customer_name)[0],
                'last_name' => $request->last_name ?? (explode(' ', $order->customer_name, 2)[1] ?? ''),
                'address' => $request->street_address ?? substr($order->shipping_address, 0, 200),
                'city' => substr($request->city_name ?? 'Jakarta', 0, 20),
                'postal_code' => $request->postal_code ?? '10000',
                'phone' => $request->phone ?? $order->customer_phone,
                'country_code' => 'IDN'
            ]
        ];

        $transactionDetails = [
            'order_id' => $order->order_number,
            'gross_amount' => (int) $order->total_amount
        ];

        // Build payload
        $midtransPayload = [
            'transaction_details' => $transactionDetails,
            'customer_details' => $customerDetails,
            'item_details' => $itemDetails
        ];

        Log::info('Calling enhanced MidtransService', [
            'order_number' => $order->order_number,
            'gross_amount' => (int) $order->total_amount,
            'item_details_count' => count($itemDetails),
            'has_voucher_discount' => $discountAmount > 0,
            'has_points_discount' => $pointsDiscount > 0,
            'total_discounts' => $discountAmount + $pointsDiscount
        ]);

        // Use enhanced MidtransService
        $response = $this->midtransService->createSnapToken($midtransPayload);
        
        // ENHANCED: Handle new response format with network detection
        if (isset($response['success']) && $response['success'] && isset($response['token'])) {
            $preferHosted = $response['prefer_hosted'] ?? false;
            $networkInfo = $response['network_info'] ?? null;
            
            Log::info('Midtrans Snap token created successfully with enhanced features', [
                'order_number' => $order->order_number,
                'token_length' => strlen($response['token']),
                'total_discounts' => $discountAmount + $pointsDiscount,
                'prefer_hosted' => $preferHosted,
                'network_response_time' => $networkInfo['response_time_ms'] ?? 'unknown',
                'can_load_popup' => $networkInfo['can_load_popup'] ?? 'unknown'
            ]);

            return [
                'token' => $response['token'],
                'redirect_url' => $response['redirect_url'] ?? null,
                'prefer_hosted' => $preferHosted,
                'network_info' => $networkInfo,
                'force_hosted' => $preferHosted // Signal untuk frontend
            ];
            
        } elseif (isset($response['token'])) {
            // Backward compatibility - old response format
            Log::info('Midtrans Snap token created (legacy format)', [
                'order_number' => $order->order_number,
                'token_length' => strlen($response['token']),
                'total_discounts' => $discountAmount + $pointsDiscount
            ]);

            return [
                'token' => $response['token'],
                'redirect_url' => $response['redirect_url'] ?? null,
                'prefer_hosted' => false, // Default untuk legacy
                'force_hosted' => false
            ];
            
        } else {
            // Error in token creation
            $errorMessage = $response['error'] ?? 'Unknown error creating payment token';
            $preferHosted = $response['prefer_hosted'] ?? true;
            
            Log::error('MidtransService token creation failed', [
                'order_number' => $order->order_number,
                'error' => $errorMessage,
                'prefer_hosted' => $preferHosted,
                'full_response' => $response
            ]);
            
            // Return fallback info for hosted payment
            return [
                'error' => $errorMessage,
                'prefer_hosted' => $preferHosted,
                'force_hosted' => true,
                'fallback_url' => route('checkout.payment', ['orderNumber' => $order->order_number])
            ];
        }

    } catch (\Exception $e) {
        Log::error('Exception in enhanced Midtrans payment creation', [
            'order_number' => $order->order_number ?? 'unknown',
            'error' => $e->getMessage(),
            'error_line' => $e->getLine(),
            'error_file' => $e->getFile(),
            'trace' => $e->getTraceAsString()
        ]);
        
        // Return fallback for exceptions
        return [
            'error' => 'Payment system temporarily unavailable: ' . $e->getMessage(),
            'prefer_hosted' => true,
            'force_hosted' => true,
            'fallback_url' => route('checkout.payment', ['orderNumber' => $order->order_number ?? 'unknown'])
        ];
    }
}

    private function getOriginIdFromEnv()
    {
        $originCityName = env('STORE_ORIGIN_CITY_NAME', 'jakarta selatan');
        $originCityIdFallback = env('STORE_ORIGIN_CITY_ID', 158);
        
        Log::info('Getting origin from .env', [
            'configured_city_name' => $originCityName,
            'configured_city_id_fallback' => $originCityIdFallback
        ]);

        try {
            $response = Http::timeout(10)->withHeaders([
                'key' => $this->rajaOngkirApiKey
            ])->get($this->rajaOngkirBaseUrl . '/destination/domestic-destination', [
                'search' => strtolower($originCityName),
                'limit' => 1
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['data'][0])) {
                    $foundOrigin = $data['data'][0];
                    Log::info('Found origin via API search', [
                        'origin_id' => $foundOrigin['id'],
                        'origin_label' => $foundOrigin['label'],
                        'search_term' => $originCityName
                    ]);
                    return $foundOrigin['id'];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error searching origin city via API: ' . $e->getMessage());
        }
        
        Log::info('Using fallback origin ID from .env', [
            'fallback_origin_id' => $originCityIdFallback
        ]);
        
        return $originCityIdFallback;
    }



    private function getProvinces()
    {
        try {
            $response = Http::timeout(10)->withHeaders([
                'key' => $this->rajaOngkirApiKey
            ])->get($this->rajaOngkirBaseUrl . '/destination/province');

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['data']) && is_array($data['data'])) {
                    return array_map(function($province) {
                        return [
                            'province_id' => $province['id'],
                            'province' => $province['name']
                        ];
                    }, $data['data']);
                }
            }
        } catch (\Exception $e) {
            Log::error('RajaOngkir V2 provinces API error: ' . $e->getMessage());
        }

        return [];
    }

    private function getMajorCities()
    {
        $majorCityNames = ['jakarta', 'bandung', 'surabaya', 'medan', 'semarang', 'makassar'];
        $cities = [];
        
        foreach ($majorCityNames as $cityName) {
            try {
                $response = Http::timeout(10)->withHeaders([
                    'key' => $this->rajaOngkirApiKey
                ])->get($this->rajaOngkirBaseUrl . '/destination/domestic-destination', [
                    'search' => $cityName,
                    'limit' => 1
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['data'][0])) {
                        $location = $data['data'][0];
                        $cities[] = [
                            'name' => ucfirst($cityName),
                            'location_id' => $location['id'],
                            'label' => $location['label'],
                            'city_name' => $location['city_name'],
                            'province_name' => $location['province_name']
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::error("Error getting major city {$cityName}: " . $e->getMessage());
            }
        }

        return $cities;
    }

    // Keep ALL payment methods exactly the same as working version
    public function payment($orderNumber)
    {
        Log::info('Payment page accessed', ['order_number' => $orderNumber]);
        
        $order = Order::with('orderItems.product')
                     ->where('order_number', $orderNumber)
                     ->firstOrFail();
        
        if ($order->status === 'paid') {
            return redirect()->route('checkout.success', ['orderNumber' => $orderNumber]);
        }
        
        if ($order->payment_method === 'cod') {
            return redirect()->route('checkout.success', ['orderNumber' => $orderNumber]);
        }
        
        $snapToken = session('snap_token') ?: $order->snap_token;
        
        if (!$snapToken && $order->status === 'pending') {
            $cartItems = collect();
            foreach ($order->orderItems as $item) {
                $cartItems->push([
                    'id' => $item->product_id,
                    'name' => $item->product_name,
                    'price' => $item->product_price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->total_price
                ]);
            }
            
            $simulatedRequest = (object) [
                'first_name' => explode(' ', $order->customer_name)[0],
                'last_name' => explode(' ', $order->customer_name, 2)[1] ?? '',
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
                'address' => $order->shipping_address,
                'destination_label' => $order->shipping_destination_label,
                'postal_code' => $order->shipping_postal_code,
                'payment_method' => $order->payment_method
            ];
            
            $midtrans = $this->createMidtransPayment($order, $cartItems, $simulatedRequest);
            
            if ($midtrans && isset($midtrans['token'])) {
                $order->update(['snap_token' => $midtrans['token']]);
                $snapToken = $midtrans['token'];
            }
        }
        
        if (!$snapToken) {
            return redirect()->route('checkout.success', ['orderNumber' => $orderNumber])
                           ->with('error', 'Payment session expired. Please contact support.');
        }

        return view('frontend.checkout.payment', compact('order', 'snapToken'));
    }

public function paymentSuccess(Request $request)
{
    $orderNumber = $request->get('order_id');
    
    Log::info('PaymentSuccess callback accessed', [
        'order_id' => $orderNumber,
        'all_params' => $request->all()
    ]);
    
    if ($orderNumber) {
        // ✅ PERBAIKAN: JANGAN langsung update ke paid
        // Biarkan webhook yang handle update status
        
        return redirect()->route('checkout.success', ['orderNumber' => $orderNumber])
                       ->with('success', 'Payment completed! We are processing your order.');
    }
    
    return redirect()->route('home')->with('success', 'Payment completed successfully!');
}

public function paymentPending(Request $request)
{
    $orderNumber = $request->get('order_id');
    
    Log::info('PaymentPending callback accessed', [
        'order_id' => $orderNumber,
        'all_params' => $request->all()
    ]);
    
    if ($orderNumber) {
        // ✅ JANGAN update status, biarkan webhook yang handle
        
        return redirect()->route('checkout.success', ['orderNumber' => $orderNumber])
                       ->with('warning', 'Payment is being processed. You will receive confirmation shortly.');
    }
    
    return redirect()->route('home')->with('warning', 'Payment pending.');
}

    public function paymentError(Request $request)
{
    $orderNumber = $request->get('order_id');
    
    if ($orderNumber) {
        $order = Order::where('order_number', $orderNumber)->first();
        
        if ($order && $order->status === 'pending') {
            // Optionally update status to failed
            // $order->update(['status' => 'failed']);
            
            Log::warning('Payment failed via callback', [
                'order_number' => $orderNumber,
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'amount' => $order->total_amount
            ]);
        }
        
        return redirect()->route('checkout.index')
                       ->with('error', 'Payment failed. Please try again.');
    }
    
    return redirect()->route('home')->with('error', 'Payment failed.');
}

    public function paymentFinish(Request $request)
    {
        $orderNumber = $request->get('order_id');
        
        if ($orderNumber) {
            return redirect()->route('checkout.payment-success', ['order_id' => $orderNumber]);
        }
        
        return redirect()->route('home')->with('success', 'Payment completed successfully!');
    }

public function paymentUnfinish(Request $request)
{
    $orderNumber = $request->get('order_id');
    
    Log::info('PaymentUnfinish callback accessed', [
        'order_id' => $orderNumber,
        'all_params' => $request->all()
    ]);
    
    if ($orderNumber) {
        // ✅ JANGAN update status, biarkan webhook yang handle
        
        return redirect()->route('checkout.success', ['orderNumber' => $orderNumber])
                       ->with('warning', 'Payment was not completed. You can retry payment anytime from your order page.');
    }
    
    return redirect()->route('home')->with('warning', 'Payment was not completed.');
}

    public function getPaymentStatus($orderNumber)
    {
        try {
            $order = Order::where('order_number', $orderNumber)->first();
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'created_at' => $order->created_at,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error getting payment status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment status'
            ], 500);
        }
    }

    public function retryPayment($orderNumber)
    {
        try {
            $order = Order::where('order_number', $orderNumber)->first();
            
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }
            
            if ($order->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Order already paid'
                ], 400);
            }
            
            $cartItems = collect();
            foreach ($order->orderItems as $item) {
                $cartItems->push([
                    'id' => $item->product_id,
                    'name' => $item->product_name,
                    'price' => $item->product_price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->total_price
                ]);
            }
            
            $simulatedRequest = (object) [
                'first_name' => explode(' ', $order->customer_name)[0],
                'last_name' => explode(' ', $order->customer_name, 2)[1] ?? '',
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
                'address' => $order->shipping_address,
                'destination_label' => $order->shipping_destination_label,
                'postal_code' => $order->shipping_postal_code,
                'payment_method' => $order->payment_method
            ];
            
            $midtrans = $this->createMidtransPayment($order, $cartItems, $simulatedRequest);
            
            if ($midtrans && isset($midtrans['token'])) {
                $order->update(['snap_token' => $midtrans['token']]);
                
                return response()->json([
                    'success' => true,
                    'snap_token' => $midtrans['token'],
                    'order_number' => $order->order_number,
                    'message' => 'Payment session created successfully'
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment session'
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Error retrying payment: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry payment'
            ], 500);
        }
    }

    public function paymentNotification(Request $request)
{
    try {
        Log::info('=== MIDTRANS WEBHOOK RECEIVED ===', [
            'timestamp' => now()->toISOString(),
            'payload' => $request->all(),
            'order_id' => $request->get('order_id'),
            'transaction_status' => $request->get('transaction_status')
        ]);
        
        $notification = $this->midtransService->handleNotification($request->all());
        
        if (!$notification) {
            return response()->json([
                'status' => 'failed', 
                'message' => 'Invalid notification'
            ], 400);
        }

        $order = Order::where('order_number', $notification['order_id'])->first();
        
        if (!$order) {
            return response()->json([
                'status' => 'success', 
                'message' => 'Order not found but notification received'
            ]);
        }

        $oldStatus = $order->status;
        $newStatus = $this->mapMidtransToOrderStatus(
            $notification['payment_status'] ?? 'unknown',
            $notification['transaction_status'] ?? 'unknown',
            $notification['fraud_status'] ?? 'accept'
        );
        
        Log::info('Order status mapping result', [
            'order_id' => $notification['order_id'],
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'payment_status' => $notification['payment_status'] ?? 'unknown',
            'transaction_status' => $notification['transaction_status'] ?? 'unknown'
        ]);
        
        // Update order status
        $order->update([
            'status' => $newStatus,
            'payment_response' => json_encode($notification['raw_notification'] ?? $request->all())
        ]);
        
        // JIKA STATUS BERUBAH DARI PENDING KE PAID, KURANGI STOK DAN KIRIM KE GINEE
        if ($oldStatus !== 'paid' && $newStatus === 'paid') {
            try {
                Log::info('Calling processStockReductionForPaidOrder', [
                    'order_id' => $order->order_number
                ]);
                
                $this->processStockReductionForPaidOrder($order);
                
                Log::info('Successfully reduced stock for paid order', [
                    'order_id' => $order->order_number
                ]);
            } catch (\Exception $e) {
                Log::error('Error in processStockReductionForPaidOrder', [
                    'order_id' => $order->order_number,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // JIKA STATUS BERUBAH KE CANCELLED, KEMBALIKAN STOK
        if ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
            try {
                Log::info('Calling processStockRestoreForCancelledOrder', [
                    'order_id' => $order->order_number
                ]);
                
                $this->processStockRestoreForCancelledOrder($order);
                
                Log::info('Successfully restored stock for cancelled order', [
                    'order_id' => $order->order_number
                ]);
            } catch (\Exception $e) {
                Log::error('Error in processStockRestoreForCancelledOrder', [
                    'order_id' => $order->order_number,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        Log::info('Order status updated', [
            'order_number' => $notification['order_id'],
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Notification processed successfully'
        ]);
        
    } catch (\Exception $e) {
        Log::error('Webhook processing error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'status' => 'error', 
            'message' => 'Processing failed'
        ], 200);
    }
}

    private function mapMidtransToOrderStatus($paymentStatus, $transactionStatus, $fraudStatus = 'accept')
    {
        if ($fraudStatus === 'challenge') {
            return 'pending';
        }
        
        if ($fraudStatus === 'deny') {
            return 'cancelled';
        }

        switch ($paymentStatus) {
            case 'paid':
                return 'paid';
            case 'pending':
                return 'pending';
            case 'failed':
            case 'cancelled':
                return 'cancelled';
            case 'refunded':
                return 'refund';
            case 'challenge':
                return 'pending';
            default:
                return 'pending';
        }
        
    }
public function success($orderNumber)
{
    Log::info('Success page accessed', ['order_number' => $orderNumber]);
    
    $order = Order::with('orderItems.product')
                 ->where('order_number', $orderNumber)
                 ->firstOrFail();

    Log::info('Order found for success page', [
        'order_number' => $orderNumber,
        'order_status' => $order->status,
        'payment_method' => $order->payment_method
    ]);

    // ✅ REDIRECT TO ORDER DETAILS PAGE INSTEAD OF SHOWING SUCCESS PAGE
    return redirect()->route('orders.show', ['orderNumber' => $orderNumber])
                   ->with('success', 'Order confirmed! Your order details are shown below.');
}
private function calculateOrderPoints(Order $order)
{
    try {
        $user = $order->user;
        if (!$user) {
            return null;
        }
        
        $pointsEarned = 0;
        $pointsPercentage = 1; // Default 1%
        $userTier = 'basic';
        $tierLabel = 'Basic Member';
        
        // Get user tier and points percentage
        if (method_exists($user, 'getCustomerTier')) {
            $userTier = $user->getCustomerTier();
        }
        
        if (method_exists($user, 'getCustomerTierLabel')) {
            $tierLabel = $user->getCustomerTierLabel();
        }
        
        if (method_exists($user, 'getPointsPercentage')) {
            $pointsPercentage = $user->getPointsPercentage();
        }
        
        // Calculate points earned
        if (method_exists($user, 'calculatePointsFromPurchase')) {
            $pointsEarned = $user->calculatePointsFromPurchase($order->total_amount);
        } else {
            // Fallback calculation
            $pointsEarned = round(($order->total_amount * $pointsPercentage) / 100, 2);
        }
        
        return [
            'points_earned' => $pointsEarned,
            'points_percentage' => $pointsPercentage,
            'user_tier' => $userTier,
            'tier_label' => $tierLabel,
            'order_amount' => $order->total_amount,
            'calculation_text' => "Rp " . number_format($order->total_amount, 0, ',', '.') . " × {$pointsPercentage}% = " . number_format($pointsEarned, 0, ',', '.') . " points"
        ];
        
    } catch (\Exception $e) {
        Log::error('Error calculating order points', [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'error' => $e->getMessage()
        ]);
        
        return null;
    }
}

/**
 * Helper method untuk filtering di CheckoutController
 */
private function filterCheckoutJNEServices($shippingOptions)
{
    $excludedServices = ['CTCSPS', 'JTR<130', 'JTR>130', 'JTR>200'];
    
    $filtered = array_filter($shippingOptions, function($option) use ($excludedServices) {
        if (!isset($option['service'])) {
            return true;
        }
        
        $service = trim($option['service']);
        
        // Skip excluded services
        if (in_array($service, $excludedServices)) {
            Log::info("🚫 Checkout filtering out JNE service: {$service}");
            return false;
        }
        
        // Skip JTR pattern services
        if (preg_match('/^JTR[<>]?\d+$/', $service)) {
            Log::info("🚫 Checkout filtering out JTR service: {$service}");
            return false;
        }
        
        return true;
    });
    
    return array_values($filtered); // Re-index array
}

}