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
        Schema::table('orders', function (Blueprint $table) {
            // Add komerce_order_no column
            $table->string('komerce_order_no', 100)->nullable()->after('order_number');
            
            // Add index for better performance
            $table->index('komerce_order_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['komerce_order_no']);
            $table->dropColumn('komerce_order_no');
        });
    }
};