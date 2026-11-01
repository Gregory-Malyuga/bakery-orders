<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakery_stock_items', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 16);
            $table->foreignId('ingredient_id')->nullable()->unique()->constrained('bakery_ingredients')->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->unique()->constrained('bakery_products')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('unit', 8);
            $table->timestamps();
            $table->index(['kind', 'name']);
        });

        Schema::create('bakery_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_item_id')->constrained('bakery_stock_items')->restrictOnDelete();
            $table->date('movement_date');
            $table->string('kind', 20);
            $table->bigInteger('quantity_milli');
            $table->bigInteger('balance_milli');
            $table->bigInteger('counted_milli')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->foreignId('reverses_id')->nullable()->unique()->constrained('bakery_stock_movements')->restrictOnDelete();
            $table->timestamps();
            $table->index(['stock_item_id', 'movement_date', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bakery_stock_movements');
        Schema::dropIfExists('bakery_stock_items');
    }
};
