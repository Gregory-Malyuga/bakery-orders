<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakery_orders', function (Blueprint $table): void {
            $table->text('admin_note')->nullable();
        });
        Schema::create('bakery_order_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('bakery_orders')->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->restrictOnDelete();
            $table->json('before_items');
            $table->json('after_items');
            $table->unsignedBigInteger('before_total_kopecks');
            $table->unsignedBigInteger('after_total_kopecks');
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('bakery_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('bakery_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('bakery_products')->restrictOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bakery_returns');
        Schema::dropIfExists('bakery_order_adjustments');
        Schema::table('bakery_orders', fn (Blueprint $table) => $table->dropColumn('admin_note'));
    }
};
