<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('word_id')->constrained()->cascadeOnDelete();
            $table->foreignId('correct_guess_id')->constrained('guesses')->cascadeOnDelete();
            $table->timestamp('completed_at')->useCurrent();

            $table->unique(['wallet_id', 'word_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('word_completions');
    }
};
