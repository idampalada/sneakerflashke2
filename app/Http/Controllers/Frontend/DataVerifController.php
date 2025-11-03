<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DataVerifController extends Controller
{
    /**
     * Display the verification data page
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Get all verified entries from the database
        $verifiedEntries = DB::table('promo_onedecade_entries')
            ->where('is_verified', true)
            ->orderBy('verified_at', 'desc')
            ->get();
        
        // Count total verifications
        $totalVerifications = $verifiedEntries->count();

        // Group by platform for statistics
        $platformStats = $verifiedEntries->groupBy('platform')
            ->map(function ($items) {
                return $items->count();
            });

        return view('frontend.promo.dataverif', compact('verifiedEntries', 'totalVerifications', 'platformStats'));
    }
}