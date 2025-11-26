<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlackFridayProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_type',
        'brand',
        'related_product',
        'name',
        'description',
        'price',
        'original_price',
        'discount_percentage',
        'sku',
        'sku_parent',
        'stock_quantity',
        'is_active',
        'is_featured',
        'gender_target',
        'available_sizes',
        'available_colors',
        'image_urls',
        'featured_image',
        'slug',
        'meta_title',
        'meta_description',
        'sale_start_date',
        'sale_end_date',
        'is_flash_sale',
        'limited_stock',
    ];

    protected $casts = [
        'gender_target' => 'array',
        'available_sizes' => 'array',
        'available_colors' => 'array',
        'image_urls' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_flash_sale' => 'boolean',
        'price' => 'decimal:2',
        'original_price' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'sale_start_date' => 'datetime',
        'sale_end_date' => 'datetime',
    ];

    // Boot method to auto-generate slug
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
                
                // Ensure unique slug
                $originalSlug = $product->slug;
                $counter = 1;
                while (static::where('slug', $product->slug)->exists()) {
                    $product->slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }
        });
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOnSale($query)
    {
        $now = now();
        return $query->where('is_active', true)
                    ->where(function ($q) use ($now) {
                        $q->whereNull('sale_start_date')
                          ->orWhere('sale_start_date', '<=', $now);
                    })
                    ->where(function ($q) use ($now) {
                        $q->whereNull('sale_end_date')
                          ->orWhere('sale_end_date', '>=', $now);
                    });
    }

    public function scopeByBrand($query, $brand)
    {
        return $query->where('brand', $brand);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('product_type', $type);
    }

    public function scopeByGender($query, $gender)
    {
        return $query->whereJsonContains('gender_target', $gender);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', "%{$search}%")
              ->orWhere('description', 'ilike', "%{$search}%")
              ->orWhere('brand', 'ilike', "%{$search}%")
              ->orWhere('sku', 'ilike', "%{$search}%")
              ->orWhere('sku_parent', 'ilike', "%{$search}%");
        });
    }

    public function scopeFlashSale($query)
    {
        return $query->where('is_flash_sale', true)->onSale();
    }

    // Accessors
    public function getFormattedPriceAttribute()
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }

    public function getFormattedOriginalPriceAttribute()
    {
        return $this->original_price ? 'Rp ' . number_format($this->original_price, 0, ',', '.') : null;
    }

    public function getDiscountAmountAttribute()
    {
        if ($this->original_price && $this->original_price > $this->price) {
            return $this->original_price - $this->price;
        }
        return 0;
    }

    public function getCalculatedDiscountPercentageAttribute()
    {
        if ($this->original_price && $this->original_price > $this->price) {
            return round((($this->original_price - $this->price) / $this->original_price) * 100);
        }
        return 0;
    }

    public function getIsSaleActiveAttribute()
    {
        $now = now();
        
        $startOk = !$this->sale_start_date || $this->sale_start_date <= $now;
        $endOk = !$this->sale_end_date || $this->sale_end_date >= $now;
        
        return $this->is_active && $startOk && $endOk;
    }

    public function getIsInStockAttribute()
    {
        return $this->stock_quantity > 0;
    }

    // Helper methods
    public function hasSize(string $size): bool
    {
        $availableSizes = $this->available_sizes;
        return is_array($availableSizes) && in_array($size, $availableSizes);
    }

    public function hasColor(string $color): bool
    {
        $availableColors = $this->available_colors;
        if (!is_array($availableColors)) {
            return false;
        }
        
        return in_array(strtolower($color), array_map('strtolower', $availableColors));
    }

    public function getFirstImageAttribute()
    {
        if (is_array($this->image_urls) && count($this->image_urls) > 0) {
            return $this->image_urls[0];
        }
        return $this->featured_image ?: '/images/placeholder-product.jpg';
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

    // Meta attributes
    public function getMetaTitleAttribute($value)
    {
        return $value ?: $this->name . ' - Black Friday Sale - SneakerFlash';
    }

    public function getMetaDescriptionAttribute($value)
    {
        return $value ?: Str::limit(strip_tags($this->description), 160);
    }
}