<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Verifikasi - SneakerFlash</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: white;
            margin: 0;
            padding: 0;
        }

        .result-container {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem 1rem;
            text-align: center;
        }

        .result-box {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .result-header {
            background-color: white;
            padding: 2rem;
            border-bottom: 1px solid #eee;
        }

        .result-data {
            font-size: 3rem;
            font-weight: bold;
            color: #f44336;
            margin: 1.5rem 0;
        }

        .promo-section {
            background-color: #f44336;
            color: white;
            padding: 2rem;
        }

        .time-section {
            background-color: white;
            padding: 1.5rem;
            color: #777;
            font-size: 0.9rem;
        }

        .footer {
            text-align: center;
            color: #888;
            padding: 1rem;
            font-size: 0.8rem;
            margin-top: 2rem;
        }
        
        .btn-white {
            background-color: white;
            color: #f44336;
            border: none;
            border-radius: 4px;
            padding: 10px 20px;
            font-weight: bold;
            cursor: pointer;
            display: inline-block;
            margin-top: 1rem;
        }
        
        .btn-blue {
            background-color: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 16px;
            font-size: 0.9rem;
            cursor: pointer;
            display: inline-block;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="result-container">
        <div class="result-box">
            <!-- Header dengan Statistik -->
            <div class="result-header">
                <h1 class="text-2xl font-bold text-gray-900">Total Peserta Undian Saat Ini</h1>
                
                <div class="result-data">
                    8 Peserta
                </div>
                
                <p class="text-gray-700">
                    18 Nomor Undian Aktif
                </p>
            </div>
            
            <!-- Tanggal Pengundian -->
            <div class="promo-section">
                <h2 class="text-xl font-bold mb-2">
                    Pengundian Langsung
                </h2>
                <p class="text-xl font-bold">
                    24 Januari 2026, 12:00 WIB
                </p>
                
                <p class="mt-3">
                    Tonton di IG Live @sneakers_flash dan jadi saksi siapa yang menang!
                </p>
                
                <div>
                    <button class="btn-white">
                        Set Reminder
                    </button>
                </div>
            </div>
            
            <!-- Timestamp -->
            <div class="time-section">
                Terakhir diperbarui: {{ now()->format('d F Y') }} pukul {{ now()->format('H:i') }}
                
                <div>
                    <button class="btn-blue">
                        Refresh Data
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer Simple -->
    <div class="footer">
        <p>© 2025 SneakerFlash. All rights reserved.</p>
    </div>
</body>
</html>