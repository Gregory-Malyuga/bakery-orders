<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['mixed' => 3, 'pair' => 2] as $mode => $minimum) {
            DB::table('bakery_products')->where('packaging_mode', $mode)->where('minimum_quantity', '<', $minimum)->update(['minimum_quantity' => $minimum]);
            DB::table('bakery_store_product_minimums')->where('minimum_quantity', '<', $minimum)->whereIn('product_id', function ($query) use ($mode): void {
                $query->select('id')->from('bakery_products')->where('packaging_mode', $mode);
            })->update(['minimum_quantity' => $minimum]);
        }
    }

    public function down(): void
    {
        // Старые значения минимума невозможно восстановить без снимка данных.
    }
};
