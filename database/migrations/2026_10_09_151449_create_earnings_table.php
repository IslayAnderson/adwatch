<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_view_id')->unique()->constrained()->cascadeOnDelete();
            // All money is stored in micro-dollars (1 USD = 1,000,000) because a single
            // impression is worth fractions of a cent.
            $table->unsignedInteger('cpm_cents');
            $table->unsignedBigInteger('gross_micros');
            $table->unsignedBigInteger('user_micros');
            $table->unsignedBigInteger('platform_micros');
            // escrow | released | reversed
            $table->string('status')->default('escrow');
            $table->timestamp('release_at');
            $table->timestamp('released_at')->nullable();
            $table->string('reversal_reason')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('earnings');
    }
};
