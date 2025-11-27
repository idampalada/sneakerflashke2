<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * BlackFridayController - Fixed to properly handle size variants and pricing display
 */
class BlackFridayController extends Controller
{
    /**
     * Display Black Friday products index page
     */
    public function index(Request $request)
    {
        try {
            // Base query for Black Friday products only
            $query = Product::where('product_type', 'BLACKFRIDAY')
                ->where('is_active', true);

            // Search functionality
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('brand', 'like', "%{$searchTerm}%")
                      ->orWhere('description', 'like', "%{$searchTerm}%")
                      ->orWhere('sku', 'like', "%{$searchTerm}%");
                });
            }

            // Brand filter
            if ($request->filled('brand')) {
                $query->where('brand', $request->brand);
            }

            // Price range filter
            if ($request->filled('price_range')) {
                $priceRange = explode('-', $request->price_range);
                if (count($priceRange) === 2) {
                    $minPrice = (int) $priceRange[0];
                    $maxPrice = (int) $priceRange[1];
                    
                    $query->where(function ($q) use ($minPrice, $maxPrice) {
                        $q->whereBetween('price', [$minPrice, $maxPrice])
                          ->orWhereBetween('sale_price', [$minPrice, $maxPrice]);
                    });
                }
            }

            // Sort options
            $sort = $request->get('sort', '');
            switch ($sort) {
                case 'discount':
                    $query->orderByRaw('CASE WHEN original_price > 0 AND sale_price > 0 THEN ((original_price - sale_price) / original_price * 100) ELSE 0 END DESC');
                    break;
                case 'price_low':
                    $query->orderByRaw('COALESCE(sale_price, price) ASC');
                    break;
                case 'price_high':
                    $query->orderByRaw('COALESCE(sale_price, price) DESC');
                    break;
                case 'name_az':
                    $query->orderBy('name', 'asc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }

            // Get products with pagination
            $products = $query->paginate(12)->appends($request->query());

            // ⭐ FIXED: Process products with proper pricing hierarchy
            $products->getCollection()->transform(function ($product) {
                // ⭐ PRICE HIERARCHY: original_price > price > sale_price (what customer pays)
                $originalPrice = $product->original_price ?? null;
                $currentPrice = $product->price ?? 0;
                $salePrice = $product->sale_price ?? null;
                
                // Determine final price (what customer actually pays)
                $finalPrice = $currentPrice;
                if ($salePrice && $salePrice < $currentPrice) {
                    $finalPrice = $salePrice;
                }
                
                $product->final_price = $finalPrice;
                
                // ⭐ FIXED: Calculate discount percentage properly
                $discountPercentage = 0;
                $displayOriginalPrice = null;
                
                if ($originalPrice && $originalPrice > $finalPrice) {
                    // Use original_price as base for discount
                    $discountPercentage = round((($originalPrice - $finalPrice) / $originalPrice) * 100);
                    $displayOriginalPrice = $originalPrice;
                } elseif (!$originalPrice && $salePrice && $salePrice < $currentPrice) {
                    // Fallback: use current price as base
                    $discountPercentage = round((($currentPrice - $salePrice) / $currentPrice) * 100);
                    $displayOriginalPrice = $currentPrice;
                }
                
                $product->calculated_discount_percentage = $discountPercentage;
                $product->display_original_price = $displayOriginalPrice;
                
                // Format main image
                if ($product->featured_image) {
                    $product->first_image = filter_var($product->featured_image, FILTER_VALIDATE_URL) 
                        ? $product->featured_image 
                        : asset('storage/' . ltrim($product->featured_image, '/'));
                } elseif ($product->images) {
                    $images = is_string($product->images) ? json_decode($product->images, true) : $product->images;
                    if (is_array($images) && count($images) > 0) {
                        $imageUrl = $images[0];
                        $product->first_image = filter_var($imageUrl, FILTER_VALIDATE_URL) 
                            ? $imageUrl 
                            : asset('storage/' . ltrim($imageUrl, '/'));
                    } else {
                        $product->first_image = asset('images/default-product.jpg');
                    }
                } else {
                    $product->first_image = asset('images/default-product.jpg');
                }
                
                // Check if has variants using sku_parent
                $hasVariants = false;
                if (!empty($product->sku_parent)) {
                    $variantCount = Product::where('sku_parent', $product->sku_parent)
                        ->where('product_type', 'BLACKFRIDAY')
                        ->where('is_active', true)
                        ->count();
                    $hasVariants = $variantCount > 1;
                }
                $product->has_variants = $hasVariants;
                
                // Get aggregated sizes from available_sizes JSON
                if ($hasVariants && !empty($product->sku_parent)) {
                    $variants = Product::where('sku_parent', $product->sku_parent)
                        ->where('product_type', 'BLACKFRIDAY')
                        ->where('is_active', true)
                        ->where('stock_quantity', '>', 0)
                        ->get();
                    
                    $sizes = [];
                    foreach ($variants as $variant) {
                        if ($variant->available_sizes) {
                            $variantSizes = is_string($variant->available_sizes) 
                                ? json_decode($variant->available_sizes, true) 
                                : $variant->available_sizes;
                            
                            if (is_array($variantSizes)) {
                                // ⭐ CLEAN SIZE: Remove backslashes and JSON formatting
                                foreach ($variantSizes as $size) {
                                    $cleanSize = $this->cleanSize($size);
                                    if (!empty($cleanSize)) {
                                        $sizes[] = $cleanSize;
                                    }
                                }
                            }
                        }
                    }
                    
                    $product->aggregated_sizes = array_values(array_unique(array_filter($sizes)));
                    
                    // Calculate total stock for variants
                    $product->total_stock = $variants->sum('stock_quantity');
                } else {
                    $product->aggregated_sizes = [];
                    $product->total_stock = $product->stock_quantity ?? 0;
                }
                
                // ⭐ FIXED: Format pricing for display - match products page exactly
                $product->formatted_price = 'Rp ' . number_format($finalPrice, 0, ',', '.');
                
                if ($displayOriginalPrice) {
                    $product->formatted_original_price = 'Rp ' . number_format($displayOriginalPrice, 0, ',', '.');
                    $product->discount_amount = $displayOriginalPrice - $finalPrice;
                } else {
                    $product->formatted_original_price = null;
                    $product->discount_amount = 0;
                }
                
                return $product;
            });

            // Get unique brands for filter
            $brands = Product::where('product_type', 'BLACKFRIDAY')
                ->where('is_active', true)
                ->distinct()
                ->pluck('brand')
                ->filter()
                ->values();

            // Get total count
            $total = Product::where('product_type', 'BLACKFRIDAY')
                ->where('is_active', true)
                ->count();

            // Set countdown end date
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

            // Return empty results on error
            $emptyProducts = new \Illuminate\Pagination\LengthAwarePaginator(
                collect(), 0, 12, 1, ['path' => request()->url()]
            );

            return view('frontend.blackfriday.index', [
                'products' => $emptyProducts,
                'brands' => collect(),
                'total' => 0,
                'countdown_end' => now()->addDays(7)
            ]);
        }
    }

    /**
     * Show individual Black Friday product
     */
    public function show(Request $request, $slug)
    {
        try {
            $product = Product::where('product_type', 'BLACKFRIDAY')
                ->where('slug', $slug)
                ->where('is_active', true)
                ->firstOrFail();

            // ⭐ FIXED: Get ALL size variants properly
            $sizeVariants = collect();
            
            if (!empty($product->sku_parent)) {
                $variants = Product::where('sku_parent', $product->sku_parent)
                    ->where('product_type', 'BLACKFRIDAY')
                    ->where('is_active', true)
                    ->get();

                foreach ($variants as $variant) {
                    $finalPrice = ($variant->sale_price && $variant->sale_price < $variant->price) 
                        ? $variant->sale_price 
                        : $variant->price;
                    
                    // ⭐ FIXED: Get size from available_sizes JSON and clean it
                    $sizes = [];
                    if ($variant->available_sizes) {
                        $variantSizes = is_string($variant->available_sizes) 
                            ? json_decode($variant->available_sizes, true) 
                            : $variant->available_sizes;
                        
                        if (is_array($variantSizes)) {
                            foreach ($variantSizes as $size) {
                                $cleanSize = $this->cleanSize($size);
                                if (!empty($cleanSize)) {
                                    $sizes[] = $cleanSize;
                                }
                            }
                        }
                    }
                    
                    // Add each size as separate variant
                    foreach ($sizes as $size) {
                        $sizeVariants->push([
                            'id' => $variant->id,
                            'size' => $size,
                            'stock' => $variant->stock_quantity ?? 0,
                            'price' => $finalPrice,
                            'original_price' => $variant->original_price ?? $variant->price,
                            'sku' => $variant->sku,
                            'available' => ($variant->stock_quantity ?? 0) > 0
                        ]);
                    }
                }
                
                // Sort by size and remove duplicates
                $sizeVariants = $sizeVariants->sortBy('size')->unique('size')->values();
            }

            // ⭐ FIXED: Calculate pricing with proper hierarchy
            $originalPrice = $product->original_price ?? null;
            $currentPrice = $product->price ?? 0;
            $salePrice = $product->sale_price ?? null;
            
            $finalPrice = $currentPrice;
            if ($salePrice && $salePrice < $currentPrice) {
                $finalPrice = $salePrice;
            }
            
            $discountPercentage = 0;
            if ($originalPrice && $originalPrice > $finalPrice) {
                $discountPercentage = round((($originalPrice - $finalPrice) / $originalPrice) * 100);
            } elseif (!$originalPrice && $salePrice && $salePrice < $currentPrice) {
                $discountPercentage = round((($currentPrice - $salePrice) / $currentPrice) * 100);
            }

            // Process product images
            $imageUrls = [];
            if ($product->images) {
                $images = is_string($product->images) ? json_decode($product->images, true) : $product->images;
                if (is_array($images)) {
                    foreach ($images as $url) {
                        $imageUrls[] = filter_var($url, FILTER_VALIDATE_URL) 
                            ? $url 
                            : asset('storage/' . ltrim($url, '/'));
                    }
                }
            }
            
            if (empty($imageUrls) && $product->featured_image) {
                $imageUrls[] = filter_var($product->featured_image, FILTER_VALIDATE_URL) 
                    ? $product->featured_image 
                    : asset('storage/' . ltrim($product->featured_image, '/'));
            }
            
            if (empty($imageUrls)) {
                $imageUrls[] = asset('images/default-product.jpg');
            }

            // Add calculated fields to product
            $product->image_urls = $imageUrls;
            $product->first_image = $imageUrls[0];
            $product->final_price = $finalPrice;
            $product->calculated_discount_percentage = $discountPercentage;
            $product->formatted_price = 'Rp ' . number_format($finalPrice, 0, ',', '.');
            
            // Set display original price
            if ($originalPrice && $originalPrice > $finalPrice) {
                $product->formatted_original_price = 'Rp ' . number_format($originalPrice, 0, ',', '.');
                $product->discount_amount = $originalPrice - $finalPrice;
            } elseif (!$originalPrice && $salePrice && $salePrice < $currentPrice) {
                $product->formatted_original_price = 'Rp ' . number_format($currentPrice, 0, ',', '.');
                $product->discount_amount = $currentPrice - $salePrice;
            } else {
                $product->formatted_original_price = null;
                $product->discount_amount = 0;
            }

            // Get related products
            $relatedProducts = Product::where('product_type', 'BLACKFRIDAY')
                ->where('id', '!=', $product->id)
                ->where('is_active', true)
                ->where(function ($query) use ($product) {
                    $query->where('brand', $product->brand)
                          ->orWhere('category_id', $product->category_id);
                })
                ->limit(4)
                ->get();

            // Format related products pricing
            $relatedProducts->transform(function ($related) {
                $finalPrice = ($related->sale_price && $related->sale_price < $related->price) 
                    ? $related->sale_price 
                    : $related->price;
                $related->final_price = $finalPrice;
                $related->formatted_price = 'Rp ' . number_format($finalPrice, 0, ',', '.');
                
                // Format image
                if ($related->featured_image) {
                    $related->first_image = filter_var($related->featured_image, FILTER_VALIDATE_URL) 
                        ? $related->featured_image 
                        : asset('storage/' . ltrim($related->featured_image, '/'));
                } else {
                    $related->first_image = asset('images/default-product.jpg');
                }
                
                // Calculate discount
                if ($related->original_price && $related->original_price > $finalPrice) {
                    $related->calculated_discount_percentage = round((($related->original_price - $finalPrice) / $related->original_price) * 100);
                } else {
                    $related->calculated_discount_percentage = 0;
                }
                
                return $related;
            });

            return view('frontend.blackfriday.show', compact(
                'product',
                'sizeVariants',
                'relatedProducts'
            ));

        } catch (\Exception $e) {
            Log::error('🖤 Black Friday show error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            abort(404);
        }
    }

    /**
     * ⭐ FIXED: Clean size display - remove backslashes and JSON formatting
     */
    private function cleanSize($size)
    {
        if (empty($size)) {
            return '';
        }
        
        // Convert to string first
        $cleanSize = (string) $size;
        
        // Remove JSON formatting and backslashes
        $cleanSize = trim($cleanSize, '"\'');
        $cleanSize = str_replace(['[', ']', '"', '\\'], '', $cleanSize);
        $cleanSize = preg_replace('/[\x00-\x1F\x7F]/', '', $cleanSize); // Remove control characters
        
        // Remove extra spaces and trim
        $cleanSize = trim($cleanSize);
        
        return $cleanSize;
    }

    /**
     * Quick search API for Black Friday products
     */
    public function quickSearch(Request $request)
    {
        try {
            $query = $request->get('q', '');
            
            if (empty($query)) {
                return response()->json(['products' => []]);
            }

            $products = Product::where('product_type', 'BLACKFRIDAY')
                ->where('is_active', true)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('brand', 'like', "%{$query}%");
                })
                ->limit(10)
                ->get();

            $results = $products->map(function ($product) {
                $finalPrice = ($product->sale_price && $product->sale_price < $product->price) 
                    ? $product->sale_price 
                    : $product->price;
                
                $imageUrl = $product->featured_image 
                    ? (filter_var($product->featured_image, FILTER_VALIDATE_URL) 
                        ? $product->featured_image 
                        : asset('storage/' . $product->featured_image))
                    : asset('images/default-product.jpg');

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'brand' => $product->brand,
                    'price' => $finalPrice,
                    'formatted_price' => 'Rp ' . number_format($finalPrice, 0, ',', '.'),
                    'image' => $imageUrl,
                    'url' => route('black-friday.show', $product->slug),
                    'in_stock' => ($product->stock_quantity ?? 0) > 0
                ];
            });

            return response()->json(['products' => $results]);

        } catch (\Exception $e) {
            Log::error('🖤 Black Friday quick search error', [
                'query' => $request->get('q'),
                'error' => $e->getMessage()
            ]);

            return response()->json(['products' => []], 500);
        }
    }

    /**
     * Get flash sale data for countdown timers
     */
    public function getFlashSaleData(Request $request)
    {
        try {
            $flashSaleProducts = Product::where('product_type', 'BLACKFRIDAY')
                ->where('is_active', true)
                ->where('is_flash_sale', true)
                ->where('sale_end_date', '>', now())
                ->limit(5)
                ->get();

            $data = $flashSaleProducts->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'end_date' => $product->sale_end_date ? $product->sale_end_date->toISOString() : null,
                    'stock' => $product->stock_quantity ?? 0
                ];
            });

            return response()->json([
                'success' => true,
                'flash_sales' => $data,
                'countdown_end' => now()->addDays(7)->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('🖤 Black Friday flash sale data error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'flash_sales' => [],
                'countdown_end' => now()->addDays(7)->toISOString()
            ], 500);
        }
    }
}