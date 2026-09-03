<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('words', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('week_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('answer_normalized');
            $table->text('hint')->nullable();
            $table->timestamps();

            $table->unique(['week_id', 'position']);
            $table->index('answer_normalized');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
