<?php

namespace App\Jobs;

use App\Enums\MessagingPlatform;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWebhookNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 60];

    public function __construct(
        public readonly string $url,
        public readonly MessagingPlatform $platform,
        public readonly string $title,
        public readonly string $body,
    ) {}

    public function handle(): void
    {
        $payload = match ($this->platform) {
            MessagingPlatform::Slack => $this->buildSlackPayload(),
            MessagingPlatform::Teams => $this->buildTeamsPayload(),
            MessagingPlatform::Discord => $this->buildDiscordPayload(),
        };

        $response = Http::timeout(10)->post($this->url, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "Webhook delivery failed [{$this->platform->value}]: HTTP {$response->status()} — {$response->body()}",
            );
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Webhook notification delivery failed after all retries.', [
            'platform' => $this->platform->value,
            'title' => $this->title,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSlackPayload(): array
    {
        return [
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => ['type' => 'plain_text', 'text' => $this->title, 'emoji' => true],
                ],
                [
                    'type' => 'section',
                    'text' => ['type' => 'mrkdwn', 'text' => $this->body],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTeamsPayload(): array
    {
        return [
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'content' => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type' => 'AdaptiveCard',
                        'version' => '1.2',
                        'body' => [
                            [
                                'type' => 'TextBlock',
                                'size' => 'Medium',
                                'weight' => 'Bolder',
                                'text' => $this->title,
                            ],
                            [
                                'type' => 'TextBlock',
                                'text' => $this->body,
                                'wrap' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDiscordPayload(): array
    {
        return [
            'embeds' => [
                [
                    'title' => $this->title,
                    'description' => $this->body,
                    'color' => 5814783, // #58A9FF — neutral blue
                ],
            ],
        ];
    }
}
