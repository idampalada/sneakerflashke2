<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail; // TAMBAHKAN INI
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\Log;

/**
 * App\Models\User
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Wishlist> $wishlists
 * @property-read int|null $wishlists_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\UserAddress> $addresses
 * @property-read int|null $addresses_count
 * @method \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Wishlist> wishlists()
 * @method \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\UserAddress> addresses()
 * @method int getWishlistCount()
 * @method bool hasInWishlist(int $productId)
 * @method bool toggleWishlist(int $productId)
 * @method bool addToWishlist(int $productId)
 * @method int removeFromWishlist(int $productId)
 */
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
            'first_name',    // TAMBAHKAN INI
    'last_name',     // TAMBAHKAN INI
        'email',
        'password',
        'phone',
        'gender',
        'birthdate',
        'google_id',
        'avatar',
        'email_verified_at',
        'total_spent',           // EXISTING - Stored spending
        'total_orders',          // EXISTING - Stored order count
        'spending_updated_at',   // EXISTING - Last sync timestamp
        'customer_tier',         // UPDATED - Now supports basic/advance/ultimate
        // NEW FIELDS FOR TIER & POINTS SYSTEM
        'spending_6_months',     // NEW - Spending in last 6 months
        'tier_period_start',     // NEW - Start of current tier evaluation period
        'last_tier_evaluation',  // NEW - Last time tier was evaluated
        'points_balance',        // NEW - Current available points
        'total_points_earned',   // NEW - Total points earned lifetime
        'total_points_redeemed', // NEW - Total points redeemed lifetime
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birthdate' => 'date',
            'spending_updated_at' => 'datetime',    // EXISTING
            'tier_period_start' => 'datetime',      // NEW
            'last_tier_evaluation' => 'datetime',   // NEW
            'total_spent' => 'decimal:2',           // EXISTING
            'spending_6_months' => 'decimal:2',     // NEW
            'points_balance' => 'decimal:2',        // NEW
            'total_points_earned' => 'decimal:2',   // NEW
            'total_points_redeemed' => 'decimal:2', // NEW
            'password' => 'hashed',
        ];
    }

    /**
     * Determine if the user can access the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->email, [
            'adminsf@sneakersflash.com',
        ]);
    }

    // =====================================
    // POSTGRESQL SPECIFIC SCOPES
    // =====================================
    
    public function scopeGoogleUsers($query)
    {
        return $query->whereNotNull('google_id');
    }

    public function scopeRegularUsers($query)
    {
        return $query->whereNull('google_id');
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    // =====================================
    // SPENDING-BASED SCOPES - UPDATED
    // =====================================

    public function scopeHighValueCustomers($query)
    {
        return $query->where('spending_6_months', '>=', 5000000); // Updated to use 6-month spending
    }

    public function scopeByTier($query, $tier)
    {
        // Use stored column for super fast queries
        return $query->where('customer_tier', $tier);
    }

    public function scopeTopSpenders($query, $limit = 10)
    {
        return $query->where('total_spent', '>', 0)
                    ->orderBy('total_spent', 'desc')
                    ->limit($limit);
    }

    public function scopeFrequentBuyers($query)
    {
        return $query->where('total_orders', '>=', 5);
    }

    public function scopeSpendingRange($query, $min = null, $max = null)
    {
        if ($min !== null) {
            $query->where('total_spent', '>=', $min);
        }
        if ($max !== null) {
            $query->where('total_spent', '<=', $max);
        }
        return $query;
    }

    // NEW SCOPES FOR TIER SYSTEM
    public function scopeBasicTier($query)
    {
        return $query->where('customer_tier', 'basic');
    }

    public function scopeAdvanceTier($query)
    {
        return $query->where('customer_tier', 'advance');
    }

    public function scopeUltimateTier($query)
    {
        return $query->where('customer_tier', 'ultimate');
    }

    public function scopeWithPoints($query)
    {
        return $query->where('points_balance', '>', 0);
    }

    // =====================================
    // E-COMMERCE RELATIONSHIPS
    // =====================================

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(ShoppingCart::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function couponUsage(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    // NEW RELATIONSHIP FOR POINTS
    public function pointsTransactions(): HasMany
    {
        return $this->hasMany(PointsTransaction::class);
    }

    // =====================================
    // ADDRESS RELATIONSHIPS & METHODS - UNCHANGED
    // =====================================

    /**
     * Get user addresses
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class)->orderBy('is_primary', 'desc')->orderBy('created_at', 'desc');
    }

    /**
     * Get primary address
     */
    public function primaryAddress(): HasOne
    {
        return $this->hasOne(UserAddress::class)->where('is_primary', true)->where('is_active', true);
    }

    /**
     * Get active addresses only
     */
    public function activeAddresses(): HasMany
    {
        return $this->hasMany(UserAddress::class)->where('is_active', true)->orderBy('is_primary', 'desc')->orderBy('created_at', 'desc');
    }

    /**
     * Check if user has addresses
     */
    public function hasAddresses(): bool
    {
        return $this->addresses()->where('is_active', true)->exists();
    }

    /**
     * Check if user has primary address
     */
    public function hasPrimaryAddress(): bool
    {
        return $this->primaryAddress()->exists();
    }

    /**
     * Get formatted address count
     */
    public function getAddressCountAttribute(): int
    {
        return $this->addresses()->where('is_active', true)->count();
    }

    /**
     * Get primary address or first available address
     */
    public function getDefaultAddressAttribute()
    {
        return $this->primaryAddress ?: $this->addresses()->where('is_active', true)->first();
    }

    // =====================================
    // WISHLIST RELATIONSHIPS & METHODS - UNCHANGED
    // =====================================

    /**
     * Get all of the wishlists for the User
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\Wishlist>
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    /**
     * Many-to-many relationship with products through wishlists
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\Product>
     */
    public function wishlistProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'wishlists')
                    ->withTimestamps()
                    ->orderBy('wishlists.created_at', 'desc');
    }

    /**
     * Get wishlist count for header badge
     *
     * @return int
     */
    public function getWishlistCount(): int
    {
        return $this->wishlists()->count();
    }

    /**
     * Check if product is in user's wishlist
     *
     * @param int $productId
     * @return bool
     */
    public function hasInWishlist(int $productId): bool
    {
        return $this->wishlists()->where('product_id', $productId)->exists();
    }

    /**
     * Add product to wishlist
     *
     * @param int $productId
     * @return \App\Models\Wishlist|false
     */
    public function addToWishlist(int $productId)
    {
        if (!$this->hasInWishlist($productId)) {
            return $this->wishlists()->create(['product_id' => $productId]);
        }
        return false;
    }

    /**
     * Remove product from wishlist
     *
     * @param int $productId
     * @return int
     */
    public function removeFromWishlist(int $productId): int
    {
        return $this->wishlists()->where('product_id', $productId)->delete();
    }

    /**
     * Toggle product in wishlist (add if not exists, remove if exists)
     *
     * @param int $productId
     * @return bool True if added, false if removed
     */
    public function toggleWishlist(int $productId): bool
    {
        if ($this->hasInWishlist($productId)) {
            $this->removeFromWishlist($productId);
            return false; // Removed
        } else {
            $this->addToWishlist($productId);
            return true; // Added
        }
    }

    /**
     * Get all wishlist product IDs for this user
     *
     * @return array<int>
     */
    public function getWishlistProductIds(): array
    {
        return $this->wishlists()->pluck('product_id')->toArray();
    }

    // =====================================
    // ACCESSORS - UNCHANGED
    // =====================================

    public function getAvatarUrlAttribute()
    {
        if ($this->avatar) {
            return $this->avatar;
        }
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=150";
    }

    public function getIsGoogleUserAttribute()
    {
        return !is_null($this->google_id);
    }

    // =====================================
    // CART HELPER METHODS - UNCHANGED
    // =====================================

    /**
     * Get cart items count
     */
    public function getCartCount()
    {
        return $this->cartItems()
            ->whereHas('product', function ($query) {
                $query->where('is_active', true)
                      ->where('stock_quantity', '>', 0);
            })
            ->sum('quantity');
    }

    /**
     * Get cart total amount
     */
    public function getCartTotal()
    {
        return $this->cartItems()
            ->whereHas('product', function ($query) {
                $query->where('is_active', true)
                      ->where('stock_quantity', '>', 0);
            })
            ->get()
            ->sum(function ($item) {
                $price = $item->product->sale_price ?? $item->product->price;
                return $price * $item->quantity;
            });
    }

    // =====================================
    // NEW TIER SYSTEM - BASIC/ADVANCE/ULTIMATE
    // =====================================

    /**
     * Valid statuses that count as "paid/completed" orders for revenue calculation
     */
    private array $paidStatuses = ['paid', 'processing', 'shipped', 'delivered'];

    /**
     * Calculate tier from 6-month spending amount (NEW LOGIC)
     */
    private function calculateTierFromSpending($spending6Months)
    {
        if ($spending6Months >= 10000000) return 'ultimate';    // 10 juta IDR dalam 6 bulan
        if ($spending6Months >= 5000000) return 'advance';     // 5 juta IDR dalam 6 bulan
        return 'basic';
    }

    /**
     * Get spending in the last 6 months from paid orders
     */
    public function getSpending6Months()
    {
        $sixMonthsAgo = now()->subMonths(6);
        
        return $this->orders()
                    ->whereIn('status', $this->paidStatuses)
                    ->where('created_at', '>=', $sixMonthsAgo)
                    ->sum('total_amount');
    }

    /**
     * Evaluate and update customer tier based on 6-month spending
     */
    public function evaluateCustomerTier()
    {
        $spending6Months = $this->getSpending6Months();
        $newTier = $this->calculateTierFromSpending($spending6Months);
        $oldTier = $this->customer_tier;
        
        $this->update([
            'spending_6_months' => $spending6Months,
            'customer_tier' => $newTier,
            'last_tier_evaluation' => now()
        ]);
        
        // Initialize tier period if not set
        if (!$this->tier_period_start) {
            $this->update(['tier_period_start' => now()->subMonths(6)]);
        }
        
        Log::info('Customer tier evaluated', [
            'user_id' => $this->id,
            'old_tier' => $oldTier,
            'new_tier' => $newTier,
            'spending_6_months' => $spending6Months,
            'tier_changed' => $oldTier !== $newTier
        ]);
        
        return $this;
    }

    /**
     * Get customer tier (current stored value)
     */
    public function getCustomerTier()
    {
        return $this->customer_tier ?: 'basic';
    }

    /**
     * Get customer tier display label (UPDATED)
     */
    public function getCustomerTierLabel()
    {
        return match($this->getCustomerTier()) {
            'ultimate' => 'Ultimate Member',
            'advance' => 'Advance Member',
            'basic' => 'Basic Member',
            // Legacy support for old tiers
            'platinum' => 'Ultimate Member',
            'gold' => 'Advance Member',
            'silver' => 'Basic Member',
            'bronze' => 'Basic Member',
            'new' => 'Basic Member',
            default => 'Basic Member'
        };
    }

    /**
     * Get customer tier color for UI badges (UPDATED)
     */
    public function getCustomerTierColor()
    {
        return match($this->getCustomerTier()) {
            'ultimate' => '#8B5CF6',    // Purple
            'advance' => '#3B82F6',     // Blue  
            'basic' => '#6B7280',       // Gray
            // Legacy support for old tiers
            'platinum' => '#8B5CF6',    // Purple
            'gold' => '#3B82F6',        // Blue
            'silver' => '#6B7280',      // Gray
            'bronze' => '#6B7280',      // Gray
            'new' => '#6B7280',         // Gray
            default => '#6B7280'
        };
    }

    /**
     * Get tier requirements for next level (UPDATED)
     */
    public function getNextTierRequirement()
    {
        $currentSpending6Months = $this->spending_6_months ?? 0;
        
        return match($this->getCustomerTier()) {
            'basic' => [
                'tier' => 'Advance Member', 
                'required' => 5000000, 
                'remaining' => max(0, 5000000 - $currentSpending6Months),
                'period' => '6 months'
            ],
            'advance' => [
                'tier' => 'Ultimate Member', 
                'required' => 10000000, 
                'remaining' => max(0, 10000000 - $currentSpending6Months),
                'period' => '6 months'
            ],
            'ultimate' => [
                'tier' => 'Ultimate Member', 
                'required' => 10000000, 
                'remaining' => 0,
                'period' => '6 months'
            ],
            // Legacy tier support
            'new', 'bronze', 'silver' => [
                'tier' => 'Advance Member', 
                'required' => 5000000, 
                'remaining' => max(0, 5000000 - $currentSpending6Months),
                'period' => '6 months'
            ],
            'gold', 'platinum' => [
                'tier' => 'Ultimate Member', 
                'required' => 10000000, 
                'remaining' => 0,
                'period' => '6 months'
            ],
        };
    }

    /**
     * Check if tier evaluation is needed (monthly check)
     */
    public function needsTierEvaluation()
    {
        if (!$this->last_tier_evaluation) {
            return true;
        }
        
        return $this->last_tier_evaluation->diffInDays(now()) >= 30;
    }

    // =====================================
    // POINTS SYSTEM - NEW
    // =====================================

    /**
     * Get points percentage based on tier
     */
    public function getPointsPercentage()
    {
        return match($this->getCustomerTier()) {
            'ultimate' => 5.0,     // 5%
            'advance' => 2.5,      // 2.5%
            'basic' => 1.0,        // 1%
            // Legacy tier support
            'platinum' => 5.0,
            'gold' => 2.5,
            'silver' => 1.0,
            'bronze' => 1.0,
            'new' => 1.0,
            default => 1.0
        };
    }

    /**
     * Calculate points from purchase amount
     */
    public function calculatePointsFromPurchase($amount)
    {
        $percentage = $this->getPointsPercentage();
        return round(($amount * $percentage) / 100, 2);
    }

    /**
     * Add points from purchase
     */
    public function addPointsFromPurchase($orderAmount)
    {
        $pointsEarned = $this->calculatePointsFromPurchase($orderAmount);
        
        $this->increment('points_balance', $pointsEarned);
        $this->increment('total_points_earned', $pointsEarned);
        
        Log::info('Points earned from purchase', [
            'user_id' => $this->id,
            'order_amount' => $orderAmount,
            'tier' => $this->getCustomerTier(),
            'points_percentage' => $this->getPointsPercentage(),
            'points_earned' => $pointsEarned,
            'new_balance' => $this->points_balance
        ]);
        
        return $pointsEarned;
    }

    /**
     * Redeem points
     */
    public function redeemPoints($amount)
    {
        if ($amount > $this->points_balance) {
            throw new \Exception('Insufficient points balance');
        }
        
        $this->decrement('points_balance', $amount);
        $this->increment('total_points_redeemed', $amount);
        
        Log::info('Points redeemed', [
            'user_id' => $this->id,
            'points_redeemed' => $amount,
            'remaining_balance' => $this->points_balance
        ]);
        
        return $this;
    }

    /**
     * Get formatted points balance
     */
    public function getFormattedPointsBalance()
    {
        return number_format($this->points_balance ?? 0, 0, ',', '.');
    }

    // =====================================
    // SPENDING METHODS - UPDATED
    // =====================================

    /**
     * Update spending statistics from orders table (UPDATED)
     */
    public function updateSpendingStats()
    {
        // Calculate total spent (all time)
        $totalSpent = $this->orders()->whereIn('status', $this->paidStatuses)->sum('total_amount');
        $totalOrders = $this->orders()->whereIn('status', $this->paidStatuses)->count();
        
        $this->update([
            'total_spent' => $totalSpent,
            'total_orders' => $totalOrders,
            'spending_updated_at' => now()
        ]);
        
        // Also evaluate tier based on 6-month spending
        $this->evaluateCustomerTier();
        
        Log::info('Updated spending stats for user', [
            'user_id' => $this->id,
            'total_spent' => $totalSpent,
            'total_orders' => $totalOrders,
            'customer_tier' => $this->customer_tier,
            'paid_statuses' => $this->paidStatuses
        ]);
        
        return $this;
    }

    /**
     * Get total amount spent by user
     */
    public function getTotalSpent($useStored = true)
    {
        if ($useStored) {
            return $this->total_spent ?? 0;
        }
        return $this->orders()->whereIn('status', $this->paidStatuses)->sum('total_amount');
    }

    /**
     * Get total completed orders count
     */
    public function getTotalOrders($useStored = true)
    {
        if ($useStored) {
            return $this->total_orders ?? 0;
        }
        return $this->orders()->whereIn('status', $this->paidStatuses)->count();
    }

    /**
     * Get all orders count (including pending)
     */
    public function getOrdersCount()
    {
        return $this->orders()->count();
    }

    /**
     * Get pending orders count
     */
    public function getPendingOrdersCount()
    {
        return $this->orders()->whereIn('status', ['pending', 'processing'])->count();
    }

    /**
     * Get average order value
     */
    public function getAverageOrderValue($useStored = true)
    {
        $totalSpent = $this->getTotalSpent($useStored);
        $totalOrders = $this->getTotalOrders($useStored);
        
        return $totalOrders > 0 ? $totalSpent / $totalOrders : 0;
    }

    /**
     * Get last order date (from completed orders only)
     */
    public function getLastOrderDate()
    {
        return $this->orders()
                    ->whereIn('status', $this->paidStatuses)
                    ->latest('created_at')
                    ->value('created_at');
    }

    // =====================================
    // REAL-TIME SPENDING ANALYSIS - UNCHANGED
    // =====================================

    /**
     * Get spending this month (real-time) - include all paid statuses
     */
    public function getSpendingThisMonth()
    {
        return $this->orders()
                    ->whereIn('status', $this->paidStatuses)
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('total_amount');
    }

    /**
     * Get spending this year (real-time) - include all paid statuses
     */
    public function getSpendingThisYear()
    {
        return $this->orders()
                    ->whereIn('status', $this->paidStatuses)
                    ->whereYear('created_at', now()->year)
                    ->sum('total_amount');
    }

    /**
     * Get spending in a specific period - include all paid statuses
     */
    public function getSpendingInPeriod($month = null, $year = null)
    {
        $query = $this->orders()->whereIn('status', $this->paidStatuses);
        
        if ($month) {
            $query->whereMonth('created_at', $month);
        }
        
        if ($year) {
            $query->whereYear('created_at', $year);
        }
        
        return $query->sum('total_amount');
    }

    /**
     * Get monthly spending for the current year (for charts)
     */
    public function getMonthlySpending($year = null)
    {
        $year = $year ?? now()->year;
        $monthly = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $monthly[$month] = $this->getSpendingInPeriod($month, $year);
        }
        
        return $monthly;
    }

    // =====================================
    // PROFILE COMPLETION METHODS - UNCHANGED
    // =====================================

    /**
     * Check if profile is complete (including address)
     */
    public function isProfileComplete(): bool
    {
        // Check basic profile fields
        $basicComplete = !empty($this->name) && 
                        !empty($this->email) && 
                        !empty($this->phone);
        
        // Check if has at least one address
        $hasAddress = $this->hasAddresses();
        
        return $basicComplete && $hasAddress;
    }

    /**
     * Get profile completion percentage
     */
    public function getProfileCompletionPercentage(): int
    {
        $fields = [
            'name' => !empty($this->name),
            'email' => !empty($this->email),
            'phone' => !empty($this->phone),
            'gender' => !empty($this->gender),
            'birthdate' => !empty($this->birthdate),
            'address' => $this->hasAddresses()
        ];
        
        $completedFields = array_filter($fields);
        
        return round((count($completedFields) / count($fields)) * 100);
    }

    /**
     * Get missing profile fields
     */
    public function getMissingProfileFields(): array
    {
        $missing = [];
        
        if (empty($this->name)) $missing[] = 'Name';
        if (empty($this->email)) $missing[] = 'Email';
        if (empty($this->phone)) $missing[] = 'Phone';
        if (empty($this->gender)) $missing[] = 'Gender';
        if (empty($this->birthdate)) $missing[] = 'Birth Date';
        if (!$this->hasAddresses()) $missing[] = 'Address';
        
        return $missing;
    }

    // =====================================
    // CUSTOMER CLASSIFICATION - UPDATED
    // =====================================

    /**
     * Check if user is a high-value customer (UPDATED to use 6-month spending)
     */
    public function isHighValueCustomer()
    {
        return ($this->spending_6_months ?? 0) >= 5000000; // 5 juta dalam 6 bulan
    }

    /**
     * Check if user is a frequent buyer (fast query using stored column)
     */
    public function isFrequentBuyer()
    {
        return ($this->total_orders ?? 0) >= 5;
    }

    /**
     * Check if user needs spending stats update
     */
    public function needsSpendingUpdate()
    {
        if (!$this->spending_updated_at) {
            return true;
        }
        
        // Check if there are newer orders
        $latestOrderDate = $this->orders()->latest('updated_at')->value('updated_at');
        
        return $latestOrderDate && $latestOrderDate > $this->spending_updated_at;
    }

    // =====================================
    // USER ACTIVITY METHODS - UNCHANGED
    // =====================================

    /**
     * Check if user is active (has orders or recent activity)
     */
    public function isActiveUser()
    {
        return $this->orders()->exists() || 
               $this->wishlists()->exists() || 
               $this->cartItems()->exists();
    }

    /**
     * Get user's favorite brands based on completed orders
     */
    public function getFavoriteBrands($limit = 5)
    {
        return $this->orders()
                   ->with('orderItems.product')
                   ->whereIn('status', $this->paidStatuses)
                   ->get()
                   ->flatMap(function ($order) {
                       return $order->orderItems->pluck('product.brand');
                   })
                   ->filter()
                   ->countBy()
                   ->sortDesc()
                   ->take($limit)
                   ->keys()
                   ->toArray();
    }

    // =====================================
    // DISPLAY HELPERS - UPDATED
    // =====================================

    /**
     * Get formatted total spent for display
     */
    public function getFormattedTotalSpent()
    {
        return 'Rp ' . number_format($this->total_spent ?? 0, 0, ',', '.');
    }

    /**
     * Get formatted spending 6 months for display (NEW)
     */
    public function getFormattedSpending6Months()
    {
        return 'Rp ' . number_format($this->spending_6_months ?? 0, 0, ',', '.');
    }

    /**
     * Get formatted average order value for display
     */
    public function getFormattedAverageOrderValue()
    {
        return 'Rp ' . number_format($this->getAverageOrderValue(), 0, ',', '.');
    }

    /**
     * Get customer statistics summary for dashboard (UPDATED)
     */
    public function getCustomerSummary()
    {
        return [
            'total_spent' => $this->total_spent ?? 0,
            'total_orders' => $this->total_orders ?? 0,
            'spending_6_months' => $this->spending_6_months ?? 0,
            'average_order_value' => $this->getAverageOrderValue(),
            'spending_this_month' => $this->getSpendingThisMonth(),
            'spending_this_year' => $this->getSpendingThisYear(),
            'customer_tier' => $this->getCustomerTier(),
            'customer_tier_label' => $this->getCustomerTierLabel(),
            'customer_tier_color' => $this->getCustomerTierColor(),
            'next_tier' => $this->getNextTierRequirement(),
            'points_balance' => $this->points_balance ?? 0,
            'points_percentage' => $this->getPointsPercentage(),
            'total_points_earned' => $this->total_points_earned ?? 0,
            'last_order_date' => $this->getLastOrderDate(),
            'is_high_value' => $this->isHighValueCustomer(),
            'is_frequent_buyer' => $this->isFrequentBuyer(),
            'favorite_brands' => $this->getFavoriteBrands(3),
            'last_updated' => $this->spending_updated_at,
            'needs_update' => $this->needsSpendingUpdate(),
            'needs_tier_evaluation' => $this->needsTierEvaluation(),
            'address_count' => $this->address_count,
            'has_primary_address' => $this->hasPrimaryAddress(),
            'profile_completion' => $this->getProfileCompletionPercentage(),
            'missing_fields' => $this->getMissingProfileFields()
        ];
    }

    // =====================================
    // ADMIN DASHBOARD HELPERS - UPDATED
    // =====================================

    /**
     * Get data formatted for admin dashboard (UPDATED)
     */
    public function getAdminSummary()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'birthdate' => $this->birthdate?->format('Y-m-d'),
            'total_spent' => $this->total_spent ?? 0,
            'total_orders' => $this->total_orders ?? 0,
            'spending_6_months' => $this->spending_6_months ?? 0,
            'average_order_value' => $this->getAverageOrderValue(),
            'customer_tier' => $this->getCustomerTier(),
            'customer_tier_label' => $this->getCustomerTierLabel(),
            'points_balance' => $this->points_balance ?? 0,
            'total_points_earned' => $this->total_points_earned ?? 0,
            'total_points_redeemed' => $this->total_points_redeemed ?? 0,
            'is_high_value' => $this->isHighValueCustomer(),
            'is_frequent_buyer' => $this->isFrequentBuyer(),
            'last_order_date' => $this->getLastOrderDate()?->format('Y-m-d H:i:s'),
            'member_since' => $this->created_at->format('Y-m-d'),
            'spending_updated_at' => $this->spending_updated_at?->format('Y-m-d H:i:s'),
            'last_tier_evaluation' => $this->last_tier_evaluation?->format('Y-m-d H:i:s'),
            'needs_update' => $this->needsSpendingUpdate(),
            'needs_tier_evaluation' => $this->needsTierEvaluation(),
            'address_count' => $this->address_count,
            'profile_completion' => $this->getProfileCompletionPercentage(),
            'is_profile_complete' => $this->isProfileComplete(),
        ];
    }

    // =====================================
    // POINTS TRANSACTION HELPERS - NEW
    // =====================================

    /**
     * Get recent points transactions
     */
    public function getRecentPointsTransactions($limit = 10)
    {
        return $this->pointsTransactions()
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
    }

    /**
     * Get points earned this month
     */
    public function getPointsEarnedThisMonth()
    {
        return $this->pointsTransactions()
                    ->where('type', 'earned')
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('amount');
    }

    /**
     * Get points redeemed this month
     */
    public function getPointsRedeemedThisMonth()
    {
        return $this->pointsTransactions()
                    ->where('type', 'redeemed')
                    ->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)
                    ->sum('amount');
    }

    /**
     * Get points summary for dashboard
     */
    public function getPointsSummary()
    {
        return [
            'current_balance' => $this->points_balance ?? 0,
            'total_earned' => $this->total_points_earned ?? 0,
            'total_redeemed' => $this->total_points_redeemed ?? 0,
            'earned_this_month' => $this->getPointsEarnedThisMonth(),
            'redeemed_this_month' => $this->getPointsRedeemedThisMonth(),
            'points_percentage' => $this->getPointsPercentage(),
            'tier' => $this->getCustomerTier(),
            'recent_transactions' => $this->getRecentPointsTransactions(5)
        ];
    }

    // =====================================
    // TIER ANALYSIS HELPERS - NEW
    // =====================================

    /**
     * Get days until next tier evaluation
     */
    public function getDaysUntilNextTierEvaluation()
    {
        if (!$this->last_tier_evaluation) {
            return 0; // Needs evaluation now
        }
        
        $nextEvaluation = $this->last_tier_evaluation->addDays(30);
        $daysLeft = now()->diffInDays($nextEvaluation, false);
        
        return max(0, $daysLeft);
    }

    /**
     * Get tier progress percentage
     */
    public function getTierProgressPercentage()
    {
        $nextTier = $this->getNextTierRequirement();
        
        if ($nextTier['remaining'] <= 0) {
            return 100; // Already at highest tier
        }
        
        $progress = ($nextTier['required'] - $nextTier['remaining']) / $nextTier['required'];
        return round($progress * 100, 1);
    }

    /**
     * Get spending needed for next tier
     */
    public function getSpendingNeededForNextTier()
    {
        $nextTier = $this->getNextTierRequirement();
        return $nextTier['remaining'];
    }

    /**
     * Check if user is eligible for tier upgrade
     */
    public function isEligibleForTierUpgrade()
    {
        return $this->getSpendingNeededForNextTier() <= 0 && $this->getCustomerTier() !== 'ultimate';
    }

    /**
     * Get tier history summary
     */
    public function getTierHistorySummary()
    {
        return [
            'current_tier' => $this->getCustomerTier(),
            'current_tier_label' => $this->getCustomerTierLabel(),
            'spending_6_months' => $this->spending_6_months ?? 0,
            'tier_period_start' => $this->tier_period_start,
            'last_evaluation' => $this->last_tier_evaluation,
            'days_until_next_evaluation' => $this->getDaysUntilNextTierEvaluation(),
            'progress_percentage' => $this->getTierProgressPercentage(),
            'spending_needed' => $this->getSpendingNeededForNextTier(),
            'eligible_for_upgrade' => $this->isEligibleForTierUpgrade(),
            'next_tier_info' => $this->getNextTierRequirement()
        ];
    }

    // =====================================
    // LEGACY SUPPORT METHODS - FOR BACKWARD COMPATIBILITY
    // =====================================

    /**
     * Legacy method for old tier labels - maps to new system
     * @deprecated Use getCustomerTierLabel() instead
     */
    public function getOldCustomerTierLabel()
{
    return match ($this->getCustomerTier()) {
        'ultimate' => 'Platinum Member',
        'advance'  => 'Gold Member',
        'basic'    => 'Bronze Member',
        default    => 'New Customer',
    };
}


    // =====================================
    // DEBUGGING & MAINTENANCE HELPERS - NEW
    // =====================================

    /**
     * Verify points balance integrity
     */
    public function verifyPointsBalance()
    {
        $calculatedBalance = $this->pointsTransactions()->earned()->sum('amount') 
                           - $this->pointsTransactions()->redeemed()->sum('amount')
                           + $this->pointsTransactions()->where('type', 'adjustment')->sum('amount');

        return abs($calculatedBalance - ($this->points_balance ?? 0)) < 0.01;
    }

    /**
     * Get comprehensive user health check
     */
    public function getHealthCheck()
    {
        return [
            'user_id' => $this->id,
            'profile_complete' => $this->isProfileComplete(),
            'has_orders' => $this->orders()->exists(),
            'spending_stats_current' => !$this->needsSpendingUpdate(),
            'tier_evaluation_current' => !$this->needsTierEvaluation(),
            'points_balance_valid' => $this->verifyPointsBalance(),
            'has_addresses' => $this->hasAddresses(),
            'email_verified' => !is_null($this->email_verified_at),
            'is_active' => $this->isActiveUser(),
            'last_activity' => $this->updated_at,
            'issues' => $this->getAccountIssues()
        ];
    }

    /**
     * Get list of account issues that need attention
     */
    public function getAccountIssues()
    {
        $issues = [];
        
        if (!$this->isProfileComplete()) {
            $issues[] = 'Profile incomplete';
        }
        
        if ($this->needsSpendingUpdate()) {
            $issues[] = 'Spending stats outdated';
        }
        
        if ($this->needsTierEvaluation()) {
            $issues[] = 'Tier evaluation needed';
        }
        
        if (!$this->verifyPointsBalance()) {
            $issues[] = 'Points balance inconsistent';
        }
        
        if (!$this->email_verified_at) {
            $issues[] = 'Email not verified';
        }
        
        if (!$this->hasAddresses()) {
            $issues[] = 'No shipping address';
        }
        
        return $issues;
    }

    /**
     * Get full user analytics data
     */
    public function getAnalyticsData()
    {
        return [
            'basic_info' => [
                'user_id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'member_since' => $this->created_at->format('Y-m-d'),
                'is_google_user' => $this->is_google_user,
                'email_verified' => !is_null($this->email_verified_at)
            ],
            'spending_analytics' => [
                'total_spent_lifetime' => $this->total_spent ?? 0,
                'spending_6_months' => $this->spending_6_months ?? 0,
                'total_orders' => $this->total_orders ?? 0,
                'average_order_value' => $this->getAverageOrderValue(),
                'spending_this_month' => $this->getSpendingThisMonth(),
                'spending_this_year' => $this->getSpendingThisYear(),
                'monthly_breakdown' => $this->getMonthlySpending(),
                'last_order_date' => $this->getLastOrderDate()
            ],
            'tier_analytics' => $this->getTierHistorySummary(),
            'points_analytics' => $this->getPointsSummary(),
            'behavioral_analytics' => [
                'is_high_value' => $this->isHighValueCustomer(),
                'is_frequent_buyer' => $this->isFrequentBuyer(),
                'is_active_user' => $this->isActiveUser(),
                'favorite_brands' => $this->getFavoriteBrands(),
                'wishlist_count' => $this->getWishlistCount(),
                'cart_count' => $this->getCartCount(),
                'address_count' => $this->address_count,
                'profile_completion' => $this->getProfileCompletionPercentage()
            ],
            'health_check' => $this->getHealthCheck()
        ];
    }
}