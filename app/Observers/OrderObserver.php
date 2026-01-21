<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\User;
use App\Models\PointsTransaction;
use Illuminate\Support\Facades\Log;

class OrderObserver
{
    /**
     * Valid statuses that count as "paid/completed" orders for revenue calculation
     */
    private array $paidStatuses = ['paid', 'processing', 'shipped', 'delivered'];

    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        // Update user stats when order is created
        $this->updateUserSpending($order->user_id, 'order_created', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total_amount' => $order->total_amount
        ]);
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        // Check if status changed
        if ($order->isDirty('status')) {
            $oldStatus = $order->getOriginal('status');
            $newStatus = $order->status;
            
            // Check if status changed from non-paid to paid, or vice versa
            $oldWasPaid = in_array($oldStatus, $this->paidStatuses);
            $newIsPaid = in_array($newStatus, $this->paidStatuses);
            
            if ($oldWasPaid !== $newIsPaid) {
                $this->updateUserSpending($order->user_id, 'status_changed', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'total_amount' => $order->total_amount,
                    'became_paid' => $newIsPaid
                ]);
                
                // Award points if order became paid
                if ($newIsPaid && !$oldWasPaid) {
                    $this->awardPointsForOrder($order);
                }
            }
                }

    // ✅ TAMBAHAN BARU: Check AWB and auto-update status
if ($order->wasChanged('meta_data') || $order->wasChanged('komerce_awb') || $order->isDirty()) {
    $this->checkAndUpdateStatusForAwb($order);
}
    }
    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        // Update user stats when order is deleted
        $this->updateUserSpending($order->user_id, 'order_deleted', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total_amount' => $order->total_amount
        ]);
    }

    /**
     * Award points to user for completed order
     */
    private function awardPointsForOrder(Order $order)
    {
        try {
            $user = User::find($order->user_id);
            if (!$user) {
                Log::warning('User not found for points award', ['user_id' => $order->user_id]);
                return;
            }

            // Calculate points based on user tier and order amount
            $pointsEarned = 0;
            
            if (method_exists($user, 'addPointsFromPurchase')) {
                // Use new points system if available
                $pointsEarned = $user->addPointsFromPurchase($order->total_amount);
            } else {
                // Fallback to simple calculation
                $pointsPercentage = 1; // Default 1% for basic tier
                if (method_exists($user, 'getPointsPercentage')) {
                    $pointsPercentage = $user->getPointsPercentage();
                }
                
                $pointsEarned = round(($order->total_amount * $pointsPercentage) / 100, 2);
                
                // Update user points balance manually if new system not available
                $user->increment('points_balance', $pointsEarned);
                $user->increment('total_points_earned', $pointsEarned);
            }
            
            // Create points transaction record if model exists
            if (class_exists('App\Models\PointsTransaction')) {
                try {
                    PointsTransaction::createEarned(
                        $user->id,
                        $pointsEarned,
                        $order->id,
                        "Points earned from order #{$order->order_number}"
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to create points transaction record', [
                        'user_id' => $user->id,
                        'order_id' => $order->id,
                        'points' => $pointsEarned,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            Log::info('Points awarded for order', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_amount' => $order->total_amount,
                'user_tier' => method_exists($user, 'getCustomerTier') ? $user->getCustomerTier() : 'basic',
                'points_percentage' => method_exists($user, 'getPointsPercentage') ? $user->getPointsPercentage() : 1,
                'points_earned' => $pointsEarned,
                'new_points_balance' => $user->points_balance ?? 0
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to award points for order', [
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Update user spending statistics and tier evaluation
     */
    private function updateUserSpending($userId, $trigger, $context = [])
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                Log::warning('User not found for spending update', ['user_id' => $userId]);
                return;
            }
            
            // Store old values for comparison
            $oldSpent = $user->total_spent ?? 0;
            $oldOrders = $user->total_orders ?? 0;
            $oldSpending6Months = $user->spending_6_months ?? 0;
            $oldTier = method_exists($user, 'getCustomerTier') ? $user->getCustomerTier() : 'basic';
            
            // Update spending stats (this method now also calls evaluateCustomerTier if available)
            if (method_exists($user, 'updateSpendingStats')) {
                $user->updateSpendingStats();
            } else {
                // Fallback update for basic stats
                $totalSpent = $user->orders()->whereIn('status', $this->paidStatuses)->sum('total_amount');
                $totalOrders = $user->orders()->whereIn('status', $this->paidStatuses)->count();
                
                $user->update([
                    'total_spent' => $totalSpent,
                    'total_orders' => $totalOrders,
                    'spending_updated_at' => now()
                ]);
            }
            
            $user->refresh();
            
            // Get new values
            $newSpent = $user->total_spent ?? 0;
            $newOrders = $user->total_orders ?? 0;
            $newSpending6Months = $user->spending_6_months ?? 0;
            $newTier = method_exists($user, 'getCustomerTier') ? $user->getCustomerTier() : 'basic';
            
            // Log the update
            Log::info('User spending and tier updated via OrderObserver', array_merge([
                'user_id' => $userId,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'trigger' => $trigger,
                'old_spent' => $oldSpent,
                'new_spent' => $newSpent,
                'spent_change' => $newSpent - $oldSpent,
                'old_orders' => $oldOrders,
                'new_orders' => $newOrders,
                'orders_change' => $newOrders - $oldOrders,
                'old_spending_6_months' => $oldSpending6Months,
                'new_spending_6_months' => $newSpending6Months,
                'spending_6_months_change' => $newSpending6Months - $oldSpending6Months,
                'old_tier' => $oldTier,
                'new_tier' => $newTier,
                'tier_changed' => $oldTier !== $newTier,
                'paid_statuses' => $this->paidStatuses
            ], $context));
            
            // Log tier change separately if it occurred
            if ($oldTier !== $newTier) {
                Log::info('Customer tier changed via OrderObserver', [
                    'user_id' => $userId,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'old_tier' => $oldTier,
                    'new_tier' => $newTier,
                    'new_tier_label' => method_exists($user, 'getCustomerTierLabel') ? $user->getCustomerTierLabel() : ucfirst($newTier) . ' Member',
                    'spending_6_months' => $newSpending6Months,
                    'trigger' => $trigger,
                    'context' => $context
                ]);
                
                // Here you could dispatch events for tier changes
                // event(new CustomerTierChanged($user, $oldTier, $newTier));
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to update user spending via OrderObserver', [
                'user_id' => $userId,
                'trigger' => $trigger,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'context' => $context
            ]);
        }
    }

    /**
     * Get tier from spending amount (fallback method)
     */
    private function getTierFromSpending($spending6Months)
    {
        if ($spending6Months >= 10000000) return 'ultimate';
        if ($spending6Months >= 5000000) return 'advance';
        return 'basic';
    }
    /**
 * Auto-update status jika order punya AWB tapi status masih paid
 */
// Update method di OrderObserver
private function checkAndUpdateStatusForAwb(Order $order): void
{
    // Skip jika sedang dalam proses update status
    if ($order->wasChanged('status')) {
        return;
    }

    // ✅ Update logic: langsung ke shipped jika ada AWB
    if ($order->hasAwb() && in_array($order->status, ['paid', 'processing'])) {
        $order->updateQuietly([
            'status' => 'shipped',        // Langsung shipped
            'shipped_at' => now(),        // Set timestamp
            'notes' => ($order->notes ? $order->notes . "\n" : '') . 
                      '[' . now()->format('Y-m-d H:i:s') . '] Auto-shipped: AWB available, paket sudah dipickup (AWB: ' . $order->awb . ')'
        ]);
    }
}
}
