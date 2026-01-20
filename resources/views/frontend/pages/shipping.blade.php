@extends('layouts.app')

@section('title', 'Delivery Information - SneakerFlash')

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
                        DELIVERY
                    </h2>

                    <div class="text-gray-700 leading-relaxed space-y-6">
                        <p>
                            We provide fast and secure shipping services across Indonesia.
                        </p>

                        <div>
                            <p class="font-semibold mb-2">Estimated Delivery Time:</p>
                            <ul class="list-disc pl-6 space-y-1">
                                <li>Jabodetabek: 1-3 business days</li>
                                <li>Outside Jabodetabek: 2-5 business days</li>
                            </ul>
                        </div>

                        <div>
                            <p class="font-semibold mb-2">Shipping Information:</p>
                            <ul class="list-disc pl-6 space-y-1">
                                <li>Orders are processed on business days (Monday - Friday) before 16:00 WIB.</li>
                                <li>Orders are processed on non-business days (Saturday - Sunday) before 17:00 WIB.</li>
                                <li>Orders placed outside business hours will be processed on the next business day.</li>
                                <li>Each order will receive a tracking number.</li>
                            </ul>
                        </div>

                        <p>
                            We partner with reliable shipping providers to ensure your items arrive safely and on time.
                        </p>
                    </div>
                </div>

                <!-- ID Content (DITAMBAHKAN SAJA) -->
                <div id="content-id" class="hidden">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">
                        PENGIRIMAN
                    </h2>

                    <div class="text-gray-700 leading-relaxed space-y-6">
                        <p>
                            Kami menyediakan layanan pengiriman yang cepat dan aman ke seluruh Indonesia.
                        </p>

                        <div>
                            <p class="font-semibold mb-2">Estimasi Waktu Pengiriman:</p>
                            <ul class="list-disc pl-6 space-y-1">
                                <li>Jabodetabek: 1–3 hari kerja</li>
                                <li>Di luar Jabodetabek: 2–5 hari kerja</li>
                            </ul>
                        </div>

                        <div>
                            <p class="font-semibold mb-2">Informasi Pengiriman:</p>
                            <ul class="list-disc pl-6 space-y-1">
                                <li>Pesanan diproses pada hari kerja (Senin–Jumat) sebelum pukul 16.00 WIB.</li>
                                <li>Pesanan pada hari non-kerja (Sabtu–Minggu) diproses sebelum pukul 17.00 WIB.</li>
                                <li>Pesanan di luar jam operasional akan diproses pada hari kerja berikutnya.</li>
                                <li>Setiap pesanan akan mendapatkan nomor resi pengiriman.</li>
                            </ul>
                        </div>

                        <p>
                            Kami bekerja sama dengan jasa pengiriman terpercaya untuk memastikan pesanan Anda sampai dengan aman dan tepat waktu.
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
