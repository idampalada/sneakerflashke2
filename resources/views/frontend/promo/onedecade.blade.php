<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>One Decade Promo - SneakerFlash</title>
    
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
        
        /* Gambar utama - full width */
        .main-image {
            display: block;
            width: 100%;
            height: auto;
        }
        
        /* Container untuk konten overlay */
        .content-overlay {
            position: absolute;
            bottom: -35px; /* Posisi dari bawah - DISESUAIKAN */
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
        }
        
        .verify-button-container:hover {
            transform: translateY(-5px);
        }
        
        /* Gambar tombol */
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
        
        #notificationImage {
            width: 450px;
            height: auto;
            max-width: 80%;
            cursor: pointer;
        }
        
        /* SECTION 2: Full width */
        .promo-section {
            width: 100%;
            position: relative;
            padding: 40px 0;
            background-color: #03245b;
        }
        
        /* Background image section 2 */
        .bg-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
        }
        
        /* Konten section 2 */
        .section-content {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Result box styling */
        .result-container {
            position: relative;
            z-index: 10;
            width: 100%;
        }
        
        .result-box {
            background-color: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            text-align: center;
        }
        
        .result-box h1 {
            font-size: 28px;
            margin-top: 0;
            margin-bottom: 30px;
            color: #333;
        }
        
        /* Simplified stats display */
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
        
        .result-promo-section {
            margin: 30px 0;
            padding: 25px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        
        .result-promo-section h2 {
            font-size: 24px;
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
        }
        
        .time-section {
            margin-top: 30px;
        }
        
        .footer {
            margin-top: 40px;
            text-align: center;
            color: white;
            padding: 15px;
        }
        
        /* Buttons */
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
        
        /* Countdown Timer */
        .countdown-container {
            display: flex;
            justify-content: center;
            width: 100%;
            margin: 30px 0 10px;
            gap: 10px;
        }
        
        .countdown-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 65px;
            width: 20%;
            max-width: 100px;
        }
        
        .countdown-value {
            background-color: white;
            color: black;
            font-size: 42px;
            font-weight: 900;
            line-height: 1;
            width: 100%;
            height: 80px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            margin-bottom: 10px;
        }
        
        .countdown-label {
            color: white;
            font-size: 18px;
            font-weight: 500;
            text-align: center;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .verify-button-img {
                width: 250px;
            }
            
            .result-box {
                padding: 20px;
            }
            
            .participants-number {
                font-size: 56px;
            }

            
            .countdown-value {
                font-size: 32px;
                height: 65px;
            }
            
            .countdown-label {
                font-size: 14px;
            }
            
            .verification-form h1 {
                font-size: 24px;
            }
            
            #notificationImage {
                width: 350px;
            }
            
            .content-overlay {
                bottom: 50px; /* Adjusted for mobile */
            }
        }
    </style>
</head>
<body>
    <!-- SECTION 1: Full width image -->
    <div class="promo-container">
        <div class="side-decoration"></div>
        
        <div class="main-image-container">
            <img src="/images/bgcampaignsf.jpg" alt="One Decade Promo" class="main-image">
            
            <div class="content-overlay">
                <div class="verify-button-container" id="verifyBtn">
                    <img src="/images/buttonremovebg.png" alt="Verifikasi Sekarang" class="verify-button-img">
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 2 -->
    <section class="promo-section" id="section-2">
        <img src="/images/bgcampaignsf2.png" alt="One Decade Promo - Bagian 2" class="bg-image">
        
        <div class="section-content">
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
                        <h2>
                            Pengundian Langsung
                        </h2>
                        <p style="font-size: 20px; font-weight: bold;">
                            24 Januari 2026, 12:00 WIB
                        </p>
                        
                        <!-- Countdown Timer -->
                        <div class="countdown-container">
                            <div class="countdown-item">
                                <div class="countdown-value" id="days">86</div>
                                <div class="countdown-label">Hari</div>
                            </div>
                            
                            <div class="countdown-item">
                                <div class="countdown-value" id="hours">0</div>
                                <div class="countdown-label">Jam</div>
                            </div>
                            
                            <div class="countdown-item">
                                <div class="countdown-value" id="minutes">14</div>
                                <div class="countdown-label">Menit</div>
                            </div>
                            
                            <div class="countdown-item">
                                <div class="countdown-value" id="seconds">15</div>
                                <div class="countdown-label">Detik</div>
                            </div>
                        </div>
                        
                        <p style="margin-top: 20px;">
                            Pengundian berlangsung Live di IG & TikTok @sneakers_flash.
Be ready, peeps.
                        </p>
                        
                        <div style="display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 20px;">
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
    
    <!-- Notification Image Overlay -->
    <div id="notificationOverlay" style="display:none;">
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
                verificationContainer.style.display = 'flex';
            });
            
            // Hide verification when clicking outside
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
            
            // Countdown Timer
            const countdownDate = new Date('January 24, 2026 12:00:00').getTime();
            
            // Update the countdown every second
            const countdown = setInterval(function() {
                // Get today's date and time
                const now = new Date().getTime();
                
                // Find the time remaining between now and the countdown date
                const timeRemaining = countdownDate - now;
                
                // Calculate days, hours, minutes, and seconds
                const days = Math.floor(timeRemaining / (1000 * 60 * 60 * 24));
                const hours = Math.floor((timeRemaining % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((timeRemaining % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeRemaining % (1000 * 60)) / 1000);
                
                // Display the result
                document.getElementById('days').textContent = days;
                document.getElementById('hours').textContent = hours;
                document.getElementById('minutes').textContent = minutes;
                document.getElementById('seconds').textContent = seconds;
                
                // If the countdown is over, display a message
                if (timeRemaining < 0) {
                    clearInterval(countdown);
                    document.getElementById('days').textContent = "0";
                    document.getElementById('hours').textContent = "0";
                    document.getElementById('minutes').textContent = "0";
                    document.getElementById('seconds').textContent = "0";
                }
            }, 1000);
            
            // Blade session status
            @if(session('verification_status') === 'success')
                notificationImage.src = '/images/kuponsukses.png';
                notificationOverlay.style.display = 'flex';
                verificationContainer.style.display = 'none';
            @elseif(session('verification_status') === 'error')
                notificationImage.src = '/images/kuponused.png';
                notificationOverlay.style.display = 'flex';
                verificationContainer.style.display = 'none';
            @endif
        });
    </script>
</body>
</html>