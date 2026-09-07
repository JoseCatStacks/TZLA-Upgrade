<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            // Standard XMR 95 chars; integrated 106. The old 64 cap was Solana-sized.
            $table->string('payout_address', 110)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->string('payout_address', 64)->nullable()->change();
        });
    }
};
