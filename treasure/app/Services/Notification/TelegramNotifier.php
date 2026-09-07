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
        private readonly ?int $messageThreadId = null,
    ) {}

    public function send(string $message): bool
    {
        if (! $this->enabled || $this->botToken === null || $this->botToken === '' || $this->chatId === null || $this->chatId === '') {
            Log::info('Telegram notify skipped', ['message' => $message]);

            return false;
        }

        $url = sprintf('%s/bot%s/sendMessage', rtrim($this->apiBase, '/'), $this->botToken);

        $payload = [
            'chat_id' => $this->chatId,
            'text' => $message,
            'disable_web_page_preview' => true,
        ];

        if ($this->messageThreadId !== null && $this->messageThreadId > 0) {
            $payload['message_thread_id'] = $this->messageThreadId;
        }

        $response = Http::timeout($this->timeout)->asJson()->post($url, $payload);

        if (! $response->ok()) {
            Log::warning('Telegram send failed', ['status' => $response->status(), 'body' => $response->body()]);

            return false;
        }

        return true;
    }
}
