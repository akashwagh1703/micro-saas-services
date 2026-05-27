<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalApiService
{
    public function execute(array $config, array $variables = []): array
    {
        $url = $this->replaceVariables($config['url'] ?? '', $variables);
        $method = strtoupper($config['method'] ?? 'GET');
        $headers = $this->buildHeaders($config['headers'] ?? [], $variables);
        $body = $this->replaceVariablesInArray($config['body'] ?? [], $variables);
        $timeout = (int) ($config['timeout'] ?? 15);
        $retries = (int) ($config['retries'] ?? 1);

        $lastError = null;

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            try {
                $request = Http::withHeaders($headers)->timeout($timeout);

                $response = match ($method) {
                    'POST' => $request->post($url, $body),
                    'PUT' => $request->put($url, $body),
                    'PATCH' => $request->patch($url, $body),
                    'DELETE' => $request->delete($url, $body),
                    default => $request->get($url, $body),
                };

                return [
                    'success' => $response->successful(),
                    'status' => $response->status(),
                    'data' => $response->json() ?? $response->body(),
                ];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning('API node attempt failed', ['attempt' => $attempt, 'error' => $lastError]);
            }
        }

        return ['success' => false, 'error' => $lastError ?? 'API request failed'];
    }

    private function buildHeaders(array $headers, array $variables): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            $result[$key] = $this->replaceVariables((string) $value, $variables);
        }

        return $result;
    }

    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{'.$key.'}}', (string) $value, $text);
        }

        return $text;
    }

    private function replaceVariablesInArray(array $data, array $variables): array
    {
        $json = json_encode($data);
        $replaced = $this->replaceVariables($json, $variables);

        return json_decode($replaced, true) ?? [];
    }
}
