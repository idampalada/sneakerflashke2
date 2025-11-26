<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('black_friday_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_type')->index();
            $table->string('brand')->index();
            $table->text('related_product');
            $table->string('name');
            $table->text('description');
            
            // Pricing
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('original_price', 12, 2)->nullable(); // For showing discount
            $table->decimal('discount_percentage', 5, 2)->nullable();
            
            // Product identifiers
            $table->string('sku')->unique();
            $table->string('sku_parent')->index();
            
            // Stock and availability
            $table->integer('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            
            // Categories and attributes
            $table->json('gender_target')->nullable(); // ['mens', 'womens', 'unisex']
            $table->json('available_sizes')->nullable();
            $table->json('available_colors')->nullable();
            
            // Images
            $table->json('image_urls')->nullable();
            $table->string('featured_image')->nullable();
            
            // SEO
            $table->string('slug')->unique();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            
            // Special Black Friday fields
            $table->datetime('sale_start_date')->nullable();
            $table->datetime('sale_end_date')->nullable();
            $table->boolean('is_flash_sale')->default(false);
            $table->integer('limited_stock')->nullable(); // For flash sale items
            
            $table->timestamps();
            
            // Indexes
            $table->index(['is_active', 'sale_start_date', 'sale_end_date']);
            $table->index(['product_type', 'brand']);
            $table->index(['is_featured', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('black_friday_products');
    }
};