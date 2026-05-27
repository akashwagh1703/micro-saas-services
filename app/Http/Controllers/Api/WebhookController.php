<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\WhatsAppAccount;
use App\Services\Inbox\InboxService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private readonly InboxService $inboxService
    ) {}

    public function verify(Request $request, int $userId): Response
    {
        $account = WhatsAppAccount::where('user_id', $userId)->first();

        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $account && $token === $account->verify_token) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request, int $userId): Response
    {
        $payload = $request->all();

        if ($this->isStatusUpdate($payload)) {
            return response('OK', 200);
        }

        $account = WhatsAppAccount::where('user_id', $userId)->where('is_connected', true)->first();

        if (! $account) {
            return response('OK', 200);
        }

        try {
            $entry = $payload['entry'][0]['changes'][0]['value'] ?? null;
            $messages = $entry['messages'] ?? [];

            foreach ($messages as $waMessage) {
                if (($waMessage['type'] ?? '') !== 'text') {
                    continue;
                }

                $from = $waMessage['from'] ?? null;
                $text = $waMessage['text']['body'] ?? '';
                $waId = $waMessage['id'] ?? null;
                $contactName = $entry['contacts'][0]['profile']['name'] ?? null;

                if (! $from) {
                    continue;
                }

                $contact = $this->inboxService->findOrCreateContact($userId, $from, $contactName);
                $conversation = $this->inboxService->findOrCreateConversation($userId, $contact, $account);

                $message = $this->inboxService->storeIncomingMessage(
                    $userId,
                    $contact,
                    $conversation,
                    $text,
                    $waId,
                    ['raw' => $waMessage]
                );

                ProcessIncomingWhatsAppMessage::dispatch($message->id);
            }
        } catch (\Throwable $e) {
            Log::error('Webhook processing error', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }

        return response('OK', 200);
    }

    private function isStatusUpdate(array $payload): bool
    {
        $statuses = $payload['entry'][0]['changes'][0]['value']['statuses'] ?? null;

        return ! empty($statuses);
    }
}
