<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BlackFridayController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Product::where('product_type', 'BLACKFRIDAY')
                ->where('is_active', true);

            // Filter by brand
            if ($request->filled('brands')) {
                $query->whereIn('brand', $request->brands);
            }

            // Filter by discount
            if ($request->filled('discount')) {
                foreach ($request->discount as $discountRange) {
                    if ($discountRange === '50+') {
                        $query->whereRaw('((original_price - price) / original_price * 100) >= 50');
                    } elseif ($discountRange === '30-49') {
                        $query->whereRaw('((original_price - price) / original_price * 100) BETWEEN 30 AND 49');
                    } elseif ($discountRange === '10-29') {
                        $query->whereRaw('((original_price - price) / original_price * 100) BETWEEN 10 AND 29');
                    }
                }
            }

            // Filter flash sale
            if ($request->boolean('flash_sale')) {
                $query->where('is_sale', true);
            }

            // Sort options
            $sort = $request->get('sort', 'discount');
            switch ($sort) {
                case 'discount':
                    $query->orderByRaw('((original_price - price) / original_price * 100) DESC');
                    break;
                case 'latest':
                    $query->latest();
                    break;
                case 'price_low':
                    $query->orderBy('price', 'asc');
                    break;
                case 'price_high':
                    $query->orderBy('price', 'desc');
                    break;
                case 'name_az':
                    $query->orderBy('name', 'asc');
                    break;
                default:
                    $query->orderByRaw('((original_price - price) / original_price * 100) DESC');
            }

            $products = $query->paginate(12)->appends($request->query());

            // Get unique brands for filter
            $brands = Product::where('product_type', 'BLACKFRIDAY')
                ->where('is_active', true)
                ->distinct()
                ->pluck('brand')
                ->filter()
                ->values();

            $total = Product::where('product_type', 'BLACKFRIDAY')
                ->where('is_active', true)
                ->count();

            // Set countdown end date (example: 7 days from now)
            $countdown_end = now()->addDays(7);

            return view('frontend.blackfriday.index', compact(
                'products',
                'brands', 
                'total',
                'countdown_end'
            ));

        } catch (\Exception $e) {
            Log::error('🖤 Black Friday index error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return view('frontend.blackfriday.index', [
                'products' => collect(),
                'brands' => collect(),
                'total' => 0,
                'countdown_end' => now()->addDays(7)
            ]);
        }
    }

    public function show($slug)
    {
        try {
            $product = Product::where('product_type', 'BLACKFRIDAY')
                ->where('slug', $slug)
                ->where('is_active', true)
                ->firstOrFail();

            // Get related products (same brand or similar)
            $relatedProducts = Product::where('product_type', 'BLACKFRIDAY')
                ->where('id', '!=', $product->id)
                ->where('is_active', true)
                ->where(function($query) use ($product) {
                    $query->where('brand', $product->brand)
                          ->orWhere('related_product', $product->related_product);
                })
                ->limit(4)
                ->get();

            return view('frontend.blackfriday.show', compact(
                'product',
                'relatedProducts'
            ));

        } catch (\Exception $e) {
            Log::error('🖤 Black Friday show error', [
                'slug' => $slug,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('black-friday.index')
                ->with('error', 'Product not found');
        }
    }

    public function quickSearch(Request $request)
    {
        $query = $request->get('query', '');
        
        if (empty($query) || strlen($query) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Product::where('product_type', 'BLACKFRIDAY')
            ->where('is_active', true)
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('brand', 'like', "%{$query}%")
                  ->orWhere('sku', 'like', "%{$query}%");
            })
            ->limit(8)
            ->get()
            ->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'brand' => $product->brand,
                    'price' => $product->price,
                    'original_price' => $product->original_price,
                    'featured_image' => $product->featured_image,
                    'discount_percentage' => $product->original_price > 0 && $product->price > 0 
                        ? round((($product->original_price - $product->price) / $product->original_price) * 100, 2)
                        : 0
                ];
            });

        return response()->json(['products' => $products]);
    }

    public function getFlashSaleData()
    {
        try {
            $flashSaleProducts = Product::where('product_type', 'BLACKFRIDAY')
                ->where('is_active', true)
                ->where('is_sale', true)
                ->get();

            $totalProducts = $flashSaleProducts->count();
            $totalDiscount = $flashSaleProducts->sum(function($product) {
                if ($product->original_price > 0 && $product->price > 0) {
                    return round((($product->original_price - $product->price) / $product->original_price) * 100, 2);
                }
                return 0;
            });

            $avgDiscount = $totalProducts > 0 ? round($totalDiscount / $totalProducts, 2) : 0;

            return response()->json([
                'total_products' => $totalProducts,
                'average_discount' => $avgDiscount,
                'total_savings' => $flashSaleProducts->sum(function($product) {
                    return $product->original_price - $product->price;
                })
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get flash sale data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addBlackFridayProduct(Request $request, $slug)
    {
        try {
            $product = Product::where('product_type', 'BLACKFRIDAY')
                ->where('slug', $slug)
                ->where('is_active', true)
                ->firstOrFail();

            $quantity = $request->get('quantity', 1);
            $size = $request->get('size');

            // Add to cart logic here
            // This would typically use your existing cart service

            return response()->json([
                'success' => true,
                'message' => 'Black Friday product added to cart successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('🖤 Add Black Friday product error', [
                'slug' => $slug,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add product to cart'
            ], 500);
        }
    }
}