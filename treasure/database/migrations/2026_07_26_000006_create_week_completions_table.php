<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('week_completions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('week_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->useCurrent();

            $table->unique(['wallet_id', 'week_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('week_completions');
    }
};
