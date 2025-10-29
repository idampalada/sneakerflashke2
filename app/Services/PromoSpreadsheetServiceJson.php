<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class PromoSpreadsheetServiceJson
{
    protected $jsonFilePath;
    
    public function __construct()
    {
        // Path to the JSON file that contains your spreadsheet data
        $this->jsonFilePath = storage_path('app/onedecade_sample_data.json');
    }
    
    /**
     * Get One Decade promo stats from a local JSON file
     */
    public function getPromoStats()
    {
        // Cache the results for 5 minutes
        return Cache::remember('onedecade_promo_stats', 300, function() {
            try {
                // Default fallback values
                $stats = [
                    'participantCount' => 8,
                    'activeNumbers' => 18,
                    'lastUpdated' => now()
                ];
                
                // Check if the JSON file exists
                if (!File::exists($this->jsonFilePath)) {
                    Log::warning('One Decade promo JSON file not found: ' . $this->jsonFilePath);
                    return $stats;
                }
                
                // Read and decode the JSON file
                $jsonContents = File::get($this->jsonFilePath);
                $data = json_decode($jsonContents, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error('Error parsing One Decade promo JSON file: ' . json_last_error_msg());
                    return $stats;
                }
                
                // Count participants (rows with value > 0 in "jumlah_kupon")
                $participantCount = 0;
                $totalCoupons = 0;
                
                if (isset($data['pesanan']) && is_array($data['pesanan'])) {
                    foreach ($data['pesanan'] as $row) {
                        $couponCount = isset($row['jumlah_kupon']) ? intval($row['jumlah_kupon']) : 0;
                        
                        if ($couponCount > 0) {
                            $participantCount++;
                            $totalCoupons += $couponCount;
                        }
                    }
                }
                
                // Update stats with actual values
                $stats['participantCount'] = $participantCount ?: 8; // Fallback to 8 if 0
                $stats['activeNumbers'] = $totalCoupons ?: 18; // Fallback to 18 if 0
                $stats['lastUpdated'] = now();
                
                return $stats;
            } catch (\Exception $e) {
                Log::error('Error fetching One Decade promo stats from JSON: ' . $e->getMessage());
                return [
                    'participantCount' => 8,
                    'activeNumbers' => 18,
                    'lastUpdated' => now()
                ];
            }
        });
    }
}