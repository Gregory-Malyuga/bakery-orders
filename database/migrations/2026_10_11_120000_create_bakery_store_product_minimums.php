<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakery_store_product_minimums', function (Blueprint $table): void {
            $table->foreignId('bakery_id')->constrained('bakeries')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('bakery_products')->cascadeOnDelete();
            $table->unsignedInteger('minimum_quantity');
            $table->primary(['bakery_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bakery_store_product_minimums');
    }
};
