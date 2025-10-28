<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PromoController extends Controller
{
    /**
     * Tampilkan halaman promo One Decade
     */
    public function showOneDecade()
    {
        // Untuk demo, anggap ada 8 peserta
        $participantCount = 8;
        $activeNumbers = 18;
        
        // Gunakan Carbon::now() tanpa timezone parameter
        $lastUpdated = Carbon::now();
        
        // Tetapkan tanggal pengundian dengan format yang benar
        // Gunakan create (bukan createFromDate) dengan parameter yang valid
        $drawDate = Carbon::create(2026, 1, 24, 12, 0, 0);
        
        return view('frontend.promo.onedecade', [
            'participantCount' => $participantCount,
            'activeNumbers' => $activeNumbers,
            'lastUpdated' => $lastUpdated,
            'drawDate' => $drawDate,
            'igAccount' => '@sneakers_flash'
        ]);
    }
    
    /**
     * Proses verifikasi untuk promo One Decade
     */
    public function verifyOneDecade(Request $request)
    {
        // Validasi input
        $request->validate([
            'invoice_number' => 'required',
            'platform' => 'required',
        ]);
        
        // Logika verifikasi bisa ditambahkan di sini
        // Untuk demo, kita langsung redirect ke halaman result
        
        return redirect()->route('promo.onedecade.result');
    }
    
    /**
     * Tampilkan halaman result promo One Decade
     */
    public function showOneDecadeResult()
    {
        // Untuk demo, anggap ada 8 peserta
        $participantCount = 8;
        $activeNumbers = 18;
        $lastUpdated = Carbon::now();
        
        return view('frontend.promo.onedecade_result', [
            'participantCount' => $participantCount,
            'activeNumbers' => $activeNumbers,
            'lastUpdated' => $lastUpdated
        ]);
    }
}