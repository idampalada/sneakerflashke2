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
use App\Exports\PromoOneDecadeExport;
use Maatwebsite\Excel\Facades\Excel;


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
            
            // CRITICAL: Return early to prevent database insertion for failed validation
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
                
                // CRITICAL: Return early to prevent database insertion for duplicate data
                return redirect()->route('promo.onedecade.finish');
            }
        }
        
        // 3. Simpan data ke database HANYA jika validasi berhasil dan data belum ada
        
        // Membersihkan contact info (nomor handphone saja)
        $cleanContact = preg_replace('/[^0-9+]/', '', $request->contact_info);
        
        // Jika nomor dimulai dengan 0, ubah ke format +62
        if (substr($cleanContact, 0, 1) === '0') {
            $cleanContact = '+62' . substr($cleanContact, 1);
        }
        
        // Membuat nomor undian acak dengan format SF-DKD-XXXX
        // Ini dikomentari karena menggunakan nomor undian dari input, bukan generate baru
        // $randomCode = 'SF-DKD-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Simpan data entry ke database
        try {
            // CRITICAL: Pastikan tabel ada sebelum mencoba menyisipkan data
            if (!Schema::hasTable('promo_onedecade_entries')) {
                // Jika tabel belum ada, buat dulu
                $this->migrateOneDecadeTable();
            }
            
            $entryId = DB::table('promo_onedecade_entries')->insertGetId([
                'undian_code' => $request->undian_code,
                'order_number' => $request->order_number,
                'platform' => $request->platform,
                'contact_info' => $cleanContact,
                'is_verified' => true,
                'verified_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Log untuk debugging
            \Log::info('Entry disimpan ke database', [
                'entry_id' => $entryId,
                'undian_code' => $request->undian_code
            ]);
            
            // Set flash data untuk halaman sukses
            session()->flash('verification_status', 'success');
            session()->flash('undian_code', $request->undian_code);
            
            // Redirect ke halaman berhasil
            return redirect()->route('promo.onedecade.finish');
            
        } catch (\Exception $e) {
            // Log error
            \Log::error('Error menyimpan data undian: ' . $e->getMessage(), [
                'undian_code' => $request->undian_code,
                'order_number' => $request->order_number
            ]);
            
            // Set flash error
            session()->flash('verification_status', 'error');
            session()->flash('error_message', 'Terjadi kesalahan saat menyimpan data. Silakan coba lagi nanti.');
            
            // Redirect ke halaman error
            return redirect()->route('promo.onedecade.finish');
        }
        
    } catch (\Exception $e) {
        // Log error untuk validasi
        \Log::error('Error proses verifikasi: ' . $e->getMessage(), [
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
        // Normalisasi platform untuk pengecekan USS EVENT
        $normalizedPlatform = str_replace(['_', '-'], ' ', strtoupper(trim($platform)));
        
        // Cek apakah platform adalah USS EVENT (dengan berbagai variasi penulisan)
        if (in_array($normalizedPlatform, ['USS EVENT', 'USSEVENT', 'USS-EVENT', 'USS_EVENT'])) {
            \Log::info('Validasi diloloskan karena platform USS EVENT', [
                'undianCode' => $undianCode,
                'orderNumber' => $orderNumber,
                'normalizedPlatform' => $normalizedPlatform
            ]);
            
            return [
                'success' => true,
                'message' => 'Data valid',
                'data' => [
                    'NOMOR UNDIAN' => $undianCode,
                    'No Pesanan' => $orderNumber,
                    'Market Place' => 'USS EVENT',
                    'Kolom' => 'Auto-approved'
                ]
            ];
        }
        
        // Ambil data spreadsheet
        $spreadsheetData = $this->fetchSpreadsheetData();
        
        if (empty($spreadsheetData)) {
            return [
                'success' => false,
                'message' => 'Tidak dapat mengakses data validasi. Silakan coba lagi nanti.',
            ];
        }
        
        \Log::info('Spreadsheet data diperoleh', [
            'size' => strlen($spreadsheetData)
        ]);
        
        // Cari semua baris dalam tabel
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/is', $spreadsheetData, $allRowMatches);
        $allRows = $allRowMatches[0] ?? [];
        
        // Deteksi baris header (biasanya berisi "NOMOR UNDIAN", "No Pesanan", "Market Place")
        $headerRow = null;
        $headerCells = [];
        $noPesananIndex = null;
        $platformIndex = null;
        $nomorUndianIndex = null;
        $jumlahKuponIndex = null; // TAMBAHAN: untuk kolom JUMLAH KUPON
        
        // Cari baris header (biasanya dalam 12 baris pertama)
        for ($i = 0; $i < min(12, count($allRows)); $i++) {
            preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $allRows[$i], $cellMatches);
            $cells = array_map(function($cell) {
                return trim(strip_tags($cell));
            }, $cellMatches[1] ?? []);
            
            // Periksa apakah ini adalah baris header
            $isHeaderRow = false;
            foreach ($cells as $j => $cell) {
                $cellUpper = strtoupper($cell);
                if (strpos($cellUpper, 'NOMOR UNDIAN') !== false || 
                    strpos($cellUpper, 'NO PESANAN') !== false || 
                    strpos($cellUpper, 'MARKET PLACE') !== false ||
                    strpos($cellUpper, 'JUMLAH KUPON') !== false) { // TAMBAHAN
                    $isHeaderRow = true;
                    
                    // Identifikasi indeks kolom penting
                    if (strpos($cellUpper, 'NOMOR UNDIAN') !== false) {
                        $nomorUndianIndex = $j;
                    } else if (strpos($cellUpper, 'NO PESANAN') !== false) {
                        $noPesananIndex = $j;
                    } else if (strpos($cellUpper, 'MARKET PLACE') !== false || strpos($cellUpper, 'MARKETPLACE') !== false) {
                        $platformIndex = $j;
                    } else if (strpos($cellUpper, 'JUMLAH KUPON') !== false) { // TAMBAHAN
                        $jumlahKuponIndex = $j;
                    }
                }
            }
            
            if ($isHeaderRow) {
                $headerRow = $i;
                $headerCells = $cells;
                break;
            }
        }
        
        // Fallback jika header tidak ditemukan (gunakan default)
        if ($nomorUndianIndex === null) {
            $nomorUndianIndex = 8; // Default: Kolom I
            \Log::warning('Nomor Undian column not identified, using default index 8');
        }
        
        if ($noPesananIndex === null) {
            $noPesananIndex = 4; // Default: Kolom E
            \Log::warning('No Pesanan column not identified, using default index 4');
        }
        
        if ($platformIndex === null) {
            $platformIndex = 2; // Default: Kolom C
            \Log::warning('Platform column not identified, using default index 2');
        }
        
        if ($jumlahKuponIndex === null) { // TAMBAHAN
            $jumlahKuponIndex = 7; // Default: Kolom H
            \Log::warning('JUMLAH KUPON column not identified, using default index 7');
        }
        
        \Log::info('Kolom teridentifikasi', [
            'nomorUndianIndex' => $nomorUndianIndex,
            'noPesananIndex' => $noPesananIndex,
            'platformIndex' => $platformIndex,
            'jumlahKuponIndex' => $jumlahKuponIndex // TAMBAHAN
        ]);
        
        // Uppercase untuk normalisasi
        $undianCodeUpper = strtoupper($undianCode);
        $orderNumberUpper = strtoupper($orderNumber);
        $platformUpper = strtoupper($platform);
        
        // Temukan baris dengan undian code yang cocok
        $matchingRow = null;
        $matchingCells = [];
        
        // Mulai pencarian dari baris setelah header (jika header ditemukan)
        $startIndex = ($headerRow !== null) ? $headerRow + 1 : 0;
        
        // MODIFIKASI: Daftar kolom yang akan diperiksa untuk nomor undian (I, J, K, L)
        $nomorUndianIndices = [$nomorUndianIndex, 9, 10, 11]; // 8 (I), 9 (J), 10 (K), 11 (L)
        
        for ($i = $startIndex; $i < count($allRows); $i++) {
            preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $allRows[$i], $cellMatches);
            $cells = array_map(function($cell) {
                return trim(strip_tags($cell));
            }, $cellMatches[1] ?? []);
            
            // Pastikan array cells cukup panjang untuk mencakup semua indeks kolom
            if (count($cells) <= max($nomorUndianIndices[0], $noPesananIndex, $platformIndex)) {
                continue;
            }
            
            // MODIFIKASI: Periksa semua kolom nomor undian potensial (I, J, K, L)
            $foundMatch = false;
            $matchingNomorUndianIndex = null;
            
            foreach ($nomorUndianIndices as $index) {
                if (isset($cells[$index])) {
                    $rowUndianCode = strtoupper(trim($cells[$index]));
                    
                    // Jika nomor undian cocok di salah satu kolom
                    if ($rowUndianCode === $undianCodeUpper) {
                        $foundMatch = true;
                        $matchingNomorUndianIndex = $index;
                        break;
                    }
                }
            }
            
            // Jika nomor undian cocok di salah satu kolom
            if ($foundMatch) {
                $matchingRow = $i;
                $matchingCells = $cells;
                
                // Log hasil
                \Log::info('Baris dengan nomor undian ditemukan', [
                    'rowIndex' => $i,
                    'columnIndex' => $matchingNomorUndianIndex,
                    'undianCodeInRow' => $undianCodeUpper
                ]);
                
                // Validasi nomor pesanan pada baris ini
                $rowOrderNumber = strtoupper(trim($cells[$noPesananIndex] ?? ''));
                
                \Log::info('Memeriksa nomor pesanan di baris yang sama', [
                    'rowOrderNumber' => $rowOrderNumber,
                    'inputOrderNumber' => $orderNumberUpper
                ]);
                
                // Jika nomor pesanan tidak cocok
                if ($rowOrderNumber !== $orderNumberUpper) {
                    \Log::warning('Nomor pesanan tidak cocok dengan baris', [
                        'rowOrderNumber' => $rowOrderNumber,
                        'inputOrderNumber' => $orderNumberUpper
                    ]);
                    
                    return [
                        'success' => false,
                        'message' => 'Nomor pesanan tidak sesuai dengan nomor undian. Silakan periksa kembali.',
                    ];
                }
                
                // Jika sampai di sini, nomor pesanan cocok - periksa platform
                $rowPlatform = trim($cells[$platformIndex] ?? '');
                $rowPlatformUpper = strtoupper($rowPlatform);
                
                \Log::info('Memeriksa platform', [
                    'rowPlatform' => $rowPlatform,
                    'inputPlatform' => $platform
                ]);
                
                // Cek kecocokan platform
                $platformMatch = false;
                
                // Pencocokan langsung
                if ($rowPlatformUpper === $platformUpper) {
                    $platformMatch = true;
                    \Log::info('Platform cocok persis');
                }
                // Pengecekan untuk '(ISI MANUAL)' atau '(ISI MANUAL)&QUOT;'
                else if (
                    strpos($rowPlatformUpper, '(ISI MANUAL)') !== false || 
                    $rowPlatformUpper === 'MANUAL' ||
                    $rowPlatformUpper === 'DIISI MANUAL'
                ) {
                    $platformMatch = true;
                    \Log::info('Platform cocok karena data platform adalah (isi manual)');
                }
                // Pencocokan mapping
                else if (
                    // Tokopedia ⟷ TOKPED
                    ($rowPlatformUpper === 'TOKPED' && $platformUpper === 'TOKOPEDIA') ||
                    ($rowPlatformUpper === 'TOKOPEDIA' && $platformUpper === 'TOKPED') ||
                    
                    // Website / Website Sneakers Flash ⟷ WEB
                    ($rowPlatformUpper === 'WEB' && ($platformUpper === 'WEBSITE' || $platformUpper === 'WEBSITE SNEAKERS FLASH')) ||
                    ($platformUpper === 'WEB' && ($rowPlatformUpper === 'WEBSITE' || $rowPlatformUpper === 'WEBSITE SNEAKERS FLASH')) ||
                    
                    // WhatsApp ⟷ WA DLL
                    ($rowPlatformUpper === 'WA DLL' && $platformUpper === 'WHATSAPP') ||
                    ($rowPlatformUpper === 'WHATSAPP' && $rowPlatformUpper === 'WA DLL') ||
                    
                    // USS Event ⟷ USS
                    ($rowPlatformUpper === 'USS' && $platformUpper === 'USS EVENT') ||
                    ($rowPlatformUpper === 'USS EVENT' && $rowPlatformUpper === 'USS') ||
                    
                    // BliBli ⟷ BLIBI
                    ($rowPlatformUpper === 'BLIBI' && $platformUpper === 'BLIBLI') ||
                    ($rowPlatformUpper === 'BLIBLI' && $rowPlatformUpper === 'BLIBI') ||
                    
                    // Shopee ⟷ SHOPEE OFFICIAL / SHOPPE / SHP
                    ($rowPlatformUpper === 'SHOPEE OFFICIAL' && $platformUpper === 'SHOPEE') ||
                    ($rowPlatformUpper === 'SHOPPE' && $platformUpper === 'SHOPEE') ||
                    ($rowPlatformUpper === 'SHP' && $platformUpper === 'SHOPEE') ||
                    ($platformUpper === 'SHOPEE OFFICIAL' && $rowPlatformUpper === 'SHOPEE') ||
                    ($platformUpper === 'SHOPPE' && $rowPlatformUpper === 'SHOPEE') ||
                    ($platformUpper === 'SHP' && $rowPlatformUpper === 'SHOPEE')
                ) {
                    $platformMatch = true;
                    \Log::info('Platform cocok via mapping');
                } else {
                    \Log::warning('Platform TIDAK cocok', [
                        'rowPlatform' => $rowPlatformUpper,
                        'inputPlatform' => $platformUpper
                    ]);
                }
                
                if ($platformMatch) {
                    \Log::info('Validasi berhasil - Platform cocok', [
                        'foundPlatform' => $rowPlatform
                    ]);
                    
                    // ========================================================================
                    // PERBAIKAN VALIDASI MULTI-KUPON
                    // Ganti validasi lama dengan validasi baru yang support multi-kupon
                    // ========================================================================
                    
                    if (Schema::hasTable('promo_onedecade_entries')) {
                        
                        // STEP 1: Ambil JUMLAH KUPON dari spreadsheet
                        $jumlahKuponValue = isset($matchingCells[$jumlahKuponIndex]) ? 
                                            (int)trim($matchingCells[$jumlahKuponIndex]) : 1;
                        
                        // Jika nilai 0 atau kosong, default ke 1
                        if ($jumlahKuponValue <= 0) {
                            $jumlahKuponValue = 1;
                        }
                        
                        // STEP 2: Hitung berapa kali nomor pesanan ini sudah redeem
                        $redeemCount = DB::table('promo_onedecade_entries')
                            ->where('order_number', $orderNumber)
                            ->count();
                        
                        \Log::info('Validasi JUMLAH KUPON', [
                            'order_number' => $orderNumber,
                            'jumlah_kupon_spreadsheet' => $jumlahKuponValue,
                            'redeem_count_sekarang' => $redeemCount,
                            'undian_code_input' => $undianCode
                        ]);
                        
                        // STEP 3: Cek apakah sudah mencapai batas JUMLAH KUPON
                        if ($redeemCount >= $jumlahKuponValue) {
                            \Log::warning('Batas JUMLAH KUPON tercapai', [
                                'order_number' => $orderNumber,
                                'jumlah_kupon_limit' => $jumlahKuponValue,
                                'sudah_redeem' => $redeemCount
                            ]);
                            
                            return [
                                'success' => false,
                                'message' => 'Nomor pesanan ini sudah mencapai batas maksimal redeem kupon (' . 
                                             $jumlahKuponValue . ' kupon). Anda sudah menggunakan semua kupon yang tersedia.',
                            ];
                        }
                        
                        // STEP 4: Cek apakah nomor undian yang SAMA sudah pernah digunakan
                        // (mencegah duplikasi nomor undian yang sama untuk nomor pesanan yang sama)
                        $duplicateUndian = DB::table('promo_onedecade_entries')
                            ->where('order_number', $orderNumber)
                            ->where('undian_code', $undianCode)
                            ->first();
                        
                        if ($duplicateUndian) {
                            \Log::warning('Nomor undian duplikat untuk nomor pesanan yang sama', [
                                'order_number' => $orderNumber,
                                'undian_code' => $undianCode
                            ]);
                            
                            return [
                                'success' => false,
                                'message' => 'Nomor undian ' . $undianCode . ' sudah pernah digunakan untuk nomor pesanan ini.',
                            ];
                        }
                        
                        // STEP 5: Validasi bahwa nomor undian yang diinput VALID 
                        // (ada di kolom I, J, K, atau L untuk baris ini)
                        $validUndianCodes = [];
                        $undianColumns = [8, 9, 10, 11]; // Kolom I, J, K, L (indeks 8, 9, 10, 11)
                        
                        foreach ($undianColumns as $colIndex) {
                            if (isset($matchingCells[$colIndex]) && !empty(trim($matchingCells[$colIndex]))) {
                                $validUndianCodes[] = strtoupper(trim($matchingCells[$colIndex]));
                            }
                        }
                        
                        \Log::info('Nomor undian valid di spreadsheet untuk baris ini', [
                            'order_number' => $orderNumber,
                            'valid_codes' => $validUndianCodes,
                            'input_code' => $undianCode
                        ]);
                        
                        // Cek apakah undian_code yang di-input ada di list valid codes
                        if (!in_array(strtoupper($undianCode), $validUndianCodes)) {
                            \Log::warning('Nomor undian tidak valid untuk nomor pesanan ini', [
                                'order_number' => $orderNumber,
                                'undian_code' => $undianCode,
                                'valid_codes' => $validUndianCodes
                            ]);
                            
                            return [
                                'success' => false,
                                'message' => 'Nomor undian tidak sesuai dengan nomor pesanan. Silakan periksa kembali.',
                            ];
                        }
                        
                        // STEP 6: Log informasi kupon yang tersisa
                        $sisaKupon = $jumlahKuponValue - $redeemCount - 1; // -1 karena ini akan di-redeem
                        \Log::info('Validasi sukses - Kupon dapat digunakan', [
                            'order_number' => $orderNumber,
                            'undian_code' => $undianCode,
                            'jumlah_kupon_total' => $jumlahKuponValue,
                            'sudah_digunakan' => $redeemCount,
                            'sisa_setelah_ini' => $sisaKupon
                        ]);
                    }
                    
                    // MODIFIKASI: Tambahkan informasi kolom yang digunakan untuk menemukan nomor undian
                    $columnLetters = ['I', 'J', 'K', 'L'];
                    $columnIndex = $matchingNomorUndianIndex - 8; // Konversi indeks ke posisi dalam array (8->0, 9->1, dst)
                    $columnLetter = isset($columnLetters[$columnIndex]) ? $columnLetters[$columnIndex] : '?';
                    
                    return [
                        'success' => true,
                        'message' => 'Data valid',
                        'data' => [
                            'NOMOR UNDIAN' => $undianCode,
                            'No Pesanan' => $orderNumber,
                            'Market Place' => $rowPlatform,
                            'Kolom' => 'NOMOR UNDIAN (Kolom ' . $columnLetter . ')'
                        ]
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => 'Platform pembelian tidak sesuai dengan data. Silakan periksa kembali.',
                    ];
                }
            }
        }
        
        // Jika sampai di sini, berarti tidak ada nomor undian yang ditemukan
        \Log::warning('Nomor undian tidak ditemukan di spreadsheet', [
            'undianCode' => $undianCode
        ]);
        
        return [
            'success' => false,
            'message' => 'Kode tidak ditemukan. Silakan periksa kembali.',
        ];
        
    } catch (\Exception $e) {
        \Log::error('Error validasi undian: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        
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
/**
 * Fetch spreadsheet data with support for more rows
 */
private function fetchSpreadsheetData()
{
    $spreadsheetId = env('ONEDECADE_SPREADSHEET_ID', '1XabuSo1KrmT-smIZNKKmvaFq1_3X4kvh7sHvA6K7TKI');
    
    try {
        // Metode 1: Coba akses sebagai CSV (dapat mengambil semua data tanpa batasan lazy loading)
        $csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv&gid=1207095869";
        
        $csvResponse = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Cache-Control' => 'no-cache, no-store, must-revalidate'
            ])
            ->get($csvUrl);
        
        if ($csvResponse->successful()) {
            $csvData = $csvResponse->body();
            
            // Jika berhasil mendapatkan data CSV, ubah ke format yang dapat diproses
            return $this->convertCsvToSearchableFormat($csvData);
        }
        
        // Jika CSV gagal, gunakan metode HTML sebagai fallback
        \Log::warning('CSV fetch failed, falling back to HTML method');
        
        // Metode 2: HTML dengan parameter tambahan untuk meminta lebih banyak baris
        $htmlUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/edit?gid=1207095869&range=A1:Z10000&rand=" . time();
        
        $response = Http::timeout(30) // tambahkan timeout lebih lama
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Cache-Control' => 'no-cache, no-store, must-revalidate'
            ])
            ->get($htmlUrl);
        
        if ($response->successful()) {
            return $response->body();
        }
        
        return null;
    } catch (\Exception $e) {
        \Log::error('Error fetching spreadsheet: ' . $e->getMessage());
        return null;
    }
}

/**
 * Convert CSV data to a searchable format
 */
private function convertCsvToSearchableFormat($csvData)
{
    if (empty($csvData)) {
        return null;
    }
    
    // Parse CSV
    $rows = array_map('str_getcsv', explode("\n", $csvData));
    if (count($rows) < 2) {
        \Log::error('CSV data parsing failed - not enough rows');
        return null;
    }
    
    // Dapatkan header (asumsi baris pertama adalah header)
    $headers = $rows[0];
    
    // Kolom yang kita cari
    $noPesananColIndex = null;
    $platformColIndex = null;
    $nomorUndianColIndices = [];
    
    // Identifikasi indeks kolom berdasarkan header
    foreach ($headers as $index => $header) {
        $headerUpper = strtoupper(trim($header));
        
        // Cari indeks untuk nomor pesanan
        if (in_array($headerUpper, ['NO PESANAN', 'NOMOR PESANAN', 'ORDER NUMBER'])) {
            $noPesananColIndex = $index;
        }
        // Cari indeks untuk platform
        else if (in_array($headerUpper, ['MARKETPLACE', 'MARKET PLACE', 'PLATFORM'])) {
            $platformColIndex = $index;
        }
        // Cari indeks untuk nomor undian
        else if (strpos($headerUpper, 'UNDIAN') !== false || strpos($headerUpper, 'NOMOR') !== false) {
            $nomorUndianColIndices[] = $index;
        }
    }
    
    // Jika tidak menemukan kolom yang diperlukan, log dan gunakan indeks default
    if ($noPesananColIndex === null) {
        \Log::warning('No Pesanan column not identified, using default index 4');
        $noPesananColIndex = 4; // Default: kolom E
    }
    
    if ($platformColIndex === null) {
        \Log::warning('Platform column not identified, using default index 2');
        $platformColIndex = 2; // Default: kolom C
    }
    
    if (empty($nomorUndianColIndices)) {
        \Log::warning('Nomor Undian columns not identified, using default indices 8-11');
        $nomorUndianColIndices = [8, 9, 10, 11]; // Default: kolom I, J, K, L
    }
    
    // Bangun HTML yang akan dapat diproses oleh kode validasi
    $html = '<table>';
    foreach ($rows as $i => $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
    
    return $html;
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
public function exportVerificationExcel()
{
    return Excel::download(
        new PromoOneDecadeExport,
        'data-verification-onedecade-' . now()->format('Ymd_His') . '.xlsx'
    );
}

}