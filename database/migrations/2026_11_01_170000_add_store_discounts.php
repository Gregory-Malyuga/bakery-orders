<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakeries', function (Blueprint $table): void {
            $table->boolean('discount_enabled')->default(false);
            $table->unsignedTinyInteger('discount_percent')->default(0);
        });

        // Existing negotiated discount for the СНТ Надежда trading point only.
        DB::table('bakeries')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(name) LIKE ?', ['%снт надежда%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%надежда снт%'])
                    ->orWhereRaw('LOWER(address) LIKE ?', ['%снт надежда%']);
            })
            ->update(['discount_enabled' => true, 'discount_percent' => 10]);
    }

    public function down(): void
    {
        Schema::table('bakeries', fn (Blueprint $table) => $table->dropColumn(['discount_enabled', 'discount_percent']));
    }
};
