<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weeks', function (Blueprint $table): void {
            $table->boolean('reward_claimed')->default(false)->after('reward_description');
        });
    }

    public function down(): void
    {
        Schema::table('weeks', function (Blueprint $table): void {
            $table->dropColumn('reward_claimed');
        });
    }
};
