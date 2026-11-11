<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakery_price_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->foreignId('product_id')->constrained('bakery_products')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('kind');
            $table->string('direction')->nullable();
            $table->unsignedBigInteger('delta_kopecks')->nullable();
            $table->foreignId('reverses_id')->nullable()->constrained('bakery_price_operations')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
        Schema::create('bakery_price_operation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operation_id')->constrained('bakery_price_operations')->cascadeOnDelete();
            $table->foreignId('bakery_id')->constrained('bakeries')->restrictOnDelete();
            $table->foreignId('price_source_id')->constrained('bakeries')->restrictOnDelete();
            $table->unsignedBigInteger('before_kopecks')->nullable();
            $table->unsignedBigInteger('after_kopecks');
            $table->unique(['operation_id', 'bakery_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bakery_price_operation_items');
        Schema::dropIfExists('bakery_price_operations');
    }
};
