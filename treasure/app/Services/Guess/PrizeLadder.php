<?php

declare(strict_types=1);

namespace App\Services\Guess;

use App\Models\Wallet;
use App\Models\Week;
use App\Models\WeekCompletion;

final class PrizeLadder
{
    public function placeFor(WeekCompletion $completion): int
    {
        return WeekCompletion::query()
            ->where('week_id', $completion->week_id)
            ->where('id', '<=', $completion->id)
            ->count();
    }

    public function prizeXmr(int $place): ?float
    {
        $map = config('game.prizes.xmr', []);
        $raw = $map[$place] ?? $map[(string) $place] ?? null;

        return $raw === null || $raw === '' ? null : (float) $raw;
    }

    public function paidPlaces(): int
    {
        return (int) config('game.prizes.paid_places', 5);
    }

    public function telegramMessage(Wallet $wallet, Week $week, int $place, ?float $prizeXmr): string
    {
        $prizeLine = $prizeXmr !== null
            ? sprintf('place %d / %s XMR', $place, $this->formatXmr($prizeXmr))
            : sprintf('place %d / unpaid (outside first %d)', $place, $this->paidPlaces());

        return implode("\n", [
            sprintf('Week %d clear — %s', $week->number, $prizeLine),
            'username: '.($wallet->username ?: '—'),
            'solana: '.$wallet->address,
            'xmr: '.($wallet->payout_address ?: '—'),
            'cleared: '.now()->utc()->format('Y-m-d H:i:s').' UTC',
        ]);
    }

    private function formatXmr(float $amount): string
    {
        $formatted = number_format($amount, 4, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }
}
