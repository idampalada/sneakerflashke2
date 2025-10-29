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
    public function showOneDecade()
    {
        try {
            // Get stats dari spreadsheet
            $stats = $this->getPromoStats();
            
            // Tetapkan tanggal pengundian
            $drawDate = Carbon::create(2026, 1, 24, 12, 0, 0);
            
            return view('frontend.promo.onedecade', [
                'participantCount' => $stats['participantCount'],
                'activeNumbers' => $stats['activeNumbers'],
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
                'lastUpdated' => Carbon::now(),
                'drawDate' => Carbon::create(2026, 1, 24, 12, 0, 0),
                'igAccount' => '@sneakers_flash'
            ]);
        }
    }
    
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
            'contact_info' => 'required|string|max:100',
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
                // Jika validasi gagal, tampilkan notifikasi error
                session()->flash('verification_status', 'error');
                session()->flash('error_message', $validationResult['message']);
                return back()->withInput();
            }
            
            // 2. Jika validasi berhasil, cek apakah data sudah ada di database
            if (Schema::hasTable('promo_onedecade_entries')) {
                $existingEntry = DB::table('promo_onedecade_entries')
                    ->where('undian_code', $request->undian_code)
                    ->where('order_number', $request->order_number)
                    ->first();
                    
                if ($existingEntry) {
                    // Jika data sudah ada, tampilkan notifikasi error
                    session()->flash('verification_status', 'error');
                    session()->flash('error_message', 'Kode tidak ditemukan atau sudah dipakai. Cek kembali ya.');
                    return back()->withInput();
                }
            }
            
            // 3. Simpan data ke database jika validasi berhasil dan data belum ada
            
            // Membersihkan contact info (email/no hp)
            $cleanContact = $request->contact_info;
            if (preg_match('/^[0-9+\s]+$/', $request->contact_info)) {
                // Jika berisi angka, anggap sebagai nomor telepon
                $cleanContact = preg_replace('/[^0-9+]/', '', $request->contact_info);
                
                // Jika nomor dimulai dengan 0, ubah ke format +62
                if (substr($cleanContact, 0, 1) === '0') {
                    $cleanContact = '+62' . substr($cleanContact, 1);
                }
            } else {
                // Jika email, trim dan lowercase
                $cleanContact = strtolower(trim($request->contact_info));
            }
            
            // Uppercase undian code
            $undianCode = strtoupper(trim($request->undian_code));
            
            // Generate a unique entry number
            $year = date('Y');
            $random = mt_rand(100000, 999999);
            $entryNumber = "SF-{$year}-{$random}";
            
            // Check if entry number already exists and regenerate if needed
            if (Schema::hasTable('promo_onedecade_entries')) {
                while (DB::table('promo_onedecade_entries')->where('entry_number', $entryNumber)->exists()) {
                    $random = mt_rand(100000, 999999);
                    $entryNumber = "SF-{$year}-{$random}";
                }
            }
            
            // Insert data jika tabel sudah ada
            if (Schema::hasTable('promo_onedecade_entries')) {
                DB::table('promo_onedecade_entries')->insert([
                    'undian_code' => $undianCode,
                    'order_number' => $request->order_number,
                    'platform' => $request->platform,
                    'contact_info' => $cleanContact,
                    'entry_number' => $entryNumber,
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
            
            // Return with AJAX if request is AJAX
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Nomor undian terverifikasi. Good luck, peeps!'
                ]);
            }
            
            // Redirect to same page for page refresh
            return back();
            
        } catch (\Exception $e) {
            Log::error('Error in One Decade promo verification: ' . $e->getMessage(), [
                'undian_code' => $request->undian_code ?? 'not provided',
                'order_number' => $request->order_number ?? 'not provided',
                'platform' => $request->platform ?? 'not provided'
            ]);
            
            // Set error flash
            session()->flash('verification_status', 'error');
            session()->flash('error_message', 'Kode tidak ditemukan atau sudah dipakai. Cek kembali ya.');
            
            // Return with AJAX if request is AJAX
            if ($request->ajax()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Kode tidak ditemukan atau sudah dipakai. Cek kembali ya.'
                ]);
            }
            
            return back()->withInput();
        }
    }
    
    /**
     * Validasi nomor undian dengan data di spreadsheet
     */
    private function validateUndianWithSpreadsheet($undianCode, $orderNumber, $platform)
    {
        try {
            // Bersihkan input
            $undianCode = trim($undianCode);
            $orderNumber = trim($orderNumber);
            $platform = trim(strtolower($platform));
            
            // Map nilai platform di form ke nilai yang ada di spreadsheet
            $platformMap = [
                'website' => 'SHOPEE', // Adjust these mappings based on your spreadsheet data
                'shopee' => 'SHOPEE',
                'tiktok' => 'TIKTOK',
                'tokopedia' => 'SHOPEE', // Adjust these mappings based on your spreadsheet data
                'blibli' => 'BLIBLI',
                'whatsapp' => 'WA DLL',
                'uss_event' => 'WEB'
            ];
            
            // Convert platform dari form ke nilai di spreadsheet
            $spreadsheetPlatform = $platformMap[$platform] ?? strtoupper($platform);
            
            // Ambil data dari spreadsheet
            $rawData = $this->fetchSpreadsheetData();
            
            if (empty($rawData)) {
                Log::error('Failed to fetch spreadsheet data for validation');
                return [
                    'success' => false,
                    'message' => 'Tidak dapat memvalidasi data. Silakan coba lagi nanti.'
                ];
            }
            
            // Parse data spreadsheet untuk mendapatkan struktur data
            $data = $this->parseSpreadsheetData($rawData);
            
            // Cari data yang cocok dengan input
            foreach ($data as $row) {
                // Periksa apakah Nomor Undian dan Nomor Pesanan cocok
                $rowUndianCode = (string)($row['NOMOR UNDIAN'] ?? '');
                $rowOrderNumber = (string)($row['No Pesanan'] ?? '');
                $rowPlatform = (string)($row['Market Place'] ?? '');
                
                // Log untuk debugging
                Log::debug('Checking row: ', [
                    'rowUndianCode' => $rowUndianCode,
                    'inputUndianCode' => $undianCode,
                    'rowOrderNumber' => $rowOrderNumber,
                    'inputOrderNumber' => $orderNumber,
                    'rowPlatform' => $rowPlatform,
                    'inputPlatform' => $spreadsheetPlatform
                ]);
                
                // Pengecekan berdasarkan Nomor Undian & Nomor Pesanan
                if ($rowUndianCode == $undianCode && $rowOrderNumber == $orderNumber) {
                    // Cek platform juga cocok
                    if ($rowPlatform == $spreadsheetPlatform) {
                        return [
                            'success' => true,
                            'message' => 'Data valid',
                            'data' => $row
                        ];
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Platform tidak sesuai dengan nomor undian dan pesanan.'
                        ];
                    }
                }
            }
            
            // Jika tidak ada data yang cocok
            return [
                'success' => false,
                'message' => 'Nomor Undian atau Nomor Pesanan tidak ditemukan.'
            ];
            
        } catch (\Exception $e) {
            Log::error('Error validating data with spreadsheet: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat validasi data. Silakan coba lagi nanti.'
            ];
        }
    }
    
    /**
     * Ambil data spreadsheet
     */
    private function fetchSpreadsheetData()
    {
        try {
            // URL untuk akses langsung HTML
            $url = "https://docs.google.com/spreadsheets/d/{$this->spreadsheetId}/edit?gid=1207095869&rand=" . time();
            
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Cache-Control' => 'no-cache, no-store, must-revalidate'
                ])
                ->get($url);
            
            if ($response->successful()) {
                return $response->body();
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Error fetching spreadsheet data: ' . $e->getMessage());
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
    public function getPromoStats()
    {
        try {
            // Ambil data dari spreadsheet
            $rawContent = $this->fetchSpreadsheetData();
            
            if (empty($rawContent)) {
                return [
                    'participantCount' => 16,
                    'activeNumbers' => 25,
                    'lastUpdated' => now()
                ];
            }
            
            // Parse HTML content
            $dom = new \DOMDocument();
            @$dom->loadHTML($rawContent);
            $tables = $dom->getElementsByTagName('table');
            
            if ($tables->length === 0) {
                return [
                    'participantCount' => 16,
                    'activeNumbers' => 25,
                    'lastUpdated' => now()
                ];
            }
            
            $table = $tables->item(0);
            $rows = $table->getElementsByTagName('tr');
            
            // Dapatkan indeks kolom JUMLAH KUPON (baris ke-4, kolom I atau indeks 8)
            $jumlahKuponIndex = 8; // Default berdasarkan tangkapan layar
            
            // Hitung jumlah peserta dan total kupon
            $participantCount = 0;
            $totalKupon = 0;
            
            // Mulai dari baris setelah header (baris ke-5, indeks 4)
            for ($i = 4; $i < $rows->length; $i++) {
                $row = $rows->item($i);
                $cells = $row->getElementsByTagName('td');
                
                // Skip jika baris tidak memiliki cukup kolom
                if ($cells->length <= $jumlahKuponIndex) {
                    continue;
                }
                
                // Cek jika baris berisi data (jika nomor pesanan tidak kosong)
                $noPesanan = trim($cells->item(4)->textContent); // Kolom E (indeks 4)
                
                if (!empty($noPesanan) && $noPesanan !== '0' && $noPesanan !== '-') {
                    $participantCount++;
                    
                    // Tambahkan nilai JUMLAH KUPON
                    $jumlahKupon = trim($cells->item($jumlahKuponIndex)->textContent);
                    if (is_numeric($jumlahKupon) && (int)$jumlahKupon > 0) {
                        $totalKupon += (int)$jumlahKupon;
                    }
                }
            }
            
            // Jika perhitungan gagal, gunakan nilai default
            if ($participantCount === 0 || $totalKupon === 0) {
                return [
                    'participantCount' => 16,
                    'activeNumbers' => 25,
                    'lastUpdated' => now()
                ];
            }
            
            return [
                'participantCount' => $participantCount,
                'activeNumbers' => $totalKupon,
                'lastUpdated' => now()
            ];
            
        } catch (\Exception $e) {
            Log::error('Error calculating promo stats: ' . $e->getMessage());
            
            return [
                'participantCount' => 16,
                'activeNumbers' => 25,
                'lastUpdated' => now()
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
}