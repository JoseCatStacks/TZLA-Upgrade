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
            $table->unsignedInteger('golden_ticket_count_cached')->nullable()->after('nft_count_cached');
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropColumn('golden_ticket_count_cached');
        });
    }
};
