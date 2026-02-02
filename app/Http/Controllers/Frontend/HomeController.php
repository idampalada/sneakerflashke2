<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    public function index()
    {
        // ⭐ STEP 1: Get active banners
        $banners = Banner::active()
            ->orderBy('sort_order')
            ->get();

        // ⭐ STEP 2: Get all active products first (before any filtering)
        $allActiveProducts = Product::query()
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->with('category')
            ->get();

        // ⭐ STEP 3: Group products by sku_parent to eliminate duplicates
        $groupedProducts = $this->groupProductsBySkuParent($allActiveProducts);

        Log::info('Homepage products grouped', [
            'original_count' => $allActiveProducts->count(),
            'grouped_count' => $groupedProducts->count()
        ]);

        // ⭐ STEP 4: Get featured products from grouped collection
        $featuredProducts = $groupedProducts->where('is_featured', true)->take(8);

        // If no featured products, get any active products from grouped collection
        if ($featuredProducts->isEmpty()) {
            $featuredProducts = $groupedProducts->take(8);
        }

        // ⭐ STEP 5: Get latest products from grouped collection
        $latestProducts = $groupedProducts->sortByDesc('created_at')->take(12);

        // ⭐ STEP 6: Get active categories (unchanged)
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->take(6)
            ->get();

        return view('frontend.home', compact('banners', 'featuredProducts', 'latestProducts', 'categories'));
    }

    /**
     * ⭐ SAME LOGIC AS ProductController: Group products by sku_parent
     * This ensures one product represents all size variants
     */
    private function groupProductsBySkuParent($products)
    {
        // Group by sku_parent (same product, different sizes)
        return $products->groupBy('sku_parent')->map(function ($productGroup, $skuParent) {
            
            // Skip products without sku_parent (treat as individual products)
            if (empty($skuParent)) {
                return $productGroup->map(function ($product) {
                    // ⭐ KEEP AS ELOQUENT OBJECT, just add extra properties
                    $product->size_variants = collect([]);
                    $product->total_stock = $product->stock_quantity ?? 0;
                    $product->has_multiple_sizes = false;
                    
                    // ⭐ CLEAN: Remove SKU parent from product name
                    $product->name = $this->cleanProductName($product->name, $product->sku_parent);
                    
                    return $product; // Keep as Eloquent object
                });
            }
            
            // Get the first product as representative (main product info)
            $representativeProduct = $productGroup->first();
            
            // ⭐ KEY FIX: Create size variants from all products with same sku_parent but different SKU
            $sizeVariants = $productGroup->map(function ($product) {
                // Extract size from SKU pattern (SBKVN0A3HZFCAR-S -> S)
                $sizeFromSku = $this->extractSizeFromSku($product->sku, $product->sku_parent);
                
                // ⭐ FIXED: Safe size handling
                $productSize = null;
                if (is_array($product->available_sizes) && !empty($product->available_sizes)) {
                    $productSize = $product->available_sizes[0];
                } elseif (is_string($product->available_sizes) && !empty($product->available_sizes)) {
                    $productSize = $product->available_sizes;
                }
                
                return [
                    'id' => $product->id,
                    'size' => $productSize ?: $sizeFromSku ?: 'One Size',
                    'stock' => $product->stock_quantity ?? 0,
                    'sku' => $product->sku,
                    'price' => $product->sale_price ?: $product->price,
                    'original_price' => $product->price,
                    'available' => ($product->stock_quantity ?? 0) > 0
                ];
            })->sortBy('size');
            
            // Calculate total stock from all variants
            $totalStock = $sizeVariants->sum('stock');
            
            // ⭐ KEEP AS ELOQUENT OBJECT: Add extra properties to representative product
            $representativeProduct->size_variants = $sizeVariants;
            $representativeProduct->total_stock = $totalStock;
            $representativeProduct->has_multiple_sizes = $sizeVariants->count() > 1;
            
            // ⭐ CLEAN: Remove SKU parent from product name
            $representativeProduct->name = $this->cleanProductName($representativeProduct->name, $representativeProduct->sku_parent);
            
            
            // Return single enhanced product representing the group (as Eloquent object)
            return collect([$representativeProduct]);
            
        })->flatten(1)->values(); // Flatten to get single collection of products
    }

    /**
     * ⭐ CLEAN: Remove SKU parent and size patterns from product name
     */
    private function cleanProductName($originalName, $skuParent)
    {
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
        
        return trim($cleanProductName, ' -');
    }

    /**
     * ⭐ SAME AS ProductController: Extract size from SKU pattern
     */
    private function extractSizeFromSku($sku, $skuParent)
    {
        // Pattern: SBKVN0A3HZFCAR-S -> extract "S"
        // Pattern: SBKVN0A3HZFCAR-M -> extract "M"
        
        if (empty($sku) || empty($skuParent)) {
            return null;
        }
        
        // Find the size part after the last dash
        $parts = explode('-', $sku);
        if (count($parts) >= 2) {
            $sizePart = end($parts);
            
            // Common size patterns
            if (in_array(strtoupper($sizePart), ['S', 'M', 'L', 'XL', 'XXL', 'XS'])) {
                return strtoupper($sizePart);
            }
            
            // Shoe sizes (numeric with optional .5)
            if (is_numeric($sizePart) || preg_match('/^\d+\.?5?$/', $sizePart)) {
                return $sizePart;
            }
        }
        
        return null;
    }
}