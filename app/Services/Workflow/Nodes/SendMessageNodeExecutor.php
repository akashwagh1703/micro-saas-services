<?php

namespace App\Services\Workflow\Nodes;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\WorkflowExecution;

class SendMessageNodeExecutor implements NodeExecutorInterface
{
    public function execute(WorkflowExecution $execution, array $node, array $context): array
    {
        $data = $node['data'] ?? [];
        $template = $data['message'] ?? '';
        $message = $template;

        foreach ($context as $key => $value) {
            $message = str_replace('{{'.$key.'}}', (string) $value, $message);
        }

        SendWhatsAppMessageJob::dispatch(
            $execution->user_id,
            $execution->conversation_id,
            $message
        );

        return [
            'success' => true,
            'output' => ['queued' => true, 'message' => $message],
        ];
    }
}
