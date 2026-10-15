<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakery_settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('value');
        });
        DB::table('bakery_settings')->insert(['key' => 'order_cutoff', 'value' => '18:30']);
    }

    public function down(): void
    {
        Schema::dropIfExists('bakery_settings');
    }
};
