<?php

namespace App\Services\Ai;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiService
{
    public function __construct(
        private readonly SettingsService $settingsService
    ) {}

    public function generate(int $userId, array $config, array $variables = []): array
    {
        $provider = $config['provider'] ?? 'openrouter';
        $prompt = $this->replaceVariables($config['prompt'] ?? '', $variables);
        $model = $config['model'] ?? 'openai/gpt-4o-mini';
        $temperature = (float) ($config['temperature'] ?? 0.7);
        $maxTokens = (int) ($config['max_tokens'] ?? 256);

        $apiKey = $provider === 'openai'
            ? $this->settingsService->get($userId, 'openai_api_key')
            : $this->settingsService->get($userId, 'openrouter_api_key');

        if (! $apiKey) {
            return ['success' => false, 'error' => 'AI API key not configured', 'fallback' => $config['fallback_message'] ?? null];
        }

        try {
            $baseUrl = $provider === 'openai'
                ? 'https://api.openai.com/v1'
                : 'https://openrouter.ai/api/v1';

            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');

                return ['success' => true, 'content' => $content, 'usage' => $response->json('usage')];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message', 'AI request failed'),
                'fallback' => $config['fallback_message'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::error('AI generation failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage(), 'fallback' => $config['fallback_message'] ?? null];
        }
    }

    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{'.$key.'}}', (string) $value, $text);
        }

        return $text;
    }
}
