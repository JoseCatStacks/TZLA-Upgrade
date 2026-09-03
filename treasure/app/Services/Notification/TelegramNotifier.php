<?php

declare(strict_types=1);

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TelegramNotifier
{
    public function __construct(
        private readonly bool $enabled,
        private readonly ?string $botToken,
        private readonly ?string $chatId,
        private readonly string $apiBase = 'https://api.telegram.org',
        private readonly int $timeout = 5,
    ) {}

    public function send(string $message): bool
    {
        if (! $this->enabled || $this->botToken === null || $this->chatId === null) {
            Log::info('Telegram notify skipped', ['message' => $message]);

            return false;
        }

        $url = sprintf('%s/bot%s/sendMessage', rtrim($this->apiBase, '/'), $this->botToken);

        $response = Http::timeout($this->timeout)->asJson()->post($url, [
            'chat_id' => $this->chatId,
            'text' => $message,
            'disable_web_page_preview' => true,
        ]);

        if (! $response->ok()) {
            Log::warning('Telegram send failed', ['status' => $response->status(), 'body' => $response->body()]);

            return false;
        }

        return true;
    }
}
