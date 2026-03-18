<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AiClient
{
    private string $driver;

    /** @var array<string, mixed> */
    private array $config;

    public function __construct()
    {
        $this->driver = config('ai.driver', 'openai');
        $this->config = config("ai.providers.{$this->driver}", []);
    }

    /**
     * Send a chat completion request to the configured AI provider.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    public function chat(array $messages, ?string $model = null, ?int $maxTokens = null): array
    {
        return match ($this->driver) {
            'anthropic' => $this->callAnthropic($messages, $model, $maxTokens),
            default => $this->callOpenAi($messages, $model, $maxTokens),
        };
    }

    /**
     * Decode a JSON string returned from the AI, stripping markdown fences first.
     *
     * @return array<string, mixed>
     */
    public function decodeJson(string $raw): array
    {
        $stripped = $this->stripFences($raw);
        $decoded = json_decode($stripped, true);

        if (! is_array($decoded)) {
            Log::warning('AiClient: failed to decode AI JSON response', [
                'driver' => $this->driver,
                'raw_length' => strlen($raw),
                'raw_preview' => substr($raw, 0, 300),
            ]);

            return [];
        }

        return $decoded;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    private function callOpenAi(array $messages, ?string $model, ?int $maxTokens): array
    {
        $apiKey = $this->config['api_key'] ?? null;
        $baseUrl = $this->config['base_url'] ?? 'https://api.openai.com/v1';
        $model = $model ?? ($this->config['model'] ?? 'gpt-4o');
        $tokens = $maxTokens ?? ($this->config['max_tokens'] ?? 4096);
        $timeout = $this->config['timeout'] ?? 120;

        $response = Http::withToken($apiKey)
            ->timeout($timeout)
            ->post("{$baseUrl}/chat/completions", [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $tokens,
                'temperature' => $this->config['temperature'] ?? 0.2,
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "OpenAI request failed [{$response->status()}]: ".$response->body()
            );
        }

        return $response->json();
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    private function callAnthropic(array $messages, ?string $model, ?int $maxTokens): array
    {
        $apiKey = $this->config['api_key'] ?? null;
        $baseUrl = $this->config['base_url'] ?? 'https://api.anthropic.com/v1';
        $model = $model ?? ($this->config['model'] ?? 'claude-3-7-sonnet-20250219');
        $tokens = $maxTokens ?? ($this->config['max_tokens'] ?? 4096);
        $timeout = $this->config['timeout'] ?? 120;
        $version = $this->config['version'] ?? '2023-06-01';

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => $version,
        ])
            ->timeout($timeout)
            ->post("{$baseUrl}/messages", [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $tokens,
                'temperature' => $this->config['temperature'] ?? 0.2,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                "Anthropic request failed [{$response->status()}]: ".$response->body()
            );
        }

        return $response->json();
    }

    /**
     * Strip markdown code fences (```json ... ```) from a string.
     */
    private function stripFences(string $raw): string
    {
        return trim(preg_replace('/^```(?:json)?\s*/i', '', preg_replace('/\s*```$/', '', trim($raw))) ?? $raw);
    }
}
