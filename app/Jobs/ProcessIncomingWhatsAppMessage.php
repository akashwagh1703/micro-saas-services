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
}
