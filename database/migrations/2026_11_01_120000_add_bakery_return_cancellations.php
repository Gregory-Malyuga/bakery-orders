<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bakery_returns', function (Blueprint $table): void {
            $table->string('status', 16)->default('active');
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bakery_returns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by_user_id');
            $table->dropColumn(['status', 'cancelled_at', 'cancellation_reason']);
        });
    }
};
