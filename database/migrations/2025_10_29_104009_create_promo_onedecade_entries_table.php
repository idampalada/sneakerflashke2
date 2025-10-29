<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promo_onedecade_entries', function (Blueprint $table) {
            $table->id();
            
            // Participant details
            $table->string('invoice_number', 50)->index();
            $table->string('platform', 20);
            $table->string('phone_number', 20)->index();
            $table->string('coupon_code', 20)->nullable()->index();
            
            // Entry details
            $table->string('entry_number', 20)->nullable()->comment('Assigned lottery number');
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_winner')->default(false);
            $table->string('prize_type', 30)->nullable()->comment('Type of prize won if any');
            
            // System fields
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            
            // Add unique constraint to invoice to prevent duplicates
            $table->unique(['invoice_number', 'platform']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_onedecade_entries');
    }
};