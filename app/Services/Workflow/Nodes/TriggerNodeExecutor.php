<?php

namespace App\Services\Workflow\Nodes;

use App\Models\WorkflowExecution;

class TriggerNodeExecutor implements NodeExecutorInterface
{
    public function execute(WorkflowExecution $execution, array $node, array $context): array
    {
        return [
            'success' => true,
            'output' => [
                'message' => $context['message'] ?? '',
                'contact_phone' => $context['contact_phone'] ?? '',
                'contact_name' => $context['contact_name'] ?? '',
            ],
        ];
    }
}
