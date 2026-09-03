<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->string('address')->unique();
            $table->timestamp('first_connected_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->decimal('tzla_balance_cached', 20, 6)->nullable();
            $table->unsignedInteger('nft_count_cached')->nullable();
            $table->timestamp('holdings_refreshed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
