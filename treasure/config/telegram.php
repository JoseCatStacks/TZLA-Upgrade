<?php

declare(strict_types=1);

return [

    'enabled' => (bool) env('TELEGRAM_ENABLED', false),

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'chat_id' => env('TELEGRAM_CHAT_ID'),

    // Shared secret echoed back by Telegram in the X-Telegram-Bot-Api-Secret-Token
    // header. Register it with setWebhook. The webhook refuses every request when
    // this is unset, because the chat-id check alone is trivially forged.
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    'api_base' => env('TELEGRAM_API_BASE', 'https://api.telegram.org'),

    'timeout' => (int) env('TELEGRAM_TIMEOUT', 5),

];
