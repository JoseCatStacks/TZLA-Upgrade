<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'address',
        'username',
        'payout_address',
        'first_connected_at',
        'last_seen_at',
        'tzla_balance_cached',
        'nft_count_cached',
        'golden_ticket_count_cached',
        'holdings_refreshed_at',
    ];

    protected $casts = [
        'first_connected_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'holdings_refreshed_at' => 'datetime',
        'tzla_balance_cached' => 'decimal:6',
        'nft_count_cached' => 'integer',
        'golden_ticket_count_cached' => 'integer',
    ];

    public function guesses(): HasMany
    {
        return $this->hasMany(Guess::class);
    }

    public function wordCompletions(): HasMany
    {
        return $this->hasMany(WordCompletion::class);
    }

    public function weekCompletions(): HasMany
    {
        return $this->hasMany(WeekCompletion::class);
    }

    public function holdsTzla(): bool
    {
        $threshold = (float) config('game.play_gate.tzla_threshold', 9.0);

        return (float) $this->tzla_balance_cached >= $threshold;
    }

    public function nftCount(): int
    {
        return (int) ($this->nft_count_cached ?? 0);
    }

    public function holdsNft(): bool
    {
        return $this->nftCount() > 0;
    }

    public function goldenTicketCount(): int
    {
        return (int) ($this->golden_ticket_count_cached ?? 0);
    }

    public function holdsGoldenTicket(): bool
    {
        return $this->goldenTicketCount() > 0;
    }

    public function canPlay(): bool
    {
        return $this->holdsGoldenTicket() || $this->holdsNft() || $this->holdsTzla();
    }

    public function shortAddress(): string
    {
        return strlen($this->address) > 10
            ? substr($this->address, 0, 4).'…'.substr($this->address, -4)
            : $this->address;
    }
}
