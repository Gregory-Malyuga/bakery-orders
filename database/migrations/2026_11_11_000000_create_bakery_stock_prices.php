<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakery_stock_price_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_item_id')->constrained('bakery_stock_items')->restrictOnDelete();
            $table->date('effective_date');
            $table->unsignedBigInteger('price_kopecks');
            $table->unsignedBigInteger('measure_milli');
            $table->string('measure_unit', 8);
            $table->unsignedBigInteger('previous_price_kopecks')->nullable();
            $table->unsignedBigInteger('previous_measure_milli')->nullable();
            $table->string('previous_measure_unit', 8)->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['stock_item_id', 'effective_date', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bakery_stock_price_changes');
    }
};
