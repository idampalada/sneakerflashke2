<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>One Decade Promo - SneakerFlash</title>
    
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100vh;
            overflow: hidden; /* Penting: mencegah scrolling */
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #03245b;
        }
        
        /* Container utama - menutupi seluruh layar */
        .promo-container {
            width: 100%;
            height: 100vh;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Dekorasi samping */
        .side-decoration {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            /* Gradient biru radial dari tengah ke tepi */
            background: radial-gradient(circle at center, #03245b, #021636);
            /* Efek konfeti */
            background-image: 
                radial-gradient(circle, #ffffff10 1px, transparent 1px),
                radial-gradient(circle, #ffffff08 2px, transparent 2px),
                radial-gradient(circle, #ffffff05 3px, transparent 3px);
            background-size: 
                30px 30px,
                60px 60px,
                90px 90px;
        }
        
        /* Gambar utama dengan "object-fit" untuk menjaga rasio aspek */
        .main-image-container {
            position: relative;
            z-index: 2;
            width: 100%;
            height: 100%;
            /* Hapus max-width dan max-height agar container selalu 100% layar */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .main-image {
            width: 100%;  /* Paksa lebar penuh container */
            height: 100%; /* Paksa tinggi penuh container */
            object-fit: fill; /* Ubah ke 'contain' untuk menghindari crop atas/bawah, menjaga seluruh gambar terlihat */
            display: block;
            /* Hapus max-width, max-height, dan width/height auto agar gambar selalu memenuhi */
        }
        
        /* Overlay untuk logo dan button */
        .content-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 3;
            pointer-events: none; /* Penting: memastikan klik diteruskan ke button */
        }
        
        .verify-button-container {
            position: absolute;
            bottom: -8%; /* Sesuai dengan permintaan: tombol diposisikan ke bawah */
            left: 0;
            right: 0;
            text-align: center;
            pointer-events: auto; /* Memastikan button bisa diklik */
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .verify-button-container:hover {
            transform: translateY(-5px);
        }
        
        .verify-button-img {
            width: 300px;
            max-width: 30vw;
            height: auto;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.5));
        }
        
        /* Form Verifikasi styles */
        .verification-container {
            display: none; /* Hidden by default */
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            align-items: center;
            justify-content: center;
        }
        
        .verification-form {
            background-color: white;
            width: 90%;
            max-width: 500px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            padding: 20px;
            text-align: center;
        }
        
        .verification-form h1 {
            font-size: 28px;
            margin: 10px 0 30px;
            font-weight: 700;
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
            box-sizing: border-box;
            background-color: #f8f8f8;
            outline: none;
        }
        
        .form-input input::placeholder,
        .form-input select::placeholder {
            color: #aaa;
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
            transition: background-color 0.2s;
        }
        
        .submit-btn:hover {
            background-color: #d41c15;
        }
        
        /* Notification overlay */
        #notificationOverlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 200;
            justify-content: center;
            align-items: center;
        }
        
        /* Ukuran gambar notifikasi - FIXED SIZE untuk menghindari zoom */
        #notificationImage {
            width: 450px; /* Ukuran tetap */
            height: auto;
            max-width: 80%; /* Batasi lebar maksimum pada layar kecil */
            cursor: pointer;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .verify-button-img {
                width: 250px;
            }
            
            .verification-form h1 {
                font-size: 24px;
            }
            
            #notificationImage {
                width: 350px; /* Sedikit lebih kecil untuk mobile */
            }
        }
    </style>
</head>
<body>
    <div class="promo-container">
        <!-- Dekorasi samping sebagai background -->
        <div class="side-decoration"></div>
        
        <!-- Container gambar utama -->
        <div class="main-image-container">
            <img src="/images/bgcampaignsf.jpg" alt="One Decade Promo" class="main-image">
        </div>
        
        <!-- Overlay untuk konten -->
        <div class="content-overlay">
            <!-- Verification button as image (dipindahkan ke bawah) -->
            <div class="verify-button-container" id="verifyBtn">
                <img src="/images/buttonremovebg.png" alt="Verifikasi Sekarang" class="verify-button-img">
            </div>
        </div>
    </div>
    
    <!-- Form Verifikasi -->
    <div id="verificationContainer" class="verification-container" style="display:none;">
        <div class="verification-form">
            <h1>Verifikasi Nomor Undianmu di Sini</h1>
            
            <form id="verificationForm" action="{{ route('promo.onedecade.verify') }}" method="POST">
                @csrf
                
                <div class="form-input">
                    <input type="text" name="undian_code" placeholder="Nomor Undian / Kode Kupon" required>
                </div>
                
                <div class="form-input">
                    <input type="text" name="order_number" placeholder="Nomor Pesanan" required>
                </div>
                
                <div class="form-input">
                    <input type="text" name="contact_info" placeholder="Email / No HP (untuk konfirmasi pemenang)" required>
                </div>
                
                <div class="form-input">
                    <select name="platform" required>
                        <option value="" selected disabled>Pilih Platform Pembelian</option>
                        <option value="website">Website Sneakers Flash</option>
                        <option value="shopee">Shopee</option>
                        <option value="tiktok">TikTok</option>
                        <option value="tokopedia">Tokopedia</option>
                        <option value="blibli">BliBli</option>
                        <option value="whatsapp">Whatsapp</option>
                        <option value="uss_event">USS Event</option>
                    </select>
                </div>
                
                <button type="submit" class="submit-btn">SUBMIT</button>
            </form>
        </div>
    </div>
    
    <!-- Notification Image Overlay - akan menampilkan gambar notifikasi dengan ukuran tetap -->
    <div id="notificationOverlay" style="display:none;">
        <img id="notificationImage" src="" alt="Notification">
    </div>
    
    <!-- JavaScript untuk interaksi -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const verifyBtn = document.getElementById('verifyBtn');
            const verificationContainer = document.getElementById('verificationContainer');
            const notificationOverlay = document.getElementById('notificationOverlay');
            const notificationImage = document.getElementById('notificationImage');
            
            // Show verification form
            verifyBtn.addEventListener('click', function() {
                verificationContainer.style.display = 'flex';
            });
            
            // Hide verifications when clicking outside
            verificationContainer.addEventListener('click', function(e) {
                if (e.target === verificationContainer) {
                    verificationContainer.style.display = 'none';
                }
            });
            
            // Hide notification when clicked
            notificationOverlay.addEventListener('click', function() {
                notificationOverlay.style.display = 'none';
                verificationContainer.style.display = 'none';
            });
            
            // Check for verification status from session
            @if(session('verification_status') === 'success')
                // Tampilkan notifikasi sukses
                notificationImage.src = '/images/kuponsukses.png';
                notificationOverlay.style.display = 'flex';
                verificationContainer.style.display = 'none';
            @elseif(session('verification_status') === 'error')
                // Tampilkan notifikasi error
                notificationImage.src = '/images/kuponused.png';
                notificationOverlay.style.display = 'flex';
                verificationContainer.style.display = 'none';
            @endif
        });
    </script>
</body>
</html>