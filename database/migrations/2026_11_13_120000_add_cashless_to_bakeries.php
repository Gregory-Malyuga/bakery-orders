<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakeries', fn (Blueprint $table) => $table->boolean('is_cashless')->default(false));
    }

    public function down(): void
    {
        Schema::table('bakeries', fn (Blueprint $table) => $table->dropColumn('is_cashless'));
    }
};
