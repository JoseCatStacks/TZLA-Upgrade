<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Models\Week;
use App\Models\Word;
use App\Services\Guess\GuessNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class BotHandler
{
    public function __construct(private readonly GuessNormalizer $normalizer) {}

    public function handle(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        $allowedChatId = (string) config('telegram.chat_id');

        if ($chatId !== $allowedChatId) {
            return;
        }

        $text = trim($message['text'] ?? '');
        if ($text === '' || $text[0] !== '/') {
            return;
        }

        // Strip @BotName suffix from commands
        $text = (string) preg_replace('/^(\/\w+)@\w+/', '$1', $text);

        [$command, $args] = $this->parseCommand($text);

        $reply = match ($command) {
            '/help'           => $this->handleHelp(),
            '/weeklist'       => $this->handleWeekList(),
            '/weekactivate'   => $this->handleWeekActivate($args),
            '/weekdeactivate' => $this->handleWeekDeactivate($args),
            '/weekreward'     => $this->handleWeekReward($args),
            '/weekclaim'      => $this->handleWeekClaim($args),
            '/weekunclaim'    => $this->handleWeekUnclaim($args),
            '/weektitle'      => $this->handleWeekTitle($args),
            '/weekcreate'     => $this->handleWeekCreate($args),
            '/wordlist'       => $this->handleWordList($args),
            '/wordset'        => $this->handleWordSet($args),
            '/wordhint'       => $this->handleWordHint($args),
            default           => null,
        };

        if ($reply !== null) {
            $this->sendMessage((int) $chatId, $reply);
        }
    }

    /** @return array{string, list<string>} */
    private function parseCommand(string $text): array
    {
        $parts = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $command = strtolower(array_shift($parts) ?? '');

        return [$command, $parts];
    }

    private function handleHelp(): string
    {
        return implode("\n", [
            '📋 *Admin Commands*',
            '',
            '*Weeks:*',
            '`/weeklist` — list all weeks',
            '`/weekcreate <n>` — create week (inactive by default)',
            '`/weekactivate <n>` — make week visible to players',
            '`/weekdeactivate <n>` — hide week from players',
            '`/weektitle <n> <title>` — set week title',
            '`/weekreward <n> <text>` — set reward description',
            '`/weekclaim <n>` — mark week reward as claimed',
            '`/weekunclaim <n>` — mark week reward as unclaimed',
            '',
            '*Words:*',
            '`/wordlist <week>` — list words for a week',
            '`/wordset <week> <pos> <answer>` — set word answer',
            '`/wordhint <week> <pos> <hint>` — set word hint',
        ]);
    }

    private function handleWeekList(): string
    {
        $weeks = Week::query()->orderBy('number')->get();

        if ($weeks->isEmpty()) {
            return 'No weeks found. Use /weekcreate to add one.';
        }

        $lines = ['📅 *Weeks:*', ''];
        foreach ($weeks as $week) {
            $status = $week->active
                ? ($week->isUnlocked() ? '✅ active' : '⏳ active (future)')
                : '❌ inactive';
            $wordCount = $week->words()->count();
            $lines[] = sprintf('*Week %d* — %s | %d word(s)', $week->number, $status, $wordCount);
            if ($week->title) {
                $lines[] = '  Title: ' . $week->title;
            }
            if ($week->reward_description) {
                $claimed = $week->reward_claimed ? ' ✅ claimed' : ' ⏳ unclaimed';
                $lines[] = '  Reward: ' . $week->reward_description . $claimed;
            }
        }

        return implode("\n", $lines);
    }

    /** @param list<string> $args */
    private function handleWeekCreate(array $args): string
    {
        if (count($args) < 1 || ! ctype_digit($args[0])) {
            return '❌ Usage: `/weekcreate <week_number>`';
        }

        $number = (int) $args[0];
        if (Week::query()->where('number', $number)->exists()) {
            return "❌ Week {$number} already exists.";
        }

        Week::create(['number' => $number, 'active' => false, 'starts_at' => now()]);

        return "✅ Created week {$number} (inactive). Use /weekactivate {$number} to enable it.";
    }

    /** @param list<string> $args */
    private function handleWeekActivate(array $args): string
    {
        $week = $this->resolveWeek($args);
        if (is_string($week)) {
            return $week;
        }

        $week->active = true;
        $week->save();

        return "✅ Week {$week->number} is now *active* and visible to players.";
    }

    /** @param list<string> $args */
    private function handleWeekDeactivate(array $args): string
    {
        $week = $this->resolveWeek($args);
        if (is_string($week)) {
            return $week;
        }

        $week->active = false;
        $week->save();

        return "✅ Week {$week->number} is now *inactive* and hidden from players.";
    }

    /** @param list<string> $args */
    private function handleWeekReward(array $args): string
    {
        if (count($args) < 2 || ! ctype_digit($args[0])) {
            return '❌ Usage: `/weekreward <week_number> <reward text>`';
        }

        $week = $this->resolveWeek($args);
        if (is_string($week)) {
            return $week;
        }

        $reward = implode(' ', array_slice($args, 1));
        $week->reward_description = $reward;
        $week->save();

        return "✅ Week {$week->number} reward set to: {$reward}";
    }

    /** @param list<string> $args */
    private function handleWeekClaim(array $args): string
    {
        $week = $this->resolveWeek($args);
        if (is_string($week)) {
            return $week;
        }

        $week->reward_claimed = true;
        $week->save();

        return "✅ Week {$week->number} reward marked as *claimed*.";
    }

    /** @param list<string> $args */
    private function handleWeekUnclaim(array $args): string
    {
        $week = $this->resolveWeek($args);
        if (is_string($week)) {
            return $week;
        }

        $week->reward_claimed = false;
        $week->save();

        return "✅ Week {$week->number} reward marked as *unclaimed*.";
    }

    /** @param list<string> $args */
    private function handleWeekTitle(array $args): string
    {
        if (count($args) < 2 || ! ctype_digit($args[0])) {
            return '❌ Usage: `/weektitle <week_number> <title>`';
        }

        $week = $this->resolveWeek($args);
        if (is_string($week)) {
            return $week;
        }

        $title = implode(' ', array_slice($args, 1));
        $week->title = $title;
        $week->save();

        return "✅ Week {$week->number} title set to: {$title}";
    }

    /** @param list<string> $args */
    private function handleWordList(array $args): string
    {
        $week = $this->resolveWeek($args);
        if (is_string($week)) {
            return $week;
        }

        $words = $week->words()->orderBy('position')->get();

        if ($words->isEmpty()) {
            return "Week {$week->number} has no words yet. Use /wordset {$week->number} <pos> <answer>";
        }

        $lines = ["📝 *Week {$week->number} words:*", ''];
        foreach ($words as $word) {
            $lines[] = sprintf('*Pos %d:* `%s`', $word->position, $word->answer_normalized);
            if ($word->hint) {
                $lines[] = '  Hint: ' . $word->hint;
            }
        }

        return implode("\n", $lines);
    }

    /** @param list<string> $args */
    private function handleWordSet(array $args): string
    {
        if (count($args) < 3 || ! ctype_digit($args[0]) || ! ctype_digit($args[1])) {
            return '❌ Usage: `/wordset <week_number> <position> <answer>`';
        }

        $week = $this->resolveWeek($args);
        if (is_string($week)) {
            return $week;
        }

        $position = (int) $args[1];
        if ($position < 1) {
            return '❌ Position must be a positive integer.';
        }

        $normalized = $this->normalizer->normalize(implode(' ', array_slice($args, 2)));
        if ($normalized === '') {
            return '❌ Answer normalizes to empty. Use alphanumeric characters.';
        }

        Word::updateOrCreate(
            ['week_id' => $week->id, 'position' => $position],
            ['answer_normalized' => $normalized],
        );

        return "✅ Week {$week->number}, position {$position} answer set to: `{$normalized}`";
    }

    /** @param list<string> $args */
    private function handleWordHint(array $args): string
    {
        if (count($args) < 3 || ! ctype_digit($args[0]) || ! ctype_digit($args[1])) {
            return '❌ Usage: `/wordhint <week_number> <position> <hint text>`';
        }

        $week = $this->resolveWeek($args);
        if (is_string($week)) {
            return $week;
        }

        $position = (int) $args[1];
        $word = Word::query()->where('week_id', $week->id)->where('position', $position)->first();

        if ($word === null) {
            return "❌ No word at position {$position} for week {$week->number}. Set the answer first with /wordset.";
        }

        $hint = implode(' ', array_slice($args, 2));
        $word->hint = $hint;
        $word->save();

        return "✅ Week {$week->number}, position {$position} hint set to: {$hint}";
    }

    /**
     * @param list<string> $args
     * @return Week|string
     */
    private function resolveWeek(array $args): Week|string
    {
        if (count($args) < 1 || ! ctype_digit($args[0])) {
            return '❌ Please provide a valid week number.';
        }

        $number = (int) $args[0];
        $week = Week::query()->where('number', $number)->first();

        if ($week === null) {
            return "❌ Week {$number} not found. Use /weekcreate {$number} to create it.";
        }

        return $week;
    }

    private function sendMessage(int $chatId, string $text): void
    {
        $token = config('telegram.bot_token');
        $apiBase = rtrim((string) config('telegram.api_base', 'https://api.telegram.org'), '/');

        if (! $token) {
            Log::warning('Telegram bot token not configured, cannot send reply.');

            return;
        }

        $response = Http::timeout((int) config('telegram.timeout', 5))
            ->asJson()
            ->post("{$apiBase}/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ]);

        if (! $response->ok()) {
            Log::warning('Telegram bot reply failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
