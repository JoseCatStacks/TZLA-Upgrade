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
            $table->decimal('staked_amount_cached', 20, 6)->nullable()->after('tzla_balance_cached');
        });

        Schema::table('fee_payments', function (Blueprint $table): void {
            $table->foreignId('week_id')->nullable()->after('word_id')->constrained()->nullOnDelete();
        });

        Schema::create('bundle_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('week_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('correct_count');
            $table->unsignedTinyInteger('total_words');
            $table->boolean('is_complete')->default(false);
            // Stored for ops/audit only — never returned to the client until week_complete.
            $table->json('answers');
            $table->string('fee_signature', 128)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'week_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_attempts');

        Schema::table('fee_payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('week_id');
        });

        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropColumn('staked_amount_cached');
        });
    }
};
