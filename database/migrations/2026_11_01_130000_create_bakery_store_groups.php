<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bakery_store_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('show_in_invoice')->default(false);
            $table->boolean('exclude_from_labels')->default(false);
            $table->timestamps();
        });

        Schema::create('bakery_store_group_members', function (Blueprint $table): void {
            $table->foreignId('group_id')->constrained('bakery_store_groups')->cascadeOnDelete();
            $table->foreignId('bakery_id')->constrained('bakeries')->cascadeOnDelete();
            $table->primary(['group_id', 'bakery_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bakery_store_group_members');
        Schema::dropIfExists('bakery_store_groups');
    }
};
