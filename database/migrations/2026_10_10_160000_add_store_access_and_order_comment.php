<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakery_account_stores', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('bakery_id')->constrained('bakeries')->cascadeOnDelete();
            $table->primary(['user_id', 'bakery_id']);
        });
        DB::table('users')->whereNotNull('bakery_id')->orderBy('id')->chunk(500, function ($users): void {
            DB::table('bakery_account_stores')->insert($users->map(fn ($user): array => [
                'user_id' => $user->id,
                'bakery_id' => $user->bakery_id,
            ])->all());
        });
        Schema::table('bakery_orders', function (Blueprint $table): void {
            $table->text('comment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bakery_orders', fn (Blueprint $table) => $table->dropColumn('comment'));
        Schema::dropIfExists('bakery_account_stores');
    }
};
