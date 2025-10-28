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
            background-image: url('/images/bgcampaignsf.jpg');
            background-size: 95%; /* Zoomed out by setting size larger than 100% */
            background-position: center center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            background-color: #03245b; /* Fallback color matching the blue in the image */
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-end;
            overflow-x: hidden; /* Prevent horizontal scroll */
        }
        
        /* Alternative background approach with cover but more visible */
        @media (max-width: 1200px) {
            body {
                background-size: cover; /* On smaller screens, ensure full coverage */
            }
        }
        
        .logo-container {
            position: absolute;
            top: 20px;
            display: flex;
            width: 100%;
            justify-content: space-between;
            padding: 0 20px;
            box-sizing: border-box;
        }
        
        .logo-left {
            height: 60px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5)); /* Add shadow to logo for better visibility */
        }
        
        .logo-right {
            height: 60px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5)); /* Add shadow to logo for better visibility */
        }
        
        .verify-button-container {
            margin-bottom: 100px;
            cursor: pointer;
            transition: transform 0.3s;
            text-align: center;
            z-index: 10; /* Ensure button stays above background */
        }
        
        .verify-button-container:hover {
            transform: translateY(-5px);
        }
        
        .verify-button-img {
            width: 300px;
            height: auto;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.5)); /* Enhanced shadow for better visibility */
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            overflow: auto;
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 0;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            background-color: #e32119;
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        input, select {
            width: 100%;
            padding: 14px;
            margin: 12px 0;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            font-size: 1.1rem;
            box-sizing: border-box;
        }
        
        select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
            padding-right: 2.5rem;
        }
        
        .submit-button {
            width: 100%;
            background-color: #e32119;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 16px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
        }
        
        .close-button {
            color: #fff;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 20px;
            top: 10px;
        }
        
        .close-button:hover {
            color: #f0f0f0;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .verify-button-img {
                width: 250px;
            }
            
            .logo-left, .logo-right {
                height: 50px;
            }
        }
    </style>
</head>
<body>
    <!-- Logo containers -->
    <div class="logo-container">
        <img src="/images/logo-sneakerflash.png" alt="Sneakers Flash" class="logo-left">
        <img src="/images/logo-onedecade.png" alt="One Decade" class="logo-right">
    </div>
    
    <!-- Verification button as image -->
    <div class="verify-button-container" id="verifyBtn">
        <img src="/images/buttonremovebg.png" alt="Verifikasi Sekarang" class="verify-button-img">
    </div>
    
    <!-- The Modal -->
    <div id="verifyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close-button">&times;</span>
                <h2>Verifikasi Nomor Undian</h2>
            </div>
            <div class="modal-body">
                <form action="{{ route('promo.onedecade.verify') }}" method="POST">
                    @csrf
                    
                    <div>
                        <input type="text" name="invoice_number" placeholder="Masukkan nomor invoice Anda" required>
                    </div>
                    
                    <div>
                        <select name="platform" required>
                            <option value="" selected disabled>Pilih Platform Pembelian</option>
                            <option value="website">Website Resmi</option>
                            <option value="tokopedia">Tokopedia</option>
                            <option value="shopee">Shopee</option>
                            <option value="instagram">Instagram</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="submit-button">
                        Verifikasi Sekarang
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- JavaScript for Modal -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = document.getElementById("verifyModal");
            var btn = document.getElementById("verifyBtn");
            var closeBtn = document.getElementsByClassName("close-button")[0];
            
            btn.onclick = function() {
                modal.style.display = "block";
            }
            
            closeBtn.onclick = function() {
                modal.style.display = "none";
            }
            
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }
        });
    </script>
</body>
</html>