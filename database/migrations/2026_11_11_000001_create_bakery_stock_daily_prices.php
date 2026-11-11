<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakery_stock_daily_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_item_id')->constrained('bakery_stock_items')->restrictOnDelete();
            $table->date('price_date');
            $table->unsignedBigInteger('price_kopecks');
            $table->unsignedBigInteger('measure_milli');
            $table->string('measure_unit', 8);
            $table->foreignId('source_change_id')->constrained('bakery_stock_price_changes')->restrictOnDelete();
            $table->unique(['stock_item_id', 'price_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bakery_stock_daily_prices');
    }
};
