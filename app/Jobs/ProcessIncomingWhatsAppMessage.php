<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessIncomingWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $messageId
    ) {}

    public function handle(): void
    {
        $message = Message::with(['contact', 'conversation'])->find($this->messageId);

        if (! $message || $message->direction !== 'incoming') {
            return;
        }

        if (! empty($message->metadata['from_bot'])) {
            return;
        }

        $workflows = Workflow::where('user_id', $message->user_id)
            ->where('status', 'published')
            ->where('is_active', true)
            ->where('trigger_type', 'message_received')
            ->get();

        foreach ($workflows as $workflow) {
            if (! $this->triggerMatches($workflow, (string) $message->content)) {
                continue;
            }

            $execution = WorkflowExecution::create([
                'user_id' => $message->user_id,
                'workflow_id' => $workflow->id,
                'contact_id' => $message->contact_id,
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
                'status' => 'pending',
                'context' => [
                    'message' => $message->content,
                    'contact_phone' => $message->contact->phone,
                    'contact_name' => $message->contact->name,
                ],
            ]);

            ExecuteWorkflowJob::dispatch($execution->id);
        }
    }

    /**
     * Only fire a workflow when the incoming message satisfies the keyword
     * filter configured on its trigger node. A trigger with no keywords
     * matches every message (preserving the original behaviour).
     */
    private function triggerMatches(Workflow $workflow, string $messageText): bool
    {
        $nodes = $workflow->definition['nodes'] ?? [];
        $trigger = collect($nodes)->firstWhere('type', 'trigger');
        $data = $trigger['data'] ?? [];

        $raw = $data['keywords'] ?? '';
        $keywords = collect(is_array($raw) ? $raw : explode(',', (string) $raw))
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->values();

        if ($keywords->isEmpty()) {
            return true;
        }

        $match = $data['match'] ?? 'any';
        $haystack = mb_strtolower($messageText);

        $hits = $keywords->filter(fn ($k) => match ($match) {
            'exact' => $haystack === mb_strtolower($k),
            default => str_contains($haystack, mb_strtolower($k)),
        });

        return $match === 'all' ? $hits->count() === $keywords->count() : $hits->isNotEmpty();
    }
}
