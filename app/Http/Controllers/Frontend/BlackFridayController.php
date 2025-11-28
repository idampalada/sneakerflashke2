<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ⭐ FIXED BlackFridayController - Added grouping to prevent duplicates like ADIDAS ADIMATIC ATMOS GLORY PURPLE
 */
class BlackFridayController extends Controller
{
    /**
     * ⭐ FIXED: Display Black Friday products with proper grouping to prevent duplicates
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

            // Get all products BEFORE grouping
            $allProducts = $query->get();

            // ⭐ CRITICAL FIX: Group products to eliminate duplicates (same as ProductController)
            $groupedProducts = $this->groupBlackFridayProductsBySkuParent($allProducts);

            Log::info('Black Friday products grouped', [
                'original_count' => $allProducts->count(),
                'grouped_count' => $groupedProducts->count(),
                'filters' => $request->all()
            ]);

            // Apply sorting to grouped collection
            $groupedProducts = $this->applySortingToBlackFridayProducts($groupedProducts, $request);

            // Manual pagination (since grouping breaks Laravel paginator)
            $perPage = 12;
            $currentPage = $request->get('page', 1);
            $offset = ($currentPage - 1) * $perPage;
            $paginatedProducts = $groupedProducts->slice($offset, $perPage)->values();

            // Create pagination object
            $products = new \Illuminate\Pagination\LengthAwarePaginator(
                $paginatedProducts,
                $groupedProducts->count(),
                $perPage,
                $currentPage,
                ['path' => request()->url(), 'pageName' => 'page']
            );
            $products->appends($request->query());

            // Process products for display
            $products->getCollection()->transform(function ($product) {
                return $this->processBlackFridayProductForDisplay($product);
            });

            // Get unique brands for filter (from original products)
            $brands = $allProducts->pluck('brand')->filter()->unique()->sort()->values();

            // Get total count
            $total = $groupedProducts->count();

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
     * ⭐ NEW: Group Black Friday products by sku_parent to eliminate duplicates
     * Same logic as ProductController but adapted for Black Friday
     */
    private function groupBlackFridayProductsBySkuParent($products)
    {
        // Group by sku_parent (same product, different sizes)
        $grouped = $products->groupBy(function($product) {
            return $product->sku_parent ?? $product->sku ?? $product->id;
        });

        return $grouped->map(function ($productGroup, $groupKey) {
            // If only one product in group, return as is
            if ($productGroup->count() === 1) {
                $product = $productGroup->first();
                $product->size_variants = collect([]);
                $product->total_stock = $product->stock_quantity ?? 0;
                $product->has_multiple_sizes = false;
                return $product;
            }

            // Multiple products = size variants
            // Use the first product as the representative
            $representativeProduct = $productGroup->first();
            
            // Create size variants from all products in group
            $sizeVariants = $productGroup->map(function ($product) {
                // Extract size from various sources
                $size = $this->extractBlackFridayProductSize($product);
                
                return [
                    'id' => $product->id,
                    'size' => $size,
                    'stock' => $product->stock_quantity ?? 0,
                    'sku' => $product->sku,
                    'price' => $product->sale_price ?? $product->price,
                    'original_price' => $product->original_price ?? $product->price,
                    'available' => ($product->stock_quantity ?? 0) > 0,
                    'slug' => $product->slug
                ];
            })->sortBy('size')->values();

            // Calculate total stock
            $totalStock = $sizeVariants->sum('stock');

            // Enhance representative product
            $representativeProduct->size_variants = $sizeVariants;
            $representativeProduct->total_stock = $totalStock;
            $representativeProduct->has_multiple_sizes = $sizeVariants->count() > 1;
            
            // Clean product name (remove SKU parent pattern)
            $representativeProduct->name = $this->cleanBlackFridayProductName($representativeProduct->name, $representativeProduct->sku_parent);

            Log::info('Black Friday grouped product', [
                'group_key' => $groupKey,
                'sku_parent' => $representativeProduct->sku_parent,
                'product_name' => $representativeProduct->name,
                'variants_count' => $sizeVariants->count(),
                'sizes' => $sizeVariants->pluck('size')->toArray(),
                'total_stock' => $totalStock
            ]);

            return $representativeProduct;
        })->values();
    }

    /**
     * ⭐ Extract size from Black Friday product using multiple strategies
     */
    private function extractBlackFridayProductSize($product)
    {
        // Strategy 1: From available_sizes field
        if (!empty($product->available_sizes)) {
            if (is_array($product->available_sizes)) {
                return $product->available_sizes[0] ?? 'One Size';
            } elseif (is_string($product->available_sizes)) {
                // Try to decode JSON
                $decoded = json_decode($product->available_sizes, true);
                if (is_array($decoded) && !empty($decoded)) {
                    return $decoded[0];
                }
                return $product->available_sizes;
            }
        }

        // Strategy 2: Extract from SKU pattern
        if (!empty($product->sku) && !empty($product->sku_parent)) {
            $extractedSize = $this->extractSizeFromSku($product->sku, $product->sku_parent);
            if ($extractedSize) {
                return $extractedSize;
            }
        }

        // Strategy 3: Extract from product name
        if (preg_match('/\b(Size\s+)?([XS|S|M|L|XL|XXL|XXXL|\d{2,3}(?:\.\d)?)\b/i', $product->name, $matches)) {
            return trim($matches[2]);
        }

        return 'One Size';
    }

    /**
     * Extract size from SKU pattern like "PARENT-SIZE"
     */
    private function extractSizeFromSku($sku, $skuParent)
    {
        if (empty($sku) || empty($skuParent)) {
            return null;
        }

        // Remove parent SKU to get the suffix
        $suffix = str_replace($skuParent, '', $sku);
        $suffix = trim($suffix, '-_');
        
        // If suffix looks like a size, return it
        if (preg_match('/^[XS|S|M|L|XL|XXL|XXXL|\d{2,3}(?:\.\d)?]?$/i', $suffix) && !empty($suffix)) {
            return strtoupper($suffix);
        }

        return null;
    }

    /**
     * Clean product name by removing SKU parent patterns
     */
    private function cleanBlackFridayProductName($originalName, $skuParent)
    {
        $cleanName = $originalName;
        
        if (!empty($skuParent)) {
            // Remove SKU parent pattern like "- VN0A3HZFCAR"
            $cleanName = preg_replace('/\s*-\s*' . preg_quote($skuParent, '/') . '\s*/', '', $cleanName);
            $cleanName = preg_replace('/\s*' . preg_quote($skuParent, '/') . '\s*/', '', $cleanName);
        }
        
        // Remove size patterns like "- Size M", "Size L", etc.
        $cleanName = preg_replace('/\s*-\s*Size\s+[A-Z0-9.]+\s*$/i', '', $cleanName);
        $cleanName = preg_replace('/\s*Size\s+[A-Z0-9.]+\s*$/i', '', $cleanName);
        $cleanName = preg_replace('/\s*-\s*[A-Z0-9.]+\s*$/i', '', $cleanName);
        
        return trim($cleanName, ' -');
    }

    /**
     * Apply sorting to grouped Black Friday products collection
     */
    private function applySortingToBlackFridayProducts($products, Request $request)
    {
        $sort = $request->get('sort', 'latest');
        
        switch ($sort) {
            case 'discount':
                return $products->sortByDesc(function ($product) {
                    $original = $product->original_price ?? $product->price;
                    $final = $product->sale_price ?? $product->price;
                    
                    if ($original > $final) {
                        return (($original - $final) / $original) * 100;
                    }
                    return 0;
                });
                
            case 'price_low':
                return $products->sortBy(function ($product) {
                    return $product->sale_price ?? $product->price;
                });
                
            case 'price_high':
                return $products->sortByDesc(function ($product) {
                    return $product->sale_price ?? $product->price;
                });
                
            case 'name_az':
                return $products->sortBy('name');
                
            default: // latest
                return $products->sortByDesc('created_at');
        }
    }

    /**
     * Process product for display with proper pricing hierarchy
     */
    private function processBlackFridayProductForDisplay($product)
    {
        // Price hierarchy: original_price > price > sale_price (what customer pays)
        $originalPrice = $product->original_price ?? null;
        $currentPrice = $product->price ?? 0;
        $salePrice = $product->sale_price ?? null;
        
        // Determine final price (what customer actually pays)
        $finalPrice = $currentPrice;
        if ($salePrice && $salePrice < $currentPrice) {
            $finalPrice = $salePrice;
        }
        
        $product->final_price = $finalPrice;
        
        // Calculate discount percentage properly
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
            if (is_array($images) && !empty($images)) {
                $product->first_image = filter_var($images[0], FILTER_VALIDATE_URL) 
                    ? $images[0] 
                    : asset('storage/' . ltrim($images[0], '/'));
            }
        }
        
        // Ensure first_image fallback
        if (empty($product->first_image)) {
            $product->first_image = asset('images/product-placeholder.png');
        }
        
        // Calculate stock
        $totalStock = $product->total_stock ?? $product->stock_quantity ?? 0;
        
        // Format pricing for display
        $product->formatted_price = 'Rp ' . number_format($finalPrice, 0, ',', '.');
        
        if ($displayOriginalPrice) {
            $product->formatted_original_price = 'Rp ' . number_format($displayOriginalPrice, 0, ',', '.');
            $product->discount_amount = $displayOriginalPrice - $finalPrice;
        } else {
            $product->formatted_original_price = null;
            $product->discount_amount = 0;
        }
        
        return $product;
    }

    /**
     * Show individual Black Friday product (keeping existing logic)
     */
    public function show(Request $request, $slug)
    {
        try {
            $product = Product::where('product_type', 'BLACKFRIDAY')
                ->where('slug', $slug)
                ->where('is_active', true)
                ->firstOrFail();

            // Get size variants properly
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
                    
                    // Get size from available_sizes JSON and clean it
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

            // Calculate pricing with proper hierarchy
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

            // Countdown end time
            $countdown_end = now()->addDays(7);

            return view('frontend.blackfriday.show', compact(
                'product', 
                'sizeVariants', 
                'imageUrls',
                'discountPercentage',
                'countdown_end'
            ));

        } catch (\Exception $e) {
            Log::error('🖤 Black Friday show error', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            abort(404, 'Black Friday product not found');
        }
    }

    /**
     * Quick search for Black Friday products (keeping existing logic)
     */
    public function quickSearch(Request $request)
    {
        $search = $request->get('query', '');
        
        if (empty($search) || strlen($search) < 2) {
            return response()->json(['products' => []]);
        }

        $products = Product::where('product_type', 'BLACKFRIDAY')
            ->where('is_active', true)
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('brand', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
            })
            ->select(['id', 'name', 'slug', 'price', 'sale_price', 'images', 'brand'])
            ->limit(8)
            ->get();

        $formattedProducts = $products->map(function ($product) {
            $finalPrice = $product->sale_price ?? $product->price;
            
            $image = null;
            if ($product->images) {
                $images = is_string($product->images) ? json_decode($product->images, true) : $product->images;
                if (is_array($images) && !empty($images)) {
                    $image = filter_var($images[0], FILTER_VALIDATE_URL) 
                        ? $images[0] 
                        : asset('storage/' . ltrim($images[0], '/'));
                }
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'brand' => $product->brand,
                'price' => 'Rp ' . number_format($finalPrice, 0, ',', '.'),
                'image' => $image,
                'url' => route('black-friday.show', $product->slug)
            ];
        });

        return response()->json(['products' => $formattedProducts]);
    }

    /**
     * Get flash sale data (keeping existing logic)
     */
    public function getFlashSaleData()
    {
        $flashSaleProducts = Product::where('product_type', 'BLACKFRIDAY')
            ->where('is_active', true)
            ->where('is_flash_sale', true)
            ->limit(10)
            ->get();

        return response()->json([
            'flash_sale_products' => $flashSaleProducts,
            'countdown_end' => now()->addDays(7)->toISOString()
        ]);
    }

    /**
     * Clean size string
     */
    private function cleanSize($size)
    {
        if (empty($size)) return null;
        
        // Remove common prefixes and clean
        $cleaned = str_replace(['Size ', 'size ', 'SIZE '], '', trim($size));
        $cleaned = trim($cleaned, ' -_');
        
        return !empty($cleaned) ? strtoupper($cleaned) : null;
    }
}