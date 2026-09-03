<?php

declare(strict_types=1);

namespace App\Console\Commands\Telegram;

use App\Services\Telegram\BotHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class PollCommand extends Command
{
    protected $signature = 'telegram:poll';

    protected $description = 'Long-poll the Telegram Bot API and handle incoming messages.';

    public function handle(BotHandler $handler): int
    {
        $token = config('telegram.bot_token');
        $apiBase = rtrim((string) config('telegram.api_base', 'https://api.telegram.org'), '/');

        if (! $token) {
            $this->error('TELEGRAM_BOT_TOKEN is not set.');

            return self::FAILURE;
        }

        $this->info('Telegram bot polling started. Press Ctrl+C to stop.');

        $offset = 0;

        // Remove any existing webhook so polling works
        Http::timeout(10)->asJson()->post("{$apiBase}/bot{$token}/deleteWebhook");

        while (true) {
            try {
                $response = Http::timeout(35)->asJson()->post("{$apiBase}/bot{$token}/getUpdates", [
                    'offset' => $offset,
                    'timeout' => 30,
                    'allowed_updates' => ['message'],
                ]);

                if (! $response->ok()) {
                    $this->warn('getUpdates failed: ' . $response->body());
                    sleep(5);

                    continue;
                }

                $updates = $response->json('result') ?? [];

                foreach ($updates as $update) {
                    $offset = $update['update_id'] + 1;
                    $message = $update['message'] ?? null;

                    if ($message !== null) {
                        $handler->handle($message);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Telegram poll error', ['error' => $e->getMessage()]);
                $this->warn('Poll error: ' . $e->getMessage());
                sleep(5);
            }
        }
    }
}
