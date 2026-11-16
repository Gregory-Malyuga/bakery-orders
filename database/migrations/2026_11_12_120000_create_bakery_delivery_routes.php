<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakery_delivery_routes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('driver');
            $table->json('store_ids');
            $table->timestamps();
        });
        Schema::create('bakery_delivery_sheets', function (Blueprint $table): void {
            $table->id();
            $table->date('delivery_date');
            $table->json('snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bakery_delivery_sheets');
        Schema::dropIfExists('bakery_delivery_routes');
    }
};
