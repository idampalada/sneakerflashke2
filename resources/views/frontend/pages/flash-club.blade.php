@extends('layouts.app')

@section('title', 'Flash Club - SneakerFlash')

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
                        FLASH CLUB
                    </h2>

                    <div class="text-gray-700 leading-relaxed space-y-6">
                        <p>
                            Flash Club is an exclusive loyalty program for Sneakers Flash customers. Join today and unlock special perks!
                        </p>

                        <div>
                            <p class="font-semibold mb-2">Member Benefits:</p>
                            <ul class="list-disc pl-6 space-y-1">
                                <li>Early access to new collections</li>
                                <li>Exclusive discounts and promotions</li>
                                <li>Reward points for every purchase</li>
                                <li>Priority service and shipping</li>
                                <li>Invitations to Sneakers Flash special events</li>
                            </ul>
                        </div>

                        <p>
                            Join now and enjoy a more personalized, faster, and more rewarding shopping experience.
                        </p>
                    </div>
                </div>

                <!-- ID Content (DITAMBAHKAN SAJA) -->
                <div id="content-id" class="hidden">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">
                        FLASH CLUB
                    </h2>

                    <div class="text-gray-700 leading-relaxed space-y-6">
                        <p>
                            Flash Club adalah program loyalitas eksklusif untuk pelanggan Sneakers Flash. Bergabung sekarang dan nikmati berbagai keuntungan spesial!
                        </p>

                        <div>
                            <p class="font-semibold mb-2">Keuntungan Member:</p>
                            <ul class="list-disc pl-6 space-y-1">
                                <li>Akses lebih awal ke koleksi terbaru</li>
                                <li>Diskon dan promo eksklusif</li>
                                <li>Poin reward untuk setiap pembelian</li>
                                <li>Layanan dan pengiriman prioritas</li>
                                <li>Undangan ke event spesial Sneakers Flash</li>
                            </ul>
                        </div>

                        <p>
                            Daftar sekarang dan rasakan pengalaman belanja yang lebih personal, cepat, dan menguntungkan.
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
