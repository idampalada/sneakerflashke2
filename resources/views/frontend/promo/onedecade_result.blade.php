<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>One Decade Promo - SneakerFlash</title>
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #03245b;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .result-container {
            width: 100%;
            max-width: 800px;
            padding: 20px;
        }
        
        .result-box {
            background-color: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            text-align: center;
        }
        
        h1 {
            font-size: 28px;
            margin-top: 0;
            margin-bottom: 30px;
            color: #333;
        }
        
        .result-data {
            font-size: 48px;
            font-weight: bold;
            color: #e32119;
            margin: 20px 0;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .entry-number {
            font-size: 32px;
            font-weight: bold;
            color: #e32119;
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            border: 3px dashed #e32119;
            border-radius: 8px;
            background-color: #fff9f9;
        }
        
        .promo-section {
            margin: 40px 0;
            padding: 25px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        
        .promo-section h2 {
            font-size: 24px;
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
        }
        
        .time-section {
            color: #6c757d;
            font-size: 14px;
            margin-top: 30px;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            color: white;
            padding: 15px;
        }
        
        .btn-white, .btn-blue {
            padding: 12px 25px;
            border-radius: 30px;
            border: none;
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-white {
            background-color: white;
            color: #e32119;
            border: 1px solid #e32119;
        }
        
        .btn-blue {
            background-color: #0066cc;
            color: white;
            border: 1px solid #0066cc;
        }
        
        .btn-white:hover, .btn-blue:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        a.back-button {
            color: white;
            text-decoration: none;
            font-weight: bold;
            margin-top: 30px;
            display: inline-block;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="result-container">
        @if(session('success'))
        <div class="success-message">
            {{ session('success') }}
        </div>
        @endif
        
        @if(session('entry_number'))
        <div class="entry-number">
            Nomor Undian: {{ session('entry_number') }}
        </div>
        @endif
        
        <div class="result-box">
            <!-- Header dengan Statistik -->
            <h1>Total Peserta Undian Saat Ini</h1>
            
            <div class="result-data">
                {{ $participantCount }} Peserta
            </div>
            
            <p>
                {{ $activeNumbers }} Nomor Undian Aktif
            </p>
            
            <!-- Tanggal Pengundian -->
            <div class="promo-section">
                <h2>
                    Pengundian Langsung
                </h2>
                <p style="font-size: 20px; font-weight: bold;">
                    24 Januari 2026, 12:00 WIB
                </p>
                
                <p style="margin-top: 20px;">
                    Tonton di IG Live @sneakers_flash dan jadi saksi siapa yang menang!
                </p>
                
                <div>
                    <a href="https://www.instagram.com/sneakers_flash/" target="_blank">
                        <button class="btn-white">
                            Ikuti Instagram Kami
                        </button>
                    </a>
                </div>
            </div>
            
            <!-- Timestamp -->
            <div class="time-section">
                Terakhir diperbarui: {{ $lastUpdated->format('d F Y') }} pukul {{ $lastUpdated->format('H:i') }}
                
                <div>
                    <form action="{{ route('promo.onedecade.result') }}" method="GET">
                        <button type="submit" class="btn-blue">
                            Refresh Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Back Button -->
    <a href="{{ route('promo.onedecade') }}" class="back-button">
        « Kembali ke Halaman Promo
    </a>
    
    <!-- Footer Simple -->
    <div class="footer">
        <p>© {{ date('Y') }} SneakerFlash. All rights reserved.</p>
    </div>
</body>
</html>