<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Claude API client (Messages API with a forced tool call, so the
 * answer always comes back as structured JSON).
 * Docs: https://docs.claude.com/en/api/messages
 * Needs ANTHROPIC_API_KEY in .env.
 */
class Claude
{
    public static function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Ask Claude to fill in one tool's input. Returns that input as an array.
     *
     * @param  array<string, mixed>  $tool  {name, description, input_schema}
     */
    public function callTool(string $model, string $system, string $userMessage, array $tool, int $maxTokens = 2000): array
    {
        if (! self::isConfigured()) {
            throw new RuntimeException('Claude API key missing. Add ANTHROPIC_API_KEY to your .env file.');
        }

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])
            ->acceptJson()
            ->timeout(90)
            ->retry(2, 2000, fn ($e, $request) => $e instanceof \Illuminate\Http\Client\ConnectionException
                || in_array(optional($e->response ?? null)->status(), [429, 500, 529], true), throw: false)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => $maxTokens,
                // The system prompt (package rules) is the same every time, so cache it to save cost.
                'system' => [['type' => 'text', 'text' => $system, 'cache_control' => ['type' => 'ephemeral']]],
                'tools' => [$tool],
                'tool_choice' => ['type' => 'tool', 'name' => $tool['name']],
                'messages' => [['role' => 'user', 'content' => $userMessage]],
            ]);

        if ($response->failed()) {
            $message = $response->json('error.message');

            throw new RuntimeException(match ($response->status()) {
                401 => 'Claude rejected the API key. Check ANTHROPIC_API_KEY in your .env file.',
                400 => str_contains((string) $message, 'credit') ? 'Your Claude API account is out of credits.' : "Claude API error: {$message}",
                429 => 'Claude API rate limit hit. Try again in a minute.',
                default => 'Claude API error (HTTP ' . $response->status() . ')' . ($message ? ": {$message}" : ''),
            });
        }

        foreach ($response->json('content', []) as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === $tool['name']) {
                return $block['input'] ?? [];
            }
        }

        throw new RuntimeException('Claude did not return an audit. Try again.');
    }
}
