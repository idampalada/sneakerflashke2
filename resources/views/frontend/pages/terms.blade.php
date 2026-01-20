@extends('layouts.app')

@section('title', 'Terms & Conditions - SneakerFlash')

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
                        TERMS & CONDITIONS
                    </h2>

                    <div class="text-gray-700 leading-relaxed space-y-6">
                        <p>
                            By shopping at Sneakers Flash, you are deemed to have read and agreed to the following terms and conditions:
                        </p>

                        <div>
                            <p class="font-semibold mb-2">Product Availability</p>
                            <p>Stock may change at any time. If availability issues occur, our team will contact you to arrange a refund or replacement.</p>
                        </div>

                        <div>
                            <p class="font-semibold mb-2">Pricing & Payment</p>
                            <p>Prices may change without prior notice. Orders will be processed once payment has been successfully verified.</p>
                        </div>

                        <div>
                            <p class="font-semibold mb-2">Shipping</p>
                            <p>Sneakers Flash is not responsible for delays caused by courier services; however, we will assist in monitoring and following up on any issues.</p>
                        </div>

                        <div>
                            <p class="font-semibold mb-2">Returns</p>
                            <p>We accept return requests within 2×24 hours after the item is received. Returned items must be unused and complete.</p>
                        </div>

                        <div>
                            <p class="font-semibold mb-2">Website Usage</p>
                            <p>All content, photos, and materials on this website are the property of Sneakers Flash and may not be used without permission.</p>
                        </div>
                    </div>
                </div>

                <!-- ID Content (DITAMBAHKAN SAJA) -->
                <div id="content-id" class="hidden">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">
                        SYARAT & KETENTUAN
                    </h2>

                    <div class="text-gray-700 leading-relaxed space-y-6">
                        <p>
                            Dengan berbelanja di Sneakers Flash, Anda dianggap telah membaca, memahami, dan menyetujui syarat dan ketentuan berikut:
                        </p>

                        <div>
                            <p class="font-semibold mb-2">Ketersediaan Produk</p>
                            <p>Stok dapat berubah sewaktu-waktu. Jika terjadi kendala ketersediaan, tim kami akan menghubungi Anda untuk pengembalian dana atau penggantian produk.</p>
                        </div>

                        <div>
                            <p class="font-semibold mb-2">Harga & Pembayaran</p>
                            <p>Harga dapat berubah tanpa pemberitahuan sebelumnya. Pesanan akan diproses setelah pembayaran berhasil diverifikasi.</p>
                        </div>

                        <div>
                            <p class="font-semibold mb-2">Pengiriman</p>
                            <p>Sneakers Flash tidak bertanggung jawab atas keterlambatan yang disebabkan oleh pihak jasa pengiriman, namun kami akan membantu melakukan pemantauan dan tindak lanjut.</p>
                        </div>

                        <div>
                            <p class="font-semibold mb-2">Pengembalian</p>
                            <p>Kami menerima permintaan pengembalian dalam waktu 2×24 jam setelah barang diterima. Produk harus dalam kondisi belum digunakan dan lengkap.</p>
                        </div>

                        <div>
                            <p class="font-semibold mb-2">Penggunaan Website</p>
                            <p>Seluruh konten, foto, dan materi di situs ini merupakan milik Sneakers Flash dan tidak diperkenankan digunakan tanpa izin.</p>
                        </div>
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
