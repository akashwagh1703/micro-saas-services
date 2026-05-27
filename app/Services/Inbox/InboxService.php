<?php

namespace App\Services\Inbox;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Services\ActivityLogger;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Support\Facades\DB;

class InboxService
{
    public function __construct(
        private readonly WhatsAppService $whatsAppService
    ) {}

    public function findOrCreateContact(int $userId, string $phone, ?string $name = null): Contact
    {
        $normalizedPhone = preg_replace('/\D/', '', $phone);

        return Contact::firstOrCreate(
            ['user_id' => $userId, 'phone' => $normalizedPhone],
            ['name' => $name ?? $normalizedPhone]
        );
    }

    public function findOrCreateConversation(int $userId, Contact $contact, ?WhatsAppAccount $account = null): Conversation
    {
        return Conversation::firstOrCreate(
            ['user_id' => $userId, 'contact_id' => $contact->id],
            ['whats_app_account_id' => $account?->id, 'unread_count' => 0]
        );
    }

    public function storeIncomingMessage(
        int $userId,
        Contact $contact,
        Conversation $conversation,
        string $content,
        ?string $waMessageId = null,
        ?array $metadata = null
    ): Message {
        return DB::transaction(function () use ($userId, $contact, $conversation, $content, $waMessageId, $metadata) {
            $message = Message::create([
                'user_id' => $userId,
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
                'direction' => 'incoming',
                'content' => $content,
                'wa_message_id' => $waMessageId,
                'status' => 'received',
                'metadata' => $metadata,
            ]);

            $now = now();
            $contact->update(['last_message_at' => $now]);
            $conversation->update([
                'last_message_at' => $now,
                'unread_count' => $conversation->unread_count + 1,
            ]);

            ActivityLogger::log($userId, 'message_received', 'New message from '.$contact->phone, $content);

            return $message;
        });
    }

    public function sendOutgoingMessage(int $userId, int $conversationId, string $content): array
    {
        $conversation = Conversation::where('user_id', $userId)->with('contact')->findOrFail($conversationId);
        $account = WhatsAppAccount::where('user_id', $userId)->first();

        if (! $account?->is_connected) {
            return ['success' => false, 'message' => 'WhatsApp not connected'];
        }

        $result = $this->whatsAppService->sendTextMessage($account, $conversation->contact->phone, $content);

        $message = Message::create([
            'user_id' => $userId,
            'conversation_id' => $conversation->id,
            'contact_id' => $conversation->contact_id,
            'direction' => 'outgoing',
            'content' => $content,
            'wa_message_id' => $result['data']['messages'][0]['id'] ?? null,
            'status' => $result['success'] ? 'sent' : 'failed',
            'metadata' => $result,
        ]);

        $now = now();
        $conversation->contact->update(['last_message_at' => $now]);
        $conversation->update(['last_message_at' => $now]);

        ActivityLogger::log($userId, 'message_sent', 'Message sent to '.$conversation->contact->phone);

        return ['success' => $result['success'], 'message' => $message, 'error' => $result['message'] ?? null];
    }
}
