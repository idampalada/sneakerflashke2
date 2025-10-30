<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PromoOnedecadeEntry;
use Illuminate\Support\Facades\Validator;

class PromoOnedecadeController extends Controller
{
    /**
     * Menampilkan halaman utama promo
     */
    public function index()
    {
        // Menghitung jumlah peserta dan nomor undian aktif
        $participantCount = PromoOnedecadeEntry::where('is_valid', true)->count();
        $activeNumbers = PromoOnedecadeEntry::where('is_valid', true)->sum('entry_count');

        return view('frontend.promo.onedecade', compact('participantCount', 'activeNumbers'));
    }

    /**
     * Menampilkan halaman khusus verifikasi
     */
    public function showVerificationPage()
    {
        // Menghitung jumlah peserta dan nomor undian aktif (akan ditampilkan di halaman verifikasi)
        $participantCount = PromoOnedecadeEntry::where('is_valid', true)->count();
        $activeNumbers = PromoOnedecadeEntry::where('is_valid', true)->sum('entry_count');

        return view('frontend.promo.onedecade_verification', compact('participantCount', 'activeNumbers'));
    }

    /**
     * Memproses verifikasi nomor undian
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'undian_code' => 'required|string|min:4',
            'order_number' => 'required|string|min:5',
            'contact_info' => 'required|string|min:10',
            'platform' => 'required|in:website,shopee,tiktok,tokopedia,blibli,whatsapp,uss_event',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Cek apakah kode undian sudah pernah digunakan
        $existingEntry = PromoOnedecadeEntry::where('coupon_code', $request->undian_code)
            ->where('order_number', $request->order_number)
            ->first();

        if ($existingEntry) {
            // Jika sudah pernah digunakan, tampilkan pesan error
            return redirect()->back()
                ->with('verification_status', 'error');
        }

        // Jika belum pernah digunakan, buat entri baru
        $entry = new PromoOnedecadeEntry();
        $entry->coupon_code = $request->undian_code;
        $entry->order_number = $request->order_number;
        $entry->contact_info = $request->contact_info;
        $entry->platform = $request->platform;
        $entry->entry_count = 1; // Defaultnya 1 nomor undian
        $entry->is_valid = true;
        $entry->ip_address = $request->ip();
        $entry->user_agent = $request->header('User-Agent');
        $entry->save();

        // Redirect dengan status sukses
        return redirect()->route('promo.onedecade.index')
            ->with('verification_status', 'success')
            ->with('entry_number', $entry->id); // ID entry sebagai nomor undian
    }
}