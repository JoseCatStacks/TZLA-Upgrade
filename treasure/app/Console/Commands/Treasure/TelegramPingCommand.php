<?php

declare(strict_types=1);

namespace App\Console\Commands\Treasure;

use App\Services\Guess\PrizeLadder;
use App\Services\Notification\TelegramNotifier;
use Illuminate\Console\Command;

final class TelegramPingCommand extends Command
{
    protected $signature = 'treasure:telegram:ping
        {--sample : Send a sample winner card instead of a short ping}';

    protected $description = 'Send a test message to the configured Telegram winners chat.';

    public function handle(TelegramNotifier $notifier, PrizeLadder $prizes): int
    {
        $wallet = new \App\Models\Wallet([
            'address' => 'TzlaTest111111111111111111111111111111111',
            'username' => 'test-run',
            'payout_address' => '4'.str_repeat('A', 94),
        ]);
        $week = new \App\Models\Week(['number' => 1]);

        $message = $this->option('sample')
            ? $prizes->telegramMessage($wallet, $week, 1, 0.6)
            : 'TZLA local test ping. If you see this, the bot is wired to this chat.';

        if (! $notifier->send($message)) {
            $this->error('Telegram send skipped or failed. Check TELEGRAM_ENABLED, TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_ID.');

            return self::FAILURE;
        }

        $this->info('Sent to chat '.config('telegram.chat_id'));

        return self::SUCCESS;
    }
}
