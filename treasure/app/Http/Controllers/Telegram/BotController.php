<?php

declare(strict_types=1);

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\BotHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

final class BotController extends Controller
{
    public function webhook(Request $request, BotHandler $handler): Response
    {
        if (! $this->isFromTelegram($request)) {
            return response('', 403);
        }

        $update = $request->json()->all();
        $message = $update['message'] ?? $update['edited_message'] ?? null;

        if ($message !== null) {
            $handler->handle($message);
        }

        return response('', 200);
    }

    /**
     * The bot exposes admin commands that can read and rewrite puzzle answers,
     * so the webhook must prove the request actually came from Telegram. The
     * chat id inside the payload is attacker-controlled and proves nothing.
     */
    private function isFromTelegram(Request $request): bool
    {
        $expected = (string) config('telegram.webhook_secret', '');

        if ($expected === '') {
            Log::warning('Telegram webhook rejected: TELEGRAM_WEBHOOK_SECRET is not configured.');

            return false;
        }

        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        return $provided !== '' && hash_equals($expected, $provided);
    }
}
