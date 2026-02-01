<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'status', // Single status field
        'subtotal',
        'tax_amount',
        'shipping_cost',
        'discount_amount',
        'total_amount',
        'currency',
        'shipping_address',
        'shipping_destination_id',
        'shipping_destination_label',
        'shipping_postal_code',
        'shipping_method',
        'billing_address',
        'store_origin',
        'payment_method',
                'voucher_id',        // ADDED
        'voucher_code',      // ADDED
        'payment_token',
        'payment_url',
        'snap_token',
        'payment_response',
        'tracking_number',
                'komerce_awb',
        'pickup_requested_at', 
        'shipped_at',
        'delivered_at',
        'notes',
        'meta_data',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'store_origin' => 'array',
        'payment_response' => 'array',
        'meta_data' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'pickup_requested_at' => 'datetime',
    ];

    /**
     * Define route key for URL routing
     */
    public function getRouteKeyName()
    {
        return 'order_number';
    }

    // =====================================
    // RELATIONSHIPS
    // =====================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // =====================================
    // HELPER METHODS
    // =====================================

    public function calculateTotals()
    {
        $this->subtotal = $this->orderItems->sum('total_price');
        $this->total_amount = $this->subtotal + $this->tax_amount + $this->shipping_cost - $this->discount_amount;
        $this->save();
    }

    // =====================================
    // FORMATTED ATTRIBUTES
    // =====================================

    public function getFormattedTotalAttribute()
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    public function getFormattedSubtotalAttribute()
    {
        return 'Rp ' . number_format($this->subtotal, 0, ',', '.');
    }

    public function getFormattedShippingAttribute()
    {
        return 'Rp ' . number_format($this->shipping_cost, 0, ',', '.');
    }

    public function getFormattedTaxAttribute()
    {
        return 'Rp ' . number_format($this->tax_amount, 0, ',', '.');
    }

    public function getFormattedDiscountAttribute()
    {
        return 'Rp ' . number_format($this->discount_amount, 0, ',', '.');
    }

    // =====================================
    // CUSTOMER ATTRIBUTES WITH FALLBACKS
    // =====================================

    public function getCustomerNameAttribute($value)
    {
        // Fallback to user name if customer_name is null
        return $value ?: ($this->user ? $this->user->name : 'Guest Customer');
    }

    public function getCustomerEmailAttribute($value)
    {
        // Fallback to user email if customer_email is null
        return $value ?: ($this->user ? $this->user->email : null);
    }

    // =====================================
    // STATUS METHODS - UPDATED FOR SINGLE STATUS
    // =====================================

    public function canBeCancelled()
    {
        return in_array($this->status, ['pending']);
    }

    public function isCompleted()
    {
        return in_array($this->status, ['delivered']);
    }

    public function isPaid()
    {
        return $this->status === 'paid';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isProcessing()
    {
        return $this->status === 'processing';
    }

    public function isShipped()
    {
        return $this->status === 'shipped';
    }

    public function isDelivered()
    {
        return $this->status === 'delivered';
    }

    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    public function isRefunded()
    {
        return $this->status === 'refund';
    }

    // =====================================
    // STATUS COLORS FOR UI - UPDATED
    // =====================================

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'yellow',
            'paid' => 'green',
            'processing' => 'blue',
            'shipped' => 'purple',
            'delivered' => 'green',
            'cancelled' => 'red',
            'refund' => 'gray',
            default => 'gray'
        };
    }

    public function getStatusBadgeAttribute()
    {
        $colors = [
            'pending' => 'bg-yellow-100 text-yellow-800',
            'paid' => 'bg-green-100 text-green-800',
            'processing' => 'bg-blue-100 text-blue-800',
            'shipped' => 'bg-purple-100 text-purple-800',
            'delivered' => 'bg-green-100 text-green-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'refund' => 'bg-gray-100 text-gray-800',
        ];

        return $colors[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    public function getStatusIconAttribute()
    {
        return match($this->status) {
            'pending' => '⏳',
            'paid' => '✅',
            'processing' => '🔄',
            'shipped' => '🚚',
            'delivered' => '📦',
            'cancelled' => '❌',
            'refund' => '💰',
            default => '❓'
        };
    }

    // =====================================
    // SCOPES - UPDATED FOR SINGLE STATUS
    // =====================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeShipped($query)
    {
        return $query->where('status', 'shipped');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeRefunded($query)
    {
        return $query->where('status', 'refund');
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // PostgreSQL: Search orders by number or customer
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('order_number', 'ILIKE', "%{$search}%")
              ->orWhere('customer_name', 'ILIKE', "%{$search}%")
              ->orWhere('customer_email', 'ILIKE', "%{$search}%");
        });
    }

    // PostgreSQL: Filter by date range
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // PostgreSQL: Get orders with total amount in range
    public function scopeAmountRange($query, $minAmount, $maxAmount)
    {
        return $query->whereBetween('total_amount', [$minAmount, $maxAmount]);
    }

    // =====================================
    // MIDTRANS SPECIFIC METHODS
    // =====================================

    public function hasSnapToken()
    {
        return !empty($this->snap_token);
    }

    public function getMidtransStatus()
    {
        if (empty($this->payment_response)) {
            return null;
        }

        $response = is_array($this->payment_response) ? $this->payment_response : json_decode($this->payment_response, true);
        return $response['transaction_status'] ?? null;
    }

    public function getMidtransFraudStatus()
    {
        if (empty($this->payment_response)) {
            return null;
        }

        $response = is_array($this->payment_response) ? $this->payment_response : json_decode($this->payment_response, true);
        return $response['fraud_status'] ?? null;
    }

    // =====================================
    // SHIPPING INFORMATION METHODS
    // =====================================

    public function getShippingAddressFormatted()
    {
        if (is_array($this->shipping_address)) {
            return implode(', ', array_filter($this->shipping_address));
        }
        
        return $this->shipping_address ?: 'No shipping address';
    }

    public function getFullShippingAddress()
    {
        $parts = array_filter([
            $this->shipping_address,
            $this->shipping_destination_label,
            $this->shipping_postal_code
        ]);
        
        return implode(', ', $parts) ?: 'No shipping address';
    }

    // =====================================
    // STATIC HELPER METHODS - UPDATED
    // =====================================

    public static function getStatuses()
    {
        return [
            'pending' => 'Pending',
            'paid' => 'Paid',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refund' => 'Refund'
        ];
    }

    public static function getStatusesWithIcons()
    {
        return [
            'pending' => ['label' => 'Pending', 'icon' => '⏳', 'color' => 'yellow'],
            'paid' => ['label' => 'Paid', 'icon' => '✅', 'color' => 'green'],
            'processing' => ['label' => 'Processing', 'icon' => '🔄', 'color' => 'blue'],
            'shipped' => ['label' => 'Shipped', 'icon' => '🚚', 'color' => 'purple'],
            'delivered' => ['label' => 'Delivered', 'icon' => '📦', 'color' => 'green'],
            'cancelled' => ['label' => 'Cancelled', 'icon' => '❌', 'color' => 'red'],
            'refund' => ['label' => 'Refund', 'icon' => '💰', 'color' => 'gray']
        ];
    }

    /**
 * Scope for orders that used points - NEW
 */
public function scopeWithPoints($query)
{
    return $query->whereRaw("JSON_EXTRACT(meta_data, '$.points_info.points_used') > 0");
}

public function getOrderSummary(): array
{
    return [
        'order_number' => $this->order_number,
        'subtotal' => $this->subtotal,
        'shipping_cost' => $this->shipping_cost,
        'voucher_discount' => $this->discount_amount,
        'points_used' => $this->getPointsUsed(),
        'points_discount' => $this->getPointsDiscount(),
        'total_discount' => $this->getTotalDiscount(),
        'total_amount' => $this->total_amount,
        'status' => $this->status,
        'payment_method' => $this->payment_method,
        'used_points' => $this->usedPoints(),
        'used_voucher' => $this->usedVoucher(),
        'voucher_code' => $this->getVoucherCode(),
        'formatted' => [
            'subtotal' => 'Rp ' . number_format($this->subtotal, 0, ',', '.'),
            'shipping_cost' => 'Rp ' . number_format($this->shipping_cost, 0, ',', '.'),
            'voucher_discount' => 'Rp ' . number_format($this->discount_amount, 0, ',', '.'),
            'points_used' => $this->getFormattedPointsUsed(),
            'points_discount' => $this->getFormattedPointsDiscount(),
            'total_discount' => $this->getFormattedTotalDiscountAttribute(),
            'total_amount' => 'Rp ' . number_format($this->total_amount, 0, ',', '.'),
        ]
    ];
}

/**
 * Scope for orders that used vouchers - NEW
 */
public function scopeWithVouchers($query)
{
    return $query->where('discount_amount', '>', 0);
}

/**
 * Scope for orders with any discounts (voucher or points) - NEW
 */
public function scopeWithDiscounts($query)
{
    return $query->where(function ($q) {
        $q->where('discount_amount', '>', 0)
          ->orWhereRaw("JSON_EXTRACT(meta_data, '$.points_info.points_discount') > 0");
    });
}

public function getSavingsBreakdown(): array
{
    $voucherSavings = $this->discount_amount;
    $pointsSavings = $this->getPointsDiscount();
    $totalSavings = $voucherSavings + $pointsSavings;
    
    return [
        'voucher_savings' => $voucherSavings,
        'points_savings' => $pointsSavings,
        'total_savings' => $totalSavings,
        'savings_percentage' => $this->subtotal > 0 ? ($totalSavings / $this->subtotal) * 100 : 0,
        'formatted' => [
            'voucher_savings' => 'Rp ' . number_format($voucherSavings, 0, ',', '.'),
            'points_savings' => 'Rp ' . number_format($pointsSavings, 0, ',', '.'),
            'total_savings' => 'Rp ' . number_format($totalSavings, 0, ',', '.'),
            'savings_percentage' => number_format($this->subtotal > 0 ? ($totalSavings / $this->subtotal) * 100 : 0, 1) . '%'
        ]
    ];
}
    // =====================================
    // STATUS TRANSITION METHODS
    // =====================================

    public function canTransitionTo($newStatus)
    {
        $allowedTransitions = [
            'pending' => ['paid', 'cancelled'],
            'paid' => ['processing', 'cancelled'],
            'processing' => ['shipped', 'cancelled'],
            'shipped' => ['delivered', 'cancelled'],
            'delivered' => ['refund'],
            'cancelled' => [], // Cannot transition from cancelled
            'refund' => [] // Cannot transition from refund
        ];

        return in_array($newStatus, $allowedTransitions[$this->status] ?? []);
    }

    public function transitionTo($newStatus, $notes = null)
{
    if (!$this->canTransitionTo($newStatus)) {
        throw new \Exception("Cannot transition from {$this->status} to {$newStatus}");
    }

    $oldStatus = $this->status;
    $this->status = $newStatus;

    // Add transition notes
    if ($notes) {
        $this->notes = ($this->notes ? $this->notes . "\n" : '') . 
                      "[" . now()->format('Y-m-d H:i:s') . "] Status changed from {$oldStatus} to {$newStatus}: {$notes}";
    }

    // Handle specific status transitions
    switch ($newStatus) {
        case 'shipped':
            if (!$this->shipped_at) {
                $this->shipped_at = now();
            }
            break;
            
        case 'delivered':
            if (!$this->delivered_at) {
                $this->delivered_at = now();
            }
            break;
            
        case 'cancelled':
            // Refund points if order is cancelled
            $this->processPointsRefund();
            break;
    }

    $this->save();

    \Illuminate\Support\Facades\Log::info('Order status transition with points support', [
        'order_number' => $this->order_number,
        'old_status' => $oldStatus,
        'new_status' => $newStatus,
        'points_used' => $this->getPointsUsed(),
        'points_refunded' => ($newStatus === 'cancelled' && $this->usedPoints()),
        'notes' => $notes
    ]);

    return $this;
}

    // =====================================
    // PAYMENT HELPER METHODS - UPDATED
    // =====================================

    public function requiresPayment()
    {
        return $this->payment_method !== 'cod' && $this->status === 'pending';
    }

    public function canRetryPayment()
    {
        return $this->payment_method !== 'cod' && 
               in_array($this->status, ['pending', 'cancelled']) &&
               !empty($this->snap_token);
    }

    public function getPaymentStatusText()
    {
        if ($this->payment_method === 'cod') {
            return match($this->status) {
                'pending' => 'COD - Pending',
                'paid' => 'COD - Completed',
                'processing' => 'COD - Processing',
                'shipped' => 'COD - Shipped',
                'delivered' => 'COD - Delivered',
                'cancelled' => 'COD - Cancelled',
                default => 'COD - ' . ucfirst($this->status)
            };
        }

        return match($this->status) {
            'pending' => 'Payment Required',
            'paid' => 'Payment Completed',
            'processing' => 'Paid - Processing',
            'shipped' => 'Paid - Shipped',
            'delivered' => 'Paid - Delivered',
            'cancelled' => 'Payment Cancelled',
            'refund' => 'Refunded',
            default => ucfirst($this->status)
        };
    }

    // =====================================
    // ADMIN HELPER METHODS
    // =====================================

    public function getAdminActions()
    {
        $actions = [];
        
        switch ($this->status) {
            case 'pending':
                if ($this->payment_method !== 'cod') {
                    $actions[] = ['action' => 'mark_paid', 'label' => 'Mark as Paid', 'color' => 'success'];
                }
                $actions[] = ['action' => 'cancel', 'label' => 'Cancel Order', 'color' => 'danger'];
                break;
                
            case 'paid':
                $actions[] = ['action' => 'process', 'label' => 'Start Processing', 'color' => 'primary'];
                $actions[] = ['action' => 'cancel', 'label' => 'Cancel Order', 'color' => 'danger'];
                break;
                
            case 'processing':
                $actions[] = ['action' => 'ship', 'label' => 'Mark as Shipped', 'color' => 'info'];
                $actions[] = ['action' => 'cancel', 'label' => 'Cancel Order', 'color' => 'danger'];
                break;
                
            case 'shipped':
                $actions[] = ['action' => 'deliver', 'label' => 'Mark as Delivered', 'color' => 'success'];
                break;
                
            case 'delivered':
                $actions[] = ['action' => 'refund', 'label' => 'Process Refund', 'color' => 'warning'];
                break;
        }
        
        return $actions;
    }

    // =====================================
    // STATISTICS METHODS
    // =====================================

    public static function getStatusCounts()
    {
        return static::selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();
    }

    public static function getRevenueByStatus()
    {
        return static::selectRaw('status, SUM(total_amount) as revenue')
                    ->groupBy('status')
                    ->pluck('revenue', 'status')
                    ->toArray();
    }
    public function processPointsRefund(): void
{
    if (!$this->user || !$this->usedPoints()) {
        return;
    }
    
    try {
        $pointsUsed = $this->getPointsUsed();
        
        if ($pointsUsed > 0) {
            // Refund points to user
            $this->user->increment('points_balance', $pointsUsed);
            
            Log::info('Points refunded for cancelled order', [
                'order_id' => $this->id,
                'order_number' => $this->order_number,
                'user_id' => $this->user_id,
                'points_refunded' => $pointsUsed,
                'new_balance' => $this->user->fresh()->points_balance
            ]);
        }
        
    } catch (\Exception $e) {
        Log::error('Error processing points refund for cancelled order', [
            'order_id' => $this->id,
            'order_number' => $this->order_number,
            'error' => $e->getMessage()
        ]);
    }
}
 /**
     * Check if AWB is available
     */
    public function hasAwb(): bool
    {
        return !empty($this->komerce_awb);
    }
    
    /**
     * Check if pickup has been requested
     */
    public function hasPickupRequested(): bool
    {
        return !empty($this->pickup_requested_at);
    }

    /**
     * Check if order is ready for pickup (paid but no pickup requested yet)
     */
public function isReadyForPickup(): bool
{
    $meta = json_decode($this->meta_data ?? '{}', true) ?? [];
    
    return $this->status === 'paid' && 
           !$this->hasPickupRequested();
}
    /**
 * Get AWB from meta_data or komerce_awb (fallback)
 */
public function getAwbAttribute(): ?string
{
    // 1️⃣ prioritas dari meta_data
    if (!empty($this->meta_data['awb'])) {
        return $this->meta_data['awb'];
    }

    // 2️⃣ fallback ke column komerce_awb
    return $this->komerce_awb ?: null;
}
public function updateStatusBasedOnAwb(): void
{
    // Jika sudah ada AWB dan status masih 'paid', ubah ke 'processing'
    if ($this->hasAwb() && $this->status === 'paid') {
        $this->update([
            'status' => 'processing',
            'notes' => ($this->notes ? $this->notes . "\n" : '') . 
                      '[' . now()->format('Y-m-d H:i:s') . '] Status automatically updated to processing - AWB available: ' . $this->awb
        ]);
        
        \Log::info('Order status auto-updated to processing', [
            'order_id' => $this->id,
            'order_number' => $this->order_number,
            'awb' => $this->awb,
            'previous_status' => 'paid',
            'new_status' => 'processing'
        ]);
    }
}

/**
 * Update AWB in meta_data and automatically update status
 */
public function updateAwbAndStatus(string $awb): void
{
    $meta = json_decode($this->meta_data ?? '{}', true) ?? [];
    $meta['awb'] = $awb;
    
    $this->update([
        'meta_data' => json_encode($meta),
        'komerce_awb' => $awb, // Backup ke column
    ]);
    
    // Auto-update status berdasarkan AWB
    $this->updateStatusBasedOnAwb();
}

/**
 * Check if order should be processed (has AWB but still paid)
 */
public function shouldBeProcessing(): bool
{
    return $this->hasAwb() && $this->status === 'paid';
}


}