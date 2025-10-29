<?php
   // Ini adalah file debug standalone
   
   require 'vendor/autoload.php';
   
   use Illuminate\Support\Facades\Http;
   use Illuminate\Support\Facades\Log;
   
   // Ambil data spreadsheet
   function fetchSpreadsheetData() {
       $spreadsheetId = '1XabuSo1KrmT-smIZNKKmvaFq1_3X4kvh7sHvA6K7TKI';
       try {
           $url = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/edit?gid=1207095869&rand=" . time();
           
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
           echo "Error: " . $e->getMessage() . "\n";
           return null;
       }
   }
   
   echo "Mengambil data spreadsheet...\n";
   $rawData = fetchSpreadsheetData();
   echo "Hasil: " . (empty($rawData) ? "KOSONG" : "OK (" . strlen($rawData) . " bytes)") . "\n";
   
   // Simpan HTML ke file untuk inspeksi
   if (!empty($rawData)) {
       file_put_contents('spreadsheet_data.html', $rawData);
       echo "Data disimpan ke spreadsheet_data.html\n";
   }