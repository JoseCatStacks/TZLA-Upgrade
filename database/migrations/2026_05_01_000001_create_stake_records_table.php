<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stake_records', function (Blueprint $table): void {
            $table->id();
            $table->string('wallet', 64)->index();
            $table->string('amount_raw', 32);
            $table->unsignedTinyInteger('nft_tier')->default(0);
            $table->timestamp('staked_at');
            $table->timestamp('unstaked_at')->nullable()->index();
            $table->string('stake_tx', 128)->nullable()->unique();
            $table->string('unstake_tx', 128)->nullable();
            $table->timestamps();

            $table->index(['wallet', 'unstaked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stake_records');
    }
};
