<?php

namespace App\Jobs;

use App\Services\Inbox\InboxService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $userId,
        public int $conversationId,
        public string $content
    ) {}

    public function handle(InboxService $inboxService): void
    {
        $inboxService->sendOutgoingMessage($this->userId, $this->conversationId, $this->content);
    }
}
