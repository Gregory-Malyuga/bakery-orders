<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakeries', function (Blueprint $table): void {
            $table->foreignId('price_source_id')->nullable()->constrained('bakeries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bakeries', fn (Blueprint $table) => $table->dropConstrainedForeignId('price_source_id'));
    }
};
