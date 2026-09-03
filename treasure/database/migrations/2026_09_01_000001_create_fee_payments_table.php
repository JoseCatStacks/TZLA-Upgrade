<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_payments', function (Blueprint $table): void {
            $table->id();

            // The unique index is the replay defence: a Solana transaction
            // signature may only ever be spent on a single guess.
            $table->string('signature', 128)->unique();

            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('word_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount_sol', 20, 9);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payments');
    }
};
