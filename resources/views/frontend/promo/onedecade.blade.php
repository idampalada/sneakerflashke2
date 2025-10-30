<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>One Decade Promo - SneakerFlash</title>
    
    <!-- Preload penting -->
    <link rel="preload" href="/images/bgcampaignsf.jpg" as="image">
    <link rel="preload" href="/images/buttonremovebg.png" as="image">
    <link rel="preload" href="/images/bgmobilesf.png" as="image">
    <link rel="preload" href="/images/bgcampaignsf2.png" as="image">
    
    <style>
        /* Reset dan dasar */
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #03245b;
            overflow-x: hidden;
        }
        
        /* SECTION 1: Wrapper utama */
        .promo-container {
            width: 100%;
            padding: 0;
            background-color: #03245b;
            position: relative;
            overflow: hidden;
        }
        
        /* Background dekorasi */
        .side-decoration {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            background: radial-gradient(circle at center, #03245b, #021636);
            background-image: 
                radial-gradient(circle, #ffffff10 1px, transparent 1px),
                radial-gradient(circle, #ffffff08 2px, transparent 2px),
                radial-gradient(circle, #ffffff05 3px, transparent 3px);
            background-size: 
                30px 30px,
                60px 60px,
                90px 90px;
        }
        
        /* Container gambar utama */
        .main-image-container {
            position: relative;
            z-index: 2;
            width: 100%;
        }
        
        /* Gambar utama - full width dengan responsive mobile support */
        .main-image {
            display: block;
            width: 100%;
            height: auto;
        }
        
        /* Container untuk konten overlay */
        .content-overlay {
            position: absolute;
            bottom: -45px; /* Posisi dari bawah - DIPINDAH KE BAWAH */
            left: 0;
            width: 100%;
            z-index: 3;
            display: flex;
            justify-content: center;
            pointer-events: none;
        }
        
        /* Container tombol verifikasi */
        .verify-button-container {
            pointer-events: auto;
            cursor: pointer;
            transition: transform 0.3s;
            animation: pulse 2s infinite; /* Tambahkan animasi pulse */
        }
        
        .verify-button-container:hover {
            transform: translateY(-5px);
            animation: none; /* Hentikan animasi saat hover */
        }
        
        /* Animasi pulse untuk tombol */
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }
        
        /* Gambar tombol */
        .verify-button-img {
            width: 300px;
            max-width: 30vw;
            height: auto;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.5));
        }
        
        /* SECTION 2: Menggunakan background gambar asli dengan "Discover your kicks" */
        .promo-section {
            width: 100%;
            position: relative;
            padding: 40px 0;
            background-color: #03245b;
        }
        
        /* Background image section 2 - desktop & mobile versions */
        .bg-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
        }
        
        /* Form Verifikasi styles - sekarang inline, bukan modal */
        .verification-container {
            background-color: white;
            width: 90%;
            max-width: 500px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            padding: 20px;
            text-align: center;
            margin: 0 auto 40px auto;
            display: none; /* Hidden by default */
            position: relative;
            z-index: 10;
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
        }
        
        .submit-btn:hover {
            background-color: #c71d16;
        }
        
        /* Notification overlay */
        #notificationOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            z-index: 200;
            display: none;
            justify-content: center;
            align-items: center;
        }
        
        /* Section content styling */
        .section-content {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Total Peserta section */
        .result-container {
            position: relative;
            z-index: 10;
            width: 100%;
        }
        
        .result-box {
            background-color: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            text-align: center;
        }
        
        .result-box h1 {
            font-size: 28px;
            margin-top: 0;
            margin-bottom: 30px;
            color: #333;
        }
        
        /* Stats display */
        .participants-number {
            font-size: 72px;
            font-weight: 900;
            color: #e32119;
            margin: 20px 0 10px;
            line-height: 1;
        }
        
        .active-numbers {
            font-size: 18px;
            margin-bottom: 30px;
            color: #333;
        }
        
        .result-promo-section {
            margin-top: 30px;
        }
        
        .result-promo-section h2 {
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 20px 0;
            background-color: #021330;
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }
        
        /* Button styles */
        .btn-white, .btn-calendar {
            padding: 12px 25px;
            border-radius: 30px;
            border: none;
            font-weight: bold;
            cursor: pointer;
            margin: 10px;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-white {
            background-color: white;
            color: #e32119;
            border: 1px solid #e32119;
        }
        
        .btn-calendar {
            background-color: #4285F4;
            color: white;
            border: 1px solid #4285F4;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-calendar img {
            width: 20px;
            height: 20px;
        }
        
        .btn-white:hover, .btn-calendar:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        /* Responsive styles - desktop default */
        .desktop-only {
            display: block;
        }
        
        .mobile-only {
            display: none;
        }
        
        /* Mobile-specific styles */
        @media (max-width: 1080px) {
            /* Switch display for main section only */
            .desktop-only {
                display: none;
            }
            
            .mobile-only {
                display: block;
            }
            
            /* Perbesar tombol verifikasi untuk mobile */
            .verify-button-img {
                width: 300px; /* Lebih besar dari sebelumnya */
                max-width: 80vw; /* Buat lebih besar di layar mobile */
                filter: drop-shadow(0 6px 12px rgba(0, 0, 0, 0.6)); /* Shadow yang lebih kuat */
            }
            
            /* Buat animasi lebih terlihat pada mobile */
            @keyframes pulse {
                0% {
                    transform: scale(1);
                    filter: brightness(1);
                }
                50% {
                    transform: scale(1.08); /* Animasi lebih kuat */
                    filter: brightness(1.1); /* Tambah efek brightness */
                }
                100% {
                    transform: scale(1);
                    filter: brightness(1);
                }
            }
            
            .verify-button-container {
                animation: pulse 1.5s infinite; /* Animasi lebih cepat */
            }
            
            /* Adjust form for mobile */
            .verification-container {
                width: 70%;
                padding: 15px;
                margin: 0 auto 30px auto;
            }
            
            .verification-form h1 {
                font-size: 22px;
                margin: 5px 0 20px;
            }
            
            .form-input {
                margin-bottom: 12px;
            }
            
            .form-input input, 
            .form-input select {
                padding: 10px;
                font-size: 14px;
            }
            
            .submit-btn {
                padding: 10px;
                font-size: 15px;
            }
            
            /* Adjust results box for mobile */
            .section-content {
                padding: 0; /* Hilangkan padding default */
            }
            
            .result-container {
                /* Buat container lebih lebar */
                width: 100%;
                display: flex;
                justify-content: center; /* Default di tengah */
            }
            
            .result-box {
                width: 65%;
                max-width: 250px;
                padding: 14px 12px; /* Padding yang lebih besar untuk space */
                border-radius: 10px;
                margin: 0; /* Reset margin */
            }
            
            .result-box h1 {
                font-size: 12px; /* Lebih kecil */
                margin-bottom: 10px; /* Margin bawah lebih besar */
                margin-top: 0;
                font-weight: 600;
            }
            
            .participants-number {
                font-size: 30px; /* Lebih kecil */
                margin: 5px 0 5px; /* Margin atas bawah lebih besar */
                font-weight: 800;
            }
            
            .active-numbers {
                font-size: 10px; /* Lebih kecil */
                margin-bottom: 15px; /* Margin bawah lebih besar */
            }
            
            .result-promo-section {
                margin-top: 15px; /* Margin atas lebih besar */
            }
            
            .result-promo-section h2 {
                font-size: 12px; /* Lebih kecil */
                margin-top: 0;
                margin-bottom: 10px; /* Margin bawah lebih besar */
                font-weight: 600;
            }
            
            .result-promo-section p {
                font-size: 10px !important; /* Override inline styles - lebih kecil */
                margin: 8px 0 !important; /* Margin atas bawah lebih besar */
                line-height: 1.3;
            }
            
            /* Tombol yang lebih kecil di mobile */
            .btn-white, .btn-calendar {
                padding: 4px 8px;
                font-size: 10px;
                margin: 5px;
                border-radius: 15px;
            }
            
            .btn-calendar img {
                width: 10px;
                height: 10px;
            }
            
            /* Adjust content overlay position - DISESUAIKAN UNTUK MOBILE */
            .content-overlay {
                bottom: -80px; /* Posisi lebih ke bawah untuk mobile */
            }
            
            /* Adjust notification image size */
            #notificationImage {
                width: 90%;
                max-width: 300px;
            }
            
            /* Pengaturan khusus untuk resolusi 1020x1080 */
            @media (min-width: 1020px) and (max-width: 1080px) {
                .section-content {
                    max-width: 320px;
                    padding: 0;
                    margin: 0 auto;
                }
                
                .promo-section {
                    padding: 15px 0;
                }
                
                .verification-container {
                    margin: 0 auto 20px auto;
                    max-width: 280px;
                }
                
                /* Posisikan kotak hasil lebih ke kiri */
                .result-container {
                    justify-content: center;
                }
                
                .result-box {
                    max-width: 240px;
                    transform: translateX(-15px);
                }
                
                /* Sesuaikan tombol verifikasi untuk resolusi ini */
                .verify-button-img {
                    width: 280px;
                    max-width: 70vw;
                }
                
                /* Posisi tombol di bagian bawah untuk resolusi 1020x1080 */
                .content-overlay {
                    bottom: 100px; /* Lebih ke bawah lagi untuk 1020x1080 */
                }
            }
        }
    </style>
</head>
<body>
    <!-- SECTION 1: Full width image - Different for mobile and desktop -->
    <div class="promo-container">
        <div class="side-decoration"></div>
        
        <div class="main-image-container">
            <!-- Desktop image -->
            <img src="/images/bgcampaignsf.jpg" alt="One Decade Promo" class="main-image desktop-only">
            
            <!-- Mobile image - specific for 1020x1080 -->
            <img src="/images/bgmobilesf.png" alt="One Decade Promo" class="main-image mobile-only">
            
            <div class="content-overlay">
                <div class="verify-button-container" id="verifyBtn">
                    <img src="/images/buttonremovebg.png" alt="Verifikasi Sekarang" class="verify-button-img">
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 2: "Discover your kicks" section - Same image for both mobile and desktop -->
    <section class="promo-section">
        <!-- Both desktop and mobile use the same background image for Section 2 -->
        <img src="/images/bgcampaignsf2.png" alt="One Decade Promo - Bagian 2" class="bg-image">
        
        <div class="section-content">
            <!-- Form Verifikasi - Appears between sections -->
            <div id="verificationContainer" class="verification-container">
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
                            <input type="tel" name="contact_info" placeholder="Nomor Handphone (untuk konfirmasi pemenang)" 
                                   pattern="[0-9+\-\s\(\)]{10,}" title="Masukkan nomor handphone valid minimal 10 digit" 
                                   required>
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
            
            <!-- Total Peserta section -->
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
                    
                    <!-- Simplified stats display -->
                    <div class="participants-number">{{ $participantCount }} Peserta</div>
                    <div class="active-numbers">{{ $activeNumbers }} Nomor Undian Aktif</div>
                    
                    <!-- Tanggal Pengundian -->
                    <div class="result-promo-section">
                        <h2>Pengundian Langsung</h2>
                        <p style="font-size: 12px; font-weight: bold;">24 Januari 2026, 12:00 WIB</p>
                        
                        <p style="margin-top: 12px;">Tonton di IG Live @sneakers_flash dan jadi saksi siapa yang menang!</p>
                        
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; justify-content: center; margin-top: 15px;">
                            <a href="https://www.instagram.com/sneakers_flash/" target="_blank" class="btn-white">
                                Ikuti Instagram Kami
                            </a>
                            
                            <a href="#" id="calendarBtn" class="btn-calendar">
                                <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0id2hpdGUiIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCI+PHBhdGggZD0iTTAgMGgyNHYyNEgwVjB6IiBmaWxsPSJub25lIi8+PHBhdGggZD0iTTE5IDRoLTFWM2MwLS41NS0uNDUtMS0xLTFzLTEgLjQ1LTEgMXYxSDhWM2MwLS41NS0uNDUtMS0xLTFzLTEgLjQ1LTEgMXYxSDVjLTEuMTEgMC0xLjk5LjktMS45OSAyTDMgMjBjMCAxLjEuODkgMiAyIDJoMTRjMS4xIDAgMi0uOSAyLTJWNmMwLTEuMS0uOS0yLTItMnptLTEgMTZINmMtLjU1IDAtMS0uNDUtMS0xVjloMTR2MTBjMCAuNTUtLjQ1IDEtMSAxeiIvPjwvc3ZnPg==" alt="Calendar Icon">
                                Set Reminder
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Notification Image Overlay -->
    <div id="notificationOverlay">
        <img id="notificationImage" src="" alt="Notification">
    </div>
    
    <!-- Footer Simple -->
    <div class="footer">
        <p>© {{ date('Y') }} SneakerFlash. All rights reserved.</p>
    </div>
    
    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const verifyBtn = document.getElementById('verifyBtn');
            const verificationContainer = document.getElementById('verificationContainer');
            const notificationOverlay = document.getElementById('notificationOverlay');
            const notificationImage = document.getElementById('notificationImage');
            const calendarBtn = document.getElementById('calendarBtn');
            
            // Show verification form
            verifyBtn.addEventListener('click', function() {
                // Toggle visibility
                if (verificationContainer.style.display === 'block') {
                    verificationContainer.style.display = 'none';
                } else {
                    verificationContainer.style.display = 'block';
                    // Scroll to the form
                    verificationContainer.scrollIntoView({behavior: 'smooth'});
                }
            });
            
            // Hide notification when clicked
            notificationOverlay.addEventListener('click', function() {
                notificationOverlay.style.display = 'none';
            });
            
            // Set Google Calendar Reminder
            calendarBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Format for Google Calendar URL
                const eventTitle = encodeURIComponent('Pengundian Langsung SneakerFlash');
                const eventDetails = encodeURIComponent('Pengundian hadiah SneakerFlash One Decade Promo. Tonton di Instagram Live @sneakers_flash untuk melihat siapa pemenang undian!');
                const eventLocation = encodeURIComponent('Instagram Live (@sneakers_flash)');
                
                // 24 Januari 2026, 12:00-13:00 WIB
                const startDateTime = encodeURIComponent('20260124T050000Z'); // 12:00 WIB in UTC
                const endDateTime = encodeURIComponent('20260124T060000Z');   // 13:00 WIB in UTC
                
                // Create Google Calendar URL
                const googleCalendarUrl = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${eventTitle}&dates=${startDateTime}/${endDateTime}&details=${eventDetails}&location=${eventLocation}`;
                
                // Open in new tab
                window.open(googleCalendarUrl, '_blank');
            });
            
            // Blade session status
            @if(session('verification_status') === 'success')
                notificationImage.src = '/images/kuponsukses.png';
                notificationOverlay.style.display = 'flex';
            @elseif(session('verification_status') === 'error')
                notificationImage.src = '/images/kuponused.png';
                notificationOverlay.style.display = 'flex';
            @endif
        });
    </script>
</body>
</html>