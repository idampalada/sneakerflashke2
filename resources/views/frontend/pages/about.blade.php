@extends('layouts.app')

@section('title', 'About Us - SneakerFlash')

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

                <!-- EN -->
                <div id="content-en">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">
                        ABOUT US
                    </h2>

                    <div class="text-gray-700 leading-relaxed space-y-6">
                        <p>
                            Founded in 2013, <strong>Sneakers Flash</strong> has become a trusted destination for curated sneakers. Staying true to our vision, we continue to lead the market by offering a diverse selection of affordable sneaker brands, each guaranteed authentic for every customer.
                        </p>

                        <p>
                            At Sneakers Flash, you'll find the latest drops, best-selling models, and even rare items, all verified for quality and authenticity.
                        </p>

                        <p class="font-medium">
                            Sneakers Flash — Discover Your Kicks.
                        </p>
                    </div>
                </div>

                <!-- ID -->
                <div id="content-id" class="hidden">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">
                        TENTANG KAMI
                    </h2>

                    <div class="text-gray-700 leading-relaxed space-y-6">
                        <p>
                            Didirikan pada tahun 2013, <strong>Sneakers Flash</strong> telah menjadi destinasi terpercaya untuk sneaker pilihan. Tetap setia pada visi kami, kami terus menghadirkan beragam pilihan sneaker dengan jaminan keaslian untuk setiap pelanggan.
                        </p>

                        <p>
                            Di Sneakers Flash, Anda akan menemukan rilisan terbaru, model terlaris, hingga item langka yang telah diverifikasi kualitas dan keasliannya.
                        </p>

                        <p class="font-medium">
                            Sneakers Flash — Temukan Sepatu Impian Anda.
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
