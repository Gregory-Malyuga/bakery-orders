<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakeries', function (Blueprint $table): void {
            $table->string('address')->default('');
            $table->boolean('is_active')->default(true);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('bakery_id')->nullable()->constrained('bakeries')->restrictOnDelete();
        });
        Schema::create('bakery_products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('bakery_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bakery_id')->constrained('bakeries')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('bakery_products')->cascadeOnDelete();
            $table->unsignedBigInteger('kopecks');
            $table->unique(['bakery_id', 'product_id']);
            $table->timestamps();
        });
        Schema::create('bakery_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bakery_id')->constrained('bakeries')->restrictOnDelete();
            $table->date('delivery_date');
            $table->string('status');
            $table->unsignedTinyInteger('active_slot')->nullable();
            $table->uuid('request_key')->unique();
            $table->string('store_name');
            $table->string('store_address');
            $table->unsignedBigInteger('total_kopecks');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['bakery_id', 'delivery_date', 'active_slot']);
            $table->index(['delivery_date', 'status']);
        });
        Schema::create('bakery_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('bakery_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('bakery_products')->restrictOnDelete();
            $table->string('product_name');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_kopecks');
            $table->unsignedBigInteger('total_kopecks');
            $table->unique(['order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bakery_order_items');
        Schema::dropIfExists('bakery_orders');
        Schema::dropIfExists('bakery_prices');
        Schema::dropIfExists('bakery_products');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('bakery_id'));
        Schema::table('bakeries', fn (Blueprint $table) => $table->dropColumn(['address', 'is_active']));
    }
};
