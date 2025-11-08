<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckSpreadsheetData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'promo:check-spreadsheet 
                            {undian_code : Kode undian yang akan dicek} 
                            {order_number : Nomor pesanan yang akan dicek} 
                            {platform : Platform yang akan dicek} 
                            {--save : Simpan data HTML ke file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memeriksa data dalam spreadsheet promo untuk validasi nomor undian';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $undianCode = $this->argument('undian_code');
        $orderNumber = $this->argument('order_number');
        $platform = $this->argument('platform');
        
        $this->info('===== Pemeriksaan Data Spreadsheet Promo =====');
        $this->info("Mencari data untuk:");
        $this->info("- Nomor Undian: {$undianCode}");
        $this->info("- Nomor Pesanan: {$orderNumber}");
        $this->info("- Platform: {$platform}");
        $this->newLine();
        
        // Langkah 1: Ambil data spreadsheet
        $this->info('Langkah 1: Mengambil data spreadsheet...');
        $spreadsheetData = $this->fetchSpreadsheetData();
        
        if (!$spreadsheetData) {
            $this->error('Gagal mengambil data spreadsheet!');
            return 1;
        }
        
        $this->info('Berhasil mengambil data spreadsheet (' . strlen($spreadsheetData) . ' bytes)');
        
        if ($this->option('save')) {
            $filePath = storage_path('app/spreadsheet_data_' . time() . '.html');
            file_put_contents($filePath, $spreadsheetData);
            $this->info("Data HTML disimpan ke file: {$filePath}");
        }
        $this->newLine();
        
        // Langkah 2: Analisis dasar - cek keberadaan string
        $this->info('Langkah 2: Cek keberadaan string dasar...');
        $undianCodeUpper = strtoupper($undianCode);
        $orderNumberUpper = strtoupper($orderNumber);
        $platformUpper = strtoupper($platform);
        
        $undianCount = substr_count($spreadsheetData, $undianCodeUpper);
        $orderCount = substr_count($spreadsheetData, $orderNumberUpper);
        $platformCount = substr_count($spreadsheetData, $platformUpper);
        
        $this->info("- Nomor Undian '{$undianCodeUpper}' ditemukan: {$undianCount} kali");
        $this->info("- Nomor Pesanan '{$orderNumberUpper}' ditemukan: {$orderCount} kali");
        $this->info("- Platform '{$platformUpper}' ditemukan: {$platformCount} kali");
        
        if ($undianCount === 0) {
            $this->error("MASALAH: Nomor Undian tidak ditemukan sama sekali dalam data!");
        }
        $this->newLine();
        
        // Langkah 3: Mencari baris dengan nomor undian
        $this->info('Langkah 3: Mencari baris dengan nomor undian...');
        $pattern = '/<tr[^>]*>.*?' . preg_quote($undianCodeUpper, '/') . '.*?<\/tr>/is';
        
        if (preg_match_all($pattern, $spreadsheetData, $rowMatches)) {
            $matchCount = count($rowMatches[0]);
            $this->info("Ditemukan {$matchCount} baris yang mengandung Nomor Undian '{$undianCodeUpper}'");
            
            // Periksa setiap baris yang ditemukan
            $fullMatchFound = false;
            
            foreach ($rowMatches[0] as $index => $rowHtml) {
                $this->info("--- Baris " . ($index+1) . " ---");
                
                // Cek nomor pesanan dalam baris ini
                $orderInRow = stripos($rowHtml, $orderNumberUpper) !== false;
                $this->info("  Nomor Pesanan '{$orderNumberUpper}' ada dalam baris: " . ($orderInRow ? 'Ya ✓' : 'Tidak ✗'));
                
                if ($orderInRow) {
                    // Extract cells
                    $cellPattern = '/<td[^>]*>(.*?)<\/td>/is';
                    if (preg_match_all($cellPattern, $rowHtml, $cellMatches)) {
                        $cells = array_map(function($cell) {
                            return trim(strip_tags($cell));
                        }, $cellMatches[1]);
                        
                        $this->info("  Sel-sel dalam baris ini:");
                        foreach ($cells as $cellIndex => $cellContent) {
                            if (!empty(trim($cellContent))) {
                                $this->info("    Sel " . ($cellIndex+1) . ": " . $cellContent);
                            }
                        }
                        
                        // Cek platform dalam sel
                        $platformFound = false;
                        $dataPlatform = '';
                        
                        foreach ($cells as $cellIndex => $cellContent) {
                            $cellContentUpper = strtoupper(trim($cellContent));
                            
                            // Cek platform
                            if ($platformUpper === 'SHOPEE' && (
                                $cellContentUpper === 'SHOPEE' || 
                                strpos($cellContentUpper, 'SHOPEE') !== false
                            )) {
                                $platformFound = true;
                                $dataPlatform = $cellContent;
                                break;
                            } 
                            else if ($cellContentUpper === $platformUpper) {
                                $platformFound = true;
                                $dataPlatform = $cellContent;
                                break;
                            }
                            // Cek mapping platform lainnya
                            else if (
                                ($cellContentUpper === 'TOKPED' && $platformUpper === 'TOKOPEDIA') ||
                                ($cellContentUpper === 'TOKOPEDIA' && $platformUpper === 'TOKPED') ||
                                ($cellContentUpper === 'WEB' && ($platformUpper === 'WEBSITE' || $platformUpper === 'WEBSITE SNEAKERS FLASH')) ||
                                ($platformUpper === 'WEB' && ($cellContentUpper === 'WEBSITE' || $cellContentUpper === 'WEBSITE SNEAKERS FLASH')) ||
                                ($cellContentUpper === 'WA DLL' && $platformUpper === 'WHATSAPP') ||
                                ($cellContentUpper === 'WHATSAPP' && $cellContentUpper === 'WA DLL') ||
                                ($cellContentUpper === 'USS' && $platformUpper === 'USS EVENT') ||
                                ($cellContentUpper === 'USS EVENT' && $cellContentUpper === 'USS') ||
                                ($cellContentUpper === 'BLIBI' && $platformUpper === 'BLIBLI') ||
                                ($cellContentUpper === 'BLIBLI' && $cellContentUpper === 'BLIBI')
                            ) {
                                $platformFound = true;
                                $dataPlatform = $cellContent;
                                break;
                            }
                        }
                        
                        if ($platformFound) {
                            $this->info("  Platform ditemukan: '{$dataPlatform}' ✓");
                            $this->info("  SUKSES: Semua data valid! ✓");
                            $fullMatchFound = true;
                        } else {
                            $this->error("  MASALAH: Platform '{$platform}' tidak ditemukan dalam baris! ✗");
                            $this->info("  Cek jika ada kesalahan penulisan atau variasi platform.");
                        }
                    } else {
                        $this->error("  MASALAH: Tidak dapat mengekstrak sel-sel dari baris! ✗");
                    }
                }
                
                $this->newLine();
            }
            
            if ($fullMatchFound) {
                $this->info("KESIMPULAN: Data lengkap ditemukan dan valid! ✓");
            } else if ($undianCount > 0 && $orderCount > 0) {
                $this->warn("KESIMPULAN: Nomor undian dan pesanan ada, tetapi tidak dalam baris yang sama atau platform tidak cocok.");
            } else {
                $this->error("KESIMPULAN: Tidak ditemukan kecocokan lengkap untuk data yang diberikan.");
            }
            
        } else {
            $this->error("Tidak ditemukan baris yang mengandung Nomor Undian '{$undianCodeUpper}'.");
            $this->info("Cek kemungkinan masalah:");
            $this->info("1. Kode undian tidak ada dalam spreadsheet");
            $this->info("2. Format HTML dari Google Spreadsheet berubah");
            $this->info("3. Kode undian mungkin ditulis dengan format berbeda");
        }
        
        // Langkah 4: Rekomendasi untuk validasi
        $this->newLine();
        $this->info('Langkah 4: Rekomendasi untuk fungsi validateUndianWithSpreadsheet...');
        
        if ($undianCount > 0) {
            $this->info("Rekomendasi: Gunakan pendekatan regex untuk parsing HTML langsung");
            $this->info("1. Cari baris yang mengandung nomor undian");
            $this->info("2. Periksa nomor pesanan dan platform dalam baris yang sama");
            $this->info("3. Tambahkan toleransi untuk variasi platform (terutama SHOPEE)");
            
            $recommendedCode = <<<'CODE'
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
        
        // Pendekatan Regex: Cari langsung di HTML
        $undianCodeUpper = strtoupper($undianCode);
        $orderNumberUpper = strtoupper($orderNumber);
        $platformUpper = strtoupper($platform);
        
        // Cari baris dengan nomor undian
        $pattern = '/<tr[^>]*>.*?' . preg_quote($undianCodeUpper, '/') . '.*?<\/tr>/is';
        
        if (preg_match_all($pattern, $spreadsheetData, $rowMatches)) {
            // Periksa setiap baris yang berisi nomor undian
            foreach ($rowMatches[0] as $rowHtml) {
                // Cek apakah nomor pesanan ada di baris ini
                if (stripos($rowHtml, $orderNumberUpper) !== false) {
                    // Extract cells dari baris ini
                    $cellPattern = '/<td[^>]*>(.*?)<\/td>/is';
                    if (preg_match_all($cellPattern, $rowHtml, $cellMatches)) {
                        $cells = array_map('strip_tags', $cellMatches[1]);
                        
                        // Cek platform dalam baris ini
                        foreach ($cells as $cellContent) {
                            $cellContentUpper = strtoupper(trim($cellContent));
                            
                            // SHOPEE - verifikasi fleksibel
                            if ($platformUpper === 'SHOPEE' && 
                                (strpos($cellContentUpper, 'SHOPEE') !== false)) {
                                return [
                                    'success' => true,
                                    'message' => 'Data valid',
                                    'data' => [
                                        'NOMOR UNDIAN' => $undianCode,
                                        'No Pesanan' => $orderNumber,
                                        'Market Place' => $cellContent,
                                        'Kolom' => 'NOMOR UNDIAN'
                                    ]
                                ];
                            }
                            
                            // Platform lain, pencocokan dan mapping
                            if ($cellContentUpper === $platformUpper ||
                                ($cellContentUpper === 'TOKPED' && $platformUpper === 'TOKOPEDIA') ||
                                ($cellContentUpper === 'TOKOPEDIA' && $platformUpper === 'TOKPED') ||
                                ($cellContentUpper === 'WEB' && ($platformUpper === 'WEBSITE' || $platformUpper === 'WEBSITE SNEAKERS FLASH')) ||
                                ($platformUpper === 'WEB' && ($cellContentUpper === 'WEBSITE' || $cellContentUpper === 'WEBSITE SNEAKERS FLASH')) ||
                                ($cellContentUpper === 'WA DLL' && $platformUpper === 'WHATSAPP') ||
                                ($cellContentUpper === 'WHATSAPP' && $cellContentUpper === 'WA DLL') ||
                                ($cellContentUpper === 'USS' && $platformUpper === 'USS EVENT') ||
                                ($cellContentUpper === 'USS EVENT' && $cellContentUpper === 'USS') ||
                                ($cellContentUpper === 'BLIBI' && $platformUpper === 'BLIBLI') ||
                                ($cellContentUpper === 'BLIBLI' && $cellContentUpper === 'BLIBI')
                            ) {
                                return [
                                    'success' => true,
                                    'message' => 'Data valid',
                                    'data' => [
                                        'NOMOR UNDIAN' => $undianCode,
                                        'No Pesanan' => $orderNumber,
                                        'Market Place' => $cellContent,
                                        'Kolom' => 'NOMOR UNDIAN'
                                    ]
                                ];
                            }
                        }
                    }
                }
            }
            
            // Jika sampai di sini, nomor undian ditemukan tetapi tidak ada kecocokan
            return [
                'success' => false,
                'message' => 'Nomor pesanan tidak sesuai dengan nomor undian. Silakan periksa kembali.',
            ];
        }
        
        // Jika sampai di sini, berarti tidak ada nomor undian yang ditemukan
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
CODE;
            
            $this->info("Kode yang direkomendasikan:");
            $this->line($recommendedCode);
        } else {
            $this->warn("Nomor undian tidak ditemukan dalam data. Tidak dapat memberikan rekomendasi spesifik.");
        }
        
        return 0;
    }
    
    /**
     * Fetch spreadsheet data
     */
    protected function fetchSpreadsheetData()
    {
        $spreadsheetId = env('ONEDECADE_SPREADSHEET_ID', '1XabuSo1KrmT-smIZNKKmvaFq1_3X4kvh7sHvA6K7TKI');
        $url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/edit?gid=1207095869&rand=" . time();
        
        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Cache-Control' => 'no-cache, no-store, must-revalidate'
                ])
                ->get($url);
            
            return $response->successful() ? $response->body() : null;
        } catch (\Exception $e) {
            $this->error("Error fetching spreadsheet: " . $e->getMessage());
            return null;
        }
    }
}