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
            $table->string('username', 64)->nullable()->after('address');
            $table->string('payout_address', 64)->nullable()->after('username');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropColumn(['username', 'payout_address']);
        });
    }
};
