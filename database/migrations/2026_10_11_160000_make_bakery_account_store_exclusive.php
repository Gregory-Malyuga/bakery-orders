<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakery_account_stores', function (Blueprint $table): void {
            $table->unique('bakery_id');
        });
    }

    public function down(): void
    {
        Schema::table('bakery_account_stores', function (Blueprint $table): void {
            $table->dropUnique(['bakery_id']);
        });
    }
};
