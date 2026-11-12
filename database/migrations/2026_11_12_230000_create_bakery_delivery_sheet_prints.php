<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakery_delivery_sheet_prints', function (Blueprint $table): void {
            $table->id();
            $table->date('delivery_date')->index();
            $table->unsignedBigInteger('source_sheet_id')->nullable();
            $table->json('snapshot');
            $table->timestamp('printed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bakery_delivery_sheet_prints');
    }
};
