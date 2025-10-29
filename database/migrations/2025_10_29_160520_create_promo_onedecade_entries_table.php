<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop tabel lama jika perlu
        Schema::dropIfExists('promo_onedecade_entries');
        
        // Buat tabel baru dengan struktur yang sesuai dengan controller
        Schema::create('promo_onedecade_entries', function (Blueprint $table) {
            $table->id();
            
            // Kolom sesuai dengan yang digunakan controller
            $table->string('undian_code')->nullable();
            $table->string('order_number')->nullable();
            $table->string('platform')->nullable();
            $table->string('contact_info')->nullable();
            $table->string('entry_number')->unique()->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_onedecade_entries');
    }
};
