<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weeks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('number')->unique();
            $table->string('title')->nullable();
            $table->timestamp('starts_at');
            $table->text('reward_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weeks');
    }
};
