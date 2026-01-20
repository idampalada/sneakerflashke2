@extends('layouts.app')

@section('title', 'Privacy Policy - SneakerFlash')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">

        <div class="bg-white rounded-lg shadow-md p-8">
            <div class="prose max-w-none">

                <!-- Language Toggle -->
                <div class="flex justify-end mb-4 text-sm font-medium">
                    <button id="btn-id" onclick="switchLang('id')" class="text-black">
                        ID
                    </button>
                    <span class="mx-2 text-black">|</span>
                    <button id="btn-en" onclick="switchLang('en')" class="text-black">
                        EN
                    </button>
                </div>

                <!-- EN Content (ORIGINAL – TIDAK DIUBAH) -->
                <div id="content-en">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">
                        PRIVACY POLICY
                    </h2>

                    <div class="text-gray-700 leading-relaxed space-y-6">
                        <p>
                            We value your privacy and handle personal data with utmost care. The information you provide is used solely for transactions and to improve our services.
                        </p>

                        <div>
                            <p class="font-semibold mb-2">Information We Collect:</p>
                            <ul class="list-disc pl-6 space-y-1">
                                <li>Name, address, and phone number</li>
                                <li>Email address</li>
                                <li>Payment information</li>
                                <li>Purchase history for internal analysis</li>
                            </ul>
                        </div>

                        <div>
                            <p class="font-semibold mb-2">How We Use Your Data:</p>
                            <ul class="list-disc pl-6 space-y-1">
                                <li>To process and fulfill orders</li>
                                <li>To contact customers regarding order updates</li>
                                <li>To provide optional promotions and updates</li>
                                <li>To enhance your overall shopping experience</li>
                            </ul>
                        </div>

                        <p>
                            We do not sell or share personal data with third parties, except trusted payment providers and shipping partners involved in order processing.
                        </p>
                    </div>
                </div>

                <!-- ID Content (DITAMBAHKAN SAJA) -->
                <div id="content-id" class="hidden">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">
                        KEBIJAKAN PRIVASI
                    </h2>

                    <div class="text-gray-700 leading-relaxed space-y-6">
                        <p>
                            Kami menghargai privasi Anda dan menangani data pribadi dengan sangat hati-hati. Informasi yang Anda berikan digunakan semata-mata untuk keperluan transaksi dan peningkatan layanan kami.
                        </p>

                        <div>
                            <p class="font-semibold mb-2">Informasi yang Kami Kumpulkan:</p>
                            <ul class="list-disc pl-6 space-y-1">
                                <li>Nama, alamat, dan nomor telepon</li>
                                <li>Alamat email</li>
                                <li>Informasi pembayaran</li>
                                <li>Riwayat pembelian untuk analisis internal</li>
                            </ul>
                        </div>

                        <div>
                            <p class="font-semibold mb-2">Penggunaan Data:</p>
                            <ul class="list-disc pl-6 space-y-1">
                                <li>Memproses dan menyelesaikan pesanan</li>
                                <li>Menghubungi pelanggan terkait pembaruan pesanan</li>
                                <li>Mengirimkan promosi dan informasi (opsional)</li>
                                <li>Meningkatkan pengalaman berbelanja Anda</li>
                            </ul>
                        </div>

                        <p>
                            Kami tidak menjual atau membagikan data pribadi kepada pihak ketiga, kecuali kepada penyedia pembayaran dan mitra pengiriman terpercaya yang terlibat dalam proses pesanan.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function switchLang(lang) {
    // hide all
    document.getElementById('content-en').classList.add('hidden');
    document.getElementById('content-id').classList.add('hidden');

    // reset buttons
    document.getElementById('btn-en').classList.remove('font-semibold','text-red-600');
    document.getElementById('btn-id').classList.remove('font-semibold','text-red-600');

    // show selected
    document.getElementById('content-' + lang).classList.remove('hidden');
    document.getElementById('btn-' + lang).classList.add('font-semibold','text-red-600');

    localStorage.setItem('about-lang', lang);
}

document.addEventListener('DOMContentLoaded', () => {
    switchLang(localStorage.getItem('about-lang') || 'en');
});
</script>
@endsection
