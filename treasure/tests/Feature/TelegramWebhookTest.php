<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Week;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'test-webhook-secret';

    private function update(string $text): array
    {
        return [
            'message' => [
                'chat' => ['id' => 4242],
                'text' => $text,
            ],
        ];
    }

    private function seedAnswer(): void
    {
        $week = Week::create([
            'number' => 1,
            'title' => 'Bones',
            'starts_at' => now()->subDay(),
            'reward_description' => '10 TZLA',
        ]);
        Word::create([
            'week_id' => $week->id,
            'position' => 1,
            'answer_normalized' => 'parchment',
            'hint' => 'Old and folded',
        ]);
    }

    public function test_webhook_rejects_request_without_secret_header(): void
    {
        config(['telegram.webhook_secret' => self::SECRET, 'telegram.chat_id' => '4242']);

        $this->postJson('/api/telegram/webhook', $this->update('/help'))
            ->assertStatus(403);
    }

    public function test_webhook_rejects_wrong_secret(): void
    {
        config(['telegram.webhook_secret' => self::SECRET, 'telegram.chat_id' => '4242']);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'not-the-secret')
            ->postJson('/api/telegram/webhook', $this->update('/help'))
            ->assertStatus(403);
    }

    public function test_webhook_fails_closed_when_no_secret_is_configured(): void
    {
        config(['telegram.webhook_secret' => null, 'telegram.chat_id' => '4242']);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'anything')
            ->postJson('/api/telegram/webhook', $this->update('/help'))
            ->assertStatus(403);
    }

    public function test_forged_request_cannot_read_answers(): void
    {
        config(['telegram.webhook_secret' => self::SECRET, 'telegram.chat_id' => '4242']);
        $this->seedAnswer();

        // Knowing the chat id used to be enough to dump every answer.
        $response = $this->postJson('/api/telegram/webhook', $this->update('/wordlist 1'));

        $response->assertStatus(403);
        $this->assertStringNotContainsString('parchment', $response->getContent());
    }

    public function test_webhook_accepts_request_with_valid_secret(): void
    {
        config([
            'telegram.webhook_secret' => self::SECRET,
            'telegram.chat_id' => '4242',
            'telegram.enabled' => false,
        ]);

        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', self::SECRET)
            ->postJson('/api/telegram/webhook', $this->update('/help'))
            ->assertStatus(200);
    }
}
