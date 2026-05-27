<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private const API_BASE = 'https://graph.facebook.com/v21.0';

    public function testConnection(WhatsAppAccount $account): array
    {
        if (! $account->access_token || ! $account->phone_number_id) {
            return ['success' => false, 'message' => 'Missing credentials'];
        }

        try {
            $response = Http::withToken($account->access_token)
                ->timeout(15)
                ->get(self::API_BASE.'/'.$account->phone_number_id);

            if ($response->successful()) {
                $data = $response->json();
                $account->update([
                    'is_connected' => true,
                    'connected_at' => now(),
                    'display_phone_number' => $data['display_phone_number'] ?? $account->display_phone_number,
                ]);

                return ['success' => true, 'message' => 'Connected successfully', 'data' => $data];
            }

            return ['success' => false, 'message' => $response->json('error.message', 'Connection failed')];
        } catch (\Throwable $e) {
            Log::error('WhatsApp test connection failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function sendTextMessage(WhatsAppAccount $account, string $to, string $text): array
    {
        $phone = preg_replace('/\D/', '', $to);

        try {
            $response = Http::withToken($account->access_token)
                ->timeout(20)
                ->post(self::API_BASE.'/'.$account->phone_number_id.'/messages', [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'text',
                    'text' => ['body' => $text],
                ]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            return ['success' => false, 'message' => $response->json('error.message', 'Send failed')];
        } catch (\Throwable $e) {
            Log::error('WhatsApp send failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function verifyWebhookSignature(string $payload, ?string $signature, string $appSecret): bool
    {
        if (! $signature || ! $appSecret) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $payload, $appSecret);

        return hash_equals($expected, $signature);
    }
}
