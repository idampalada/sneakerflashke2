{{-- resources/views/frontend/promo/onedecade_finish.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Verification Result - SneakerFlash</title>

  <style>
    html, body {
      margin: 0;
      padding: 0;
      width: 100%;
      height: 100%;
      min-height: 100vh;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      background: #ffffff;
      display: flex;
      justify-content: center;
      align-items: center;
      flex-direction: column;
    }

    .result-container {
      text-align: center;
      max-width: 600px;
      padding: 30px;
    }

    .result-icon {
      width: 100px;
      height: 100px;
      margin-bottom: 20px;
      display: inline-block;
    }

    .result-title {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 15px;
      color: #28a745;
    }

    .result-message {
      font-size: 18px;
      line-height: 1.6;
      margin-bottom: 30px;
      color: #555;
      padding: 0 20px;
    }

    .entry-number {
      font-size: 20px;
      font-weight: bold;
      color: #333;
      margin-top: 20px;
      margin-bottom: 40px;
    }

    .error-title {
      color: #dc3545;
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 15px;
    }

    .home-button {
      display: inline-block;
      background-color: #03245b;
      color: white;
      font-weight: bold;
      padding: 12px 24px;
      border-radius: 8px;
      text-decoration: none;
      transition: all 0.3s ease;
      border: 2px solid #03245b;
    }

    .home-button:hover {
      background-color: white;
      color: #03245b;
    }
  </style>
</head>
<body>
  <div class="result-container">
    @if(session('verification_status') === 'success')
      <div class="result-icon">
        <!-- Success Icon - Green Checkmark (SVG Inline) -->
        <svg viewBox="0 0 24 24" width="100" height="100">
          <circle cx="12" cy="12" r="11" fill="white" stroke="#28a745" stroke-width="2"/>
          <path d="M7 13l3 3 7-7" fill="none" stroke="#28a745" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <h1 class="result-title">Verifikasi Berhasil!</h1>
      <p class="result-message">
        Nomor undian Anda telah berhasil diverifikasi. Terima kasih telah berpartisipasi dalam 
        promo One Decade SneakersFlash. Semoga beruntung!
      </p>
      
      @if(session('undian_code'))
      <div class="entry-number">
        Nomor Undian Anda: {{ session('undian_code') }}
      </div>
      @endif
    @else
      <div class="result-icon">
        <!-- Error Icon - Red X (SVG Inline) -->
        <svg viewBox="0 0 24 24" width="100" height="100">
          <circle cx="12" cy="12" r="11" fill="white" stroke="#dc3545" stroke-width="2"/>
          <path d="M8 8l8 8" stroke="#dc3545" stroke-width="3" stroke-linecap="round"/>
          <path d="M16 8l-8 8" stroke="#dc3545" stroke-width="3" stroke-linecap="round"/>
        </svg>
      </div>
      <h1 class="error-title">Verifikasi Gagal</h1>
      
      @if(str_contains(session('error_message'), 'Nomor pesanan tidak sesuai'))
        <p class="result-message">
          Nomor pesanan tidak sesuai dengan nomor undian. Silakan periksa kembali data yang Anda masukkan.
        </p>
      @elseif(str_contains(session('error_message'), 'sudah dipakai'))
        <p class="result-message">
          Kode ini sudah pernah digunakan. Setiap kode undian hanya dapat diverifikasi satu kali.
        </p>
      @else
        <p class="result-message">
          {{ session('error_message') ?? 'Kode tidak ditemukan atau sudah dipakai. Silakan cek kembali kode undian dan nomor pesanan Anda, atau hubungi customer service kami untuk bantuan.' }}
        </p>
      @endif
    @endif

    <a href="{{ route('promo.onedecade') }}" class="home-button">Kembali ke Halaman Utama</a>
  </div>
</body>
</html>