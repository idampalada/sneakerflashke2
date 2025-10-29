<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoOnedecadeEntry extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invoice_number',
        'platform',
        'phone_number',
        'coupon_code',
        'entry_number',
        'is_verified',
        'is_winner',
        'prize_type',
        'ip_address',
        'user_agent',
        'verified_at',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_verified' => 'boolean',
        'is_winner' => 'boolean',
        'verified_at' => 'datetime',
    ];
    
    /**
     * Generate a unique entry number
     * 
     * @return string
     */
    public static function generateEntryNumber(): string
    {
        // Format: SF-[YEAR]-[RANDOM 6 DIGITS]
        $year = date('Y');
        $random = mt_rand(100000, 999999);
        
        $entryNumber = "SF-{$year}-{$random}";
        
        // Check if entry number already exists and regenerate if needed
        while (self::where('entry_number', $entryNumber)->exists()) {
            $random = mt_rand(100000, 999999);
            $entryNumber = "SF-{$year}-{$random}";
        }
        
        return $entryNumber;
    }
    
    /**
     * Format phone number for display
     * 
     * @return string
     */
    public function getFormattedPhoneAttribute(): string
    {
        // If starts with +62, replace with 0
        if (substr($this->phone_number, 0, 3) === '+62') {
            return '0' . substr($this->phone_number, 3);
        }
        
        return $this->phone_number;
    }
    
    /**
     * Scope a query to only include verified entries
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }
    
    /**
     * Scope a query to only include winners
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWinners($query)
    {
        return $query->where('is_winner', true);
    }
}