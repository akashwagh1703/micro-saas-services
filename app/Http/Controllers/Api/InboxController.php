<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Inbox\InboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function __construct(
        private readonly InboxService $inboxService
    ) {}

    public function conversations(Request $request): JsonResponse
    {
        $query = Conversation::where('user_id', $request->user()->id)
            ->with('contact')
            ->orderByDesc('last_message_at');

        if ($search = $request->query('search')) {
            $query->whereHas('contact', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(20));
    }

    public function messages(Request $request, int $conversationId): JsonResponse
    {
        $conversation = Conversation::where('user_id', $request->user()->id)->findOrFail($conversationId);

        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->paginate(50);

        $conversation->update(['unread_count' => 0]);

        return response()->json([
            'conversation' => $conversation->load('contact'),
            'messages' => $messages,
        ]);
    }

    public function send(Request $request, int $conversationId): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:4096'],
        ]);

        $result = $this->inboxService->sendOutgoingMessage(
            $request->user()->id,
            $conversationId,
            $validated['content']
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
