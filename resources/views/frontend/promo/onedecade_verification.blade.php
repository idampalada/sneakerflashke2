{{-- resources/views/frontend/promo/onedecade_verification.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-TP5Z473');</script>
<!-- End Google Tag Manager -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Verifikasi Undian - SneakerFlash</title>

  <style>
    html, body {
      margin: 0;
      padding: 0;
      width: 100%;
      height: 100%;
      min-height: 100vh;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      overflow: hidden;
      background: #03245b;
    }

    .verification-page {
      position: fixed;
      inset: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      overflow: hidden;
    }

    /* 🔧 Background image — pastikan gambar versi lebar 2100x1080 */
    .verification-bg {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: contain;  /* biar gambar selalu utuh */
      object-position: center;
      z-index: 0;
      background-color: #03245b; /* fallback warna */
    }

    .verification-form {
      position: relative;
      z-index: 1;
      background-color: #fff;
      width: 90%;
      max-width: 500px;
      border-radius: 15px;
      padding: 30px;
      text-align: center;
      box-shadow: 0 5px 20px rgba(0,0,0,0.5);
      animation: fadeIn 0.5s ease-in-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .verification-form h1 {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 25px;
      color: #222;
    }

    .form-input {
      width: 100%;
      margin-bottom: 15px;
    }

    .form-input input,
    .form-input select {
      width: 100%;
      padding: 15px;
      border: 1px solid #ddd;
      border-radius: 50px;
      font-size: 16px;
      background-color: #f8f8f8;
      outline: none;
      box-sizing: border-box;
    }

    .form-input select {
      appearance: none;
      background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 15px center;
      background-size: 15px;
      padding-right: 40px;
    }

    .submit-btn {
      width: 100%;
      padding: 15px;
      background-color: #e32119;
      color: white;
      border: none;
      border-radius: 50px;
      font-size: 18px;
      font-weight: bold;
      cursor: pointer;
      text-transform: uppercase;
      margin-top: 10px;
      transition: all 0.3s;
      box-shadow: 0 4px 10px rgba(227, 33, 25, 0.3);
    }
    .submit-btn:hover {
      background-color: #c41c15;
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(227, 33, 25, 0.4);
    }

    .back-btn {
      margin-top: 20px;
      padding: 12px 25px;
      background-color: transparent;
      color: white;
      border: 2px solid white;
      border-radius: 30px;
      font-size: 16px;
      font-weight: 600;
      text-decoration: none;
      display: inline-block;
      transition: all 0.3s;
    }

    .back-btn:hover {
      background-color: white;
      color: #03245b;
    }

    @media (max-width: 768px) {
      .verification-form {
        padding: 20px;
      }
      .verification-form h1 {
        font-size: 24px;
      }
    }
  </style>
</head>
<body>
  <!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-TP5Z473"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
  <div class="verification-page">
    <img class="verification-bg" src="/images/bgcampaignsf3.png" alt="Background" />
    <div class="verification-form">
      <h1>Verifikasi Nomor Undianmu di Sini</h1>

      @if ($errors->any())
        <div class="error-message">
          <ul style="margin: 0; padding-left: 20px;">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form id="verificationForm" action="{{ route('promo.onedecade.verify') }}" method="POST">
        @csrf
        <div class="form-input">
          <input type="text" name="undian_code" placeholder="Nomor Undian / Kode Kupon" value="{{ old('undian_code') }}" required />
        </div>
        <div class="form-input">
          <input type="text" name="order_number" placeholder="Nomor Pesanan" value="{{ old('order_number') }}" required />
        </div>
        <div class="form-input">
          <input type="tel" name="contact_info" placeholder="Nomor Handphone (untuk konfirmasi pemenang)" pattern="[0-9+\-\s\(\)]{10,}" value="{{ old('contact_info') }}" required />
        </div>
        <div class="form-input">
          <select name="platform" required>
            <option value="" disabled selected>Pilih Platform Pembelian</option>
            <option value="website" {{ old('platform') == 'website' ? 'selected' : '' }}>Website Sneakers Flash</option>
            <option value="shopee" {{ old('platform') == 'shopee' ? 'selected' : '' }}>Shopee</option>
            <option value="tiktok" {{ old('platform') == 'tiktok' ? 'selected' : '' }}>TikTok</option>
            <option value="tokopedia" {{ old('platform') == 'tokopedia' ? 'selected' : '' }}>Tokopedia</option>
            <option value="blibli" {{ old('platform') == 'blibli' ? 'selected' : '' }}>BliBli</option>
            <option value="whatsapp" {{ old('platform') == 'whatsapp' ? 'selected' : '' }}>Whatsapp</option>
            <option value="uss_event" {{ old('platform') == 'uss_event' ? 'selected' : '' }}>USS Event</option>
          </select>
        </div>
        <button type="submit" class="submit-btn">SUBMIT</button>
      </form>

      <a href="{{ route('promo.onedecade') }}" class="back-btn">Kembali</a>
    </div>
  </div>


<script>
  document.addEventListener('DOMContentLoaded', () => {
    // Get the form
    const form = document.getElementById('verificationForm');
    
    // Add event listener for form submission
    if (form) {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Create FormData object
        const formData = new FormData(form);
        
        // Submit the form via AJAX
        fetch(form.action, {
          method: 'POST',
          body: formData,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.redirect_url) {
            // Redirect to the finish page
            window.location.href = data.redirect_url;
          }
        })
        .catch(error => {
          console.error('Error:', error);
          // In case of error, submit the form normally
          form.submit();
        });
      });
    }
  });
</script>

</body>
</html>
