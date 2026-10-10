<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakery_products', function (Blueprint $table): void {
            $table->string('packaging_mode')->default('mixed');
        });
        DB::table('bakery_products')->whereIn(DB::raw('lower(trim(name))'), ['пицца', 'ватрушка', 'хачапури', 'лепешка', 'лепёшка'])->update(['packaging_mode' => 'single']);
        DB::table('bakery_products')->whereIn(DB::raw('lower(trim(name))'), ['мак', 'изюм', 'косичка с маком'])->update(['packaging_mode' => 'pair']);
    }

    public function down(): void
    {
        Schema::table('bakery_products', fn (Blueprint $table) => $table->dropColumn('packaging_mode'));
    }
};
