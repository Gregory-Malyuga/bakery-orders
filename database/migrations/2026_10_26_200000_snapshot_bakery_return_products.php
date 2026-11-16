<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakery_returns', function (Blueprint $table): void {
            $table->string('product_name')->nullable();
            $table->unsignedBigInteger('unit_kopecks')->nullable();
            $table->boolean('is_order_item')->default(true);
        });

        DB::table('bakery_returns')->orderBy('id')->chunkById(500, function ($returns): void {
            foreach ($returns as $return) {
                $item = DB::table('bakery_order_items')
                    ->where('order_id', $return->order_id)
                    ->where('product_id', $return->product_id)
                    ->first(['product_name', 'unit_kopecks']);
                if ($item !== null) {
                    DB::table('bakery_returns')->where('id', $return->id)->update([
                        'product_name' => $item->product_name,
                        'unit_kopecks' => $item->unit_kopecks,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('bakery_returns', function (Blueprint $table): void {
            $table->dropColumn(['product_name', 'unit_kopecks', 'is_order_item']);
        });
    }
};
