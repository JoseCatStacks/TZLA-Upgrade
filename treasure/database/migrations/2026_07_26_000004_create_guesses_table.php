<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('word_id')->constrained()->cascadeOnDelete();
            $table->string('guess_raw');
            $table->string('guess_normalized');
            $table->boolean('is_correct')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'word_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guesses');
    }
};
