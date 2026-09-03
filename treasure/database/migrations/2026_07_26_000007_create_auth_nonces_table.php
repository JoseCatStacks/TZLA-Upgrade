<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_nonces', function (Blueprint $table): void {
            $table->id();
            $table->string('wallet_address');
            $table->string('nonce')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['wallet_address', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_nonces');
    }
};
