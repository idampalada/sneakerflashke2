<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\PromoSpreadsheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;


class PromoController extends Controller
{
    protected $spreadsheetId;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->spreadsheetId = env('ONEDECADE_SPREADSHEET_ID', '1XabuSo1KrmT-smIZNKKmvaFq1_3X4kvh7sHvA6K7TKI');
    }
    
    /**
     * Tampilkan halaman promo One Decade
     */

    
    /**
     * Proses verifikasi untuk promo One Decade
     * Update: Validasi nomor undian dengan data di spreadsheet
     */
public function verifyOneDecade(Request $request)
{
    // Validasi input
    $request->validate([
        'undian_code' => 'required|string|max:50',
        'order_number' => 'required|string|max:50',
        'contact_info' => 'required|regex:/^[0-9+\-\s\(\)]{10,}$/',
        'platform' => 'required|string',
    ]);
    
    try {
        // 1. Validasi dengan data spreadsheet
        $validationResult = $this->validateUndianWithSpreadsheet(
            $request->undian_code,
            $request->order_number,
            $request->platform
        );
        
        if (!$validationResult['success']) {
            // Jika validasi gagal, redirect ke halaman finish dengan status error
            session()->flash('verification_status', 'error');
            session()->flash('error_message', $validationResult['message']);
            return redirect()->route('promo.onedecade.finish');
        }
        
        // 2. Cek apakah kode sudah pernah digunakan di database
        if (Schema::hasTable('promo_onedecade_entries')) {
            $existingEntry = DB::table('promo_onedecade_entries')
                ->where('undian_code', $request->undian_code)
                ->where('order_number', $request->order_number)
                ->first();
                
            if ($existingEntry) {
                // Kode sudah pernah dipakai
                session()->flash('verification_status', 'error');
                session()->flash('error_message', 'Kode tidak ditemukan atau sudah dipakai. Cek kembali ya.');
                return redirect()->route('promo.onedecade.finish');
            }
        }
        
        // 3. Simpan data ke database jika validasi berhasil dan data belum ada
        
        // Membersihkan contact info (nomor handphone saja)
        $cleanContact = preg_replace('/[^0-9+]/', '', $request->contact_info);
        
        // Jika nomor dimulai dengan 0, ubah ke format +62
        if (substr($cleanContact, 0, 1) === '0') {
            $cleanContact = '+62' . substr($cleanContact, 1);
        }
        
        // Uppercase undian code
        $undianCode = strtoupper(trim($request->undian_code));
        
        // Tidak perlu generate entry number dengan format SF-YYYY-XXXXXX
        // Gunakan langsung kode undian asli (undian_code) yang dimasukkan user
        
        // Insert data jika tabel sudah ada
        if (Schema::hasTable('promo_onedecade_entries')) {
            DB::table('promo_onedecade_entries')->insert([
                'undian_code' => $undianCode,
                'order_number' => $request->order_number,
                'platform' => $request->platform,
                'contact_info' => $cleanContact,
                'entry_number' => $undianCode, // Simpan undian_code sebagai entry_number
                'is_verified' => true,
                'verified_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        // Set success flash
        session()->flash('verification_status', 'success');
        session()->flash('success_message', 'Nomor undian terverifikasi. Good luck, peeps!');
        session()->flash('undian_code', $undianCode); // Simpan undian_code di session
        
        // Return with AJAX if request is AJAX
        if ($request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Nomor undian terverifikasi. Good luck, peeps!',
                'redirect_url' => route('promo.onedecade.finish')
            ]);
        }
        
        // Redirect to finish page
        return redirect()->route('promo.onedecade.finish');
        
    } catch (\Exception $e) {
        Log::error('Error in One Decade promo verification: ' . $e->getMessage(), [
            'undian_code' => $request->undian_code ?? 'not provided',
            'order_number' => $request->order_number ?? 'not provided',
            'platform' => $request->platform ?? 'not provided',
        ]);
        
        // Set error flash
        session()->flash('verification_status', 'error');
        session()->flash('error_message', 'Terjadi kesalahan sistem. Silakan coba lagi nanti.');
        
        // Return with AJAX if request is AJAX
        if ($request->ajax()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan sistem. Silakan coba lagi nanti.',
                'redirect_url' => route('promo.onedecade.finish')
            ]);
        }
        
        // Redirect to finish page
        return redirect()->route('promo.onedecade.finish');
    }
}



    
    /**
     * Validasi nomor undian dengan data di spreadsheet
     */
    // Di PromoController.php, ubah fungsi validateUndianWithSpreadsheet()
private function validateUndianWithSpreadsheet($undianCode, $orderNumber, $platform)
{
    \Log::info('Validasi dimulai', [
        'undianCode' => $undianCode,
        'orderNumber' => $orderNumber,
        'platform' => $platform
    ]);
    
    try {
        // Ambil data spreadsheet
        $spreadsheetData = $this->fetchSpreadsheetData();
        
        if (empty($spreadsheetData)) {
            return [
                'success' => false,
                'message' => 'Tidak dapat mengakses data validasi. Silakan coba lagi nanti.',
            ];
        }
        
        // Parse HTML menjadi DOM
        $dom = new \DOMDocument();
        @$dom->loadHTML($spreadsheetData);
        $rows = $dom->getElementsByTagName('tr');
        
        // Cari indeks kolom yang relevan
        $noPesananIndex = 4; // Kolom E, lihat gambar
        $nomorUndianIndex = 7; // Kolom H (JUMLAH KUPON)
        $platformIndex = 2;   // Kolom C, untuk Market Place
        
        // Cek setiap baris data
        $nomorUndianDitemukan = false;
        
        foreach ($rows as $i => $row) {
            // Skip header rows
            if ($i < 4) continue;
            
            $cells = $row->getElementsByTagName('td');
            
            // Skip jika tidak cukup kolom
            if ($cells->length <= max($noPesananIndex, $nomorUndianIndex, $platformIndex)) continue;
            
            $dataNoPesanan = trim(strip_tags($cells->item($noPesananIndex)->textContent));
            $dataNomorUndian = trim(strip_tags($cells->item($nomorUndianIndex)->textContent));
            $dataPlatform = trim(strip_tags($cells->item($platformIndex)->textContent));
            
            // Jika nomor undian cocok
            if (strtoupper($dataNomorUndian) === strtoupper($undianCode)) {
                $nomorUndianDitemukan = true;
                
                // Cek apakah nomor pesanan cocok
                if (strtoupper($dataNoPesanan) === strtoupper($orderNumber)) {
                    // Cek apakah platform juga cocok
                    if (strtoupper($dataPlatform) === strtoupper($platform)) {
                        // Semua data cocok - sukses!
                        return [
                            'success' => true,
                            'message' => 'Data valid',
                            'data' => [
                                'NOMOR UNDIAN' => $dataNomorUndian,
                                'No Pesanan' => $dataNoPesanan,
                                'Market Place' => $dataPlatform
                            ]
                        ];
                    } else {
                        // Platform tidak cocok
                        return [
                            'success' => false,
                            'message' => 'Platform pembelian tidak sesuai dengan data. Silakan periksa kembali.',
                        ];
                    }
                } else {
                    // Nomor undian cocok tapi nomor pesanan tidak cocok
                    return [
                        'success' => false,
                        'message' => 'Nomor pesanan tidak sesuai dengan nomor undian. Silakan periksa kembali.',
                    ];
                }
            }
        }
        
        // Nomor undian tidak ditemukan sama sekali
        return [
            'success' => false,
            'message' => 'Kode tidak ditemukan. Silakan periksa kembali.',
        ];
        
    } catch (\Exception $e) {
        \Log::error('Error validasi undian: ' . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Terjadi kesalahan sistem. Silakan coba lagi nanti.',
        ];
    }
}
    
    /**
     * Ambil data spreadsheet
     */
    // Di PromoController.php, ubah fungsi fetchSpreadsheetData()
private function fetchSpreadsheetData()
{
    try {
        // URL untuk akses langsung HTML
        $url = "https://docs.google.com/spreadsheets/d/{$this->spreadsheetId}/edit?gid=1207095869&rand=" . time();
        
        // Log untuk debugging
        \Log::info('Mencoba mengambil spreadsheet', [
            'url' => $url
        ]);
        
        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Cache-Control' => 'no-cache, no-store, must-revalidate'
            ])
            ->get($url);
        
        if ($response->successful()) {
            $body = $response->body();
            \Log::info('Berhasil mengambil spreadsheet', [
                'size' => strlen($body)
            ]);
            return $body;
        }
        
        \Log::error('Gagal mengambil spreadsheet', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);
        
        return null;
    } catch (\Exception $e) {
        \Log::error('Error fetching spreadsheet data: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        return null;
    }
}
    
    /**
     * Parse data spreadsheet menjadi array data
     */
    private function parseSpreadsheetData($rawContent)
    {
        $result = [];
        
        try {
            // Parse HTML content
            $dom = new \DOMDocument();
            @$dom->loadHTML($rawContent);
            $tables = $dom->getElementsByTagName('table');
            
            if ($tables->length === 0) {
                return $result;
            }
            
            $table = $tables->item(0);
            $rows = $table->getElementsByTagName('tr');
            
            // Tentukan baris header (baris ke-4, indeks 3)
            $headerRow = null;
            if ($rows->length > 3) {
                $headerRow = $rows->item(3);
            } else {
                return $result;
            }
            
            // Ekstrak header
            $headers = [];
            $headerCells = $headerRow->getElementsByTagName('td');
            for ($i = 0; $i < $headerCells->length; $i++) {
                $headers[$i] = trim($headerCells->item($i)->textContent);
            }
            
            // Ekstrak data dari baris berikutnya
            for ($i = 4; $i < $rows->length; $i++) {
                $row = $rows->item($i);
                $cells = $row->getElementsByTagName('td');
                
                if ($cells->length < count($headers)) {
                    continue; // Skip jika jumlah kolom kurang
                }
                
                $rowData = [];
                for ($j = 0; $j < $cells->length && $j < count($headers); $j++) {
                    if (!empty($headers[$j])) {
                        $rowData[$headers[$j]] = trim($cells->item($j)->textContent);
                    }
                }
                
                // Tambahkan ke hasil jika ada data valid
                if (!empty($rowData)) {
                    $result[] = $rowData;
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Error parsing spreadsheet data: ' . $e->getMessage());
        }
        
        return $result;
    }
    
    /**
     * Get promo stats dari spreadsheet
     */
/**
 * Get promo stats dari spreadsheet - versi fixed
 */
/**
 * Get promo stats dari spreadsheet - versi fixed
 */
public function getPromoStats()
{
    try {
        $rawContent = $this->fetchSpreadsheetData();

        // Fallback bila parsing gagal
        $participantCount = 16;
        $activeNumbers    = 27;

        if (!empty($rawContent)) {
            // Ambil semua baris <tr>
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $rawContent, $rowMatches);
            $rows     = $rowMatches[1] ?? [];
            $rowCount = count($rows);

            // Deteksi indeks kolom (dinamis), fallback ke E (No Pesanan) & I (JUMLAH KUPON)
            $noPesananIndex = null;
            $jumlahKuponIndex = null;
            $dataStartRow = 4;

            for ($i = 0; $i < min(12, $rowCount); $i++) {
                preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $rows[$i], $cellMatchesHeader);
                $headerCells = array_map(fn($v) => trim(strip_tags(html_entity_decode($v))), $cellMatchesHeader[1] ?? []);
                foreach ($headerCells as $idx => $text) {
                    if ($text === 'No Pesanan' || $text === 'NO PESANAN') $noPesananIndex = $idx;
                    if ($text === 'JUMLAH KUPON') $jumlahKuponIndex = $idx;
                }
                if ($noPesananIndex !== null && $jumlahKuponIndex !== null) {
                    $dataStartRow = $i + 1;
                    break;
                }
            }

            if ($noPesananIndex === null)   $noPesananIndex   = 4; // kolom E
            if ($jumlahKuponIndex === null) $jumlahKuponIndex = 8; // kolom I

            $rowsSeen  = 0;
            $rowsValid = 0;
            $participants = 0;
            $totalKupon   = 0;
            $sample = [];
            $kuponValuesSample = [];

            for ($r = $dataStartRow; $r < $rowCount; $r++) {
                preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $rows[$r], $cellMatches);
                $cells = $cellMatches[1] ?? [];
                $rowsSeen++;

                if (count($cells) <= max($jumlahKuponIndex, $noPesananIndex)) continue;

                $noPesananRaw   = strip_tags(html_entity_decode($cells[$noPesananIndex]   ?? ''));
                $jumlahKuponRaw = strip_tags(html_entity_decode($cells[$jumlahKuponIndex] ?? ''));

                $noPesanan = preg_replace('/\s+/', '', trim($noPesananRaw));
                $jumlahKuponNum = (int) preg_replace('/[^0-9\-]/', '', $jumlahKuponRaw);

                if ($noPesanan !== '' && $noPesanan !== '0' && $noPesanan !== '-' && $jumlahKuponNum > 0) {
                    $rowsValid++;
                    $participants++;             // tiap baris dengan kupon > 0
                    $totalKupon += $jumlahKuponNum; // ← SUM JUMLAH KUPON

                    if (count($sample) < 10) $sample[] = ['no_pesanan' => $noPesanan, 'kupon' => $jumlahKuponNum];
                    if (count($kuponValuesSample) < 20) $kuponValuesSample[] = $jumlahKuponNum;
                }
            }

            if ($participants > 0) $participantCount = $participants;
            $activeNumbers = $totalKupon; // ← pakai SUM kupon

            \Log::info('PromoStats parsed (sum kupon)', [
                'rows_total_found'   => $rowCount,
                'data_start_row'     => $dataStartRow,
                'no_pesanan_index'   => $noPesananIndex,
                'jumlah_kupon_index' => $jumlahKuponIndex,
                'rows_seen'          => $rowsSeen,
                'rows_valid_kupon>0' => $rowsValid,
                'participants'       => $participantCount,
                'sum_jumlah_kupon'   => $activeNumbers,
                'kupon_values_sample'=> $kuponValuesSample,
                'sample_first_10'    => $sample,
            ]);

            // Fallback bila kosong total
            if ($participantCount <= 0) $participantCount = 16;
            if ($activeNumbers   <= 0) $activeNumbers    = 27;
        }

        return [
            'participantCount' => $participantCount,
            'activeNumbers'    => $activeNumbers,
            'lastUpdated'      => now(),
        ];
    } catch (\Exception $e) {
        \Log::error('Error calculating promo stats: '.$e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);

        return [
            'participantCount' => 16,
            'activeNumbers'    => 27,
            'lastUpdated'      => now(),
        ];
    }
}



    
    /**
     * Tampilkan halaman result promo One Decade
     */
    public function showOneDecadeResult()
    {
        try {
            // Get stats
            $stats = $this->getPromoStats();
            
            // Get success message dan entry number dari session
            $successMessage = session('success_message');
            $errorMessage = session('error_message');
            
            return view('frontend.promo.onedecade_result', [
                'participantCount' => $stats['participantCount'],
                'activeNumbers' => $stats['activeNumbers'],
                'lastUpdated' => $stats['lastUpdated'],
                'successMessage' => $successMessage,
                'errorMessage' => $errorMessage
            ]);
        } catch (\Exception $e) {
            Log::error('Error in One Decade promo result page: ' . $e->getMessage());
            
            // Fallback to default values
            return view('frontend.promo.onedecade_result', [
                'participantCount' => 16,
                'activeNumbers' => 25,
                'lastUpdated' => Carbon::now(),
                'successMessage' => session('success_message'),
                'errorMessage' => session('error_message')
            ]);
        }
    }
    
    /**
     * Migrasi database
     */
    public function migrateOneDecadeTable()
    {
        try {
            if (!Schema::hasTable('promo_onedecade_entries')) {
                Schema::create('promo_onedecade_entries', function ($table) {
                    $table->id();
                    $table->string('undian_code')->nullable();
                    $table->string('order_number')->nullable();
                    $table->string('platform')->nullable();
                    $table->string('contact_info')->nullable();
                    $table->string('entry_number')->unique()->nullable();
                    $table->boolean('is_verified')->default(false);
                    $table->timestamp('verified_at')->nullable();
                    $table->string('ip_address')->nullable();
                    $table->text('user_agent')->nullable();
                    $table->timestamps();
                });
                
                return response()->json(['success' => true, 'message' => 'Promo one decade table created successfully']);
            }
            
            return response()->json(['success' => false, 'message' => 'Table already exists']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
       public function debugValidateUndian($undianCode, $orderNumber, $platform)
   {
       try {
           $result = $this->validateUndianWithSpreadsheet($undianCode, $orderNumber, $platform);
           return response()->json($result);
       } catch (\Exception $e) {
           return response()->json([
               'error' => $e->getMessage(),
               'trace' => $e->getTraceAsString()
           ], 500);
       }
   }
   public function showOneDecadeVerification()
{
    try {
        // Get stats dari spreadsheet
        $stats = $this->getPromoStats();
        
        // Tetapkan tanggal pengundian
        $drawDate = Carbon::create(2026, 1, 24, 12, 0, 0);
        
        return view('frontend.promo.onedecade_verification', [
            'participantCount' => $stats['participantCount'],
            'activeNumbers' => $stats['activeNumbers'],
            'lastUpdated' => $stats['lastUpdated'],
            'drawDate' => $drawDate,
            'igAccount' => '@sneakers_flash'
        ]);
    } catch (\Exception $e) {
        Log::error('Error in One Decade verification page: ' . $e->getMessage());
        
        // Fallback to default values
        return view('frontend.promo.onedecade_verification', [
            'participantCount' => 16,
            'activeNumbers' => 25,
            'lastUpdated' => Carbon::now(),
            'drawDate' => Carbon::create(2026, 1, 24, 12, 0, 0),
            'igAccount' => '@sneakers_flash'
        ]);
    }
}
public function showOneDecade()
{
    try {
        // Get stats dari spreadsheet
        $stats = $this->getPromoStats();
        
        // Tambahkan baris ini untuk mendapatkan total verifikasi dari database
        $totalVerifications = DB::table('promo_onedecade_entries')
            ->where('is_verified', true)
            ->count();
        
        // Tetapkan tanggal pengundian
        $drawDate = Carbon::create(2026, 1, 24, 12, 0, 0);
        
        return view('frontend.promo.onedecade', [
            'participantCount' => $stats['participantCount'],
            'activeNumbers' => $stats['activeNumbers'],
            'totalVerifications' => $totalVerifications, // Tambahkan ini ke data view
            'lastUpdated' => $stats['lastUpdated'],
            'drawDate' => $drawDate,
            'igAccount' => '@sneakers_flash'
        ]);
    } catch (\Exception $e) {
        Log::error('Error in One Decade promo page: ' . $e->getMessage());
        
        // Fallback to default values
        return view('frontend.promo.onedecade', [
            'participantCount' => 16,
            'activeNumbers' => 25,
            'totalVerifications' => 0, // Tambahkan ini juga di bagian fallback
            'lastUpdated' => Carbon::now(),
            'drawDate' => Carbon::create(2026, 1, 24, 12, 0, 0),
            'igAccount' => '@sneakers_flash'
        ]);
    }
}
}