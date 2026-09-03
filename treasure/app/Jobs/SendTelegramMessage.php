<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Notification\TelegramNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly string $message) {}

    public function handle(TelegramNotifier $notifier): void
    {
        $notifier->send($this->message);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendTelegramMessage failed', [
            'message' => $this->message,
            'error' => $e->getMessage(),
        ]);
    }
}
