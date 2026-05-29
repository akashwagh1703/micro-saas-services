<?php

namespace App\Services\Workflow\Nodes;

use App\Models\WorkflowExecution;
use App\Services\Ai\AiService;

class AiNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        private readonly AiService $aiService
    ) {}

    public function execute(WorkflowExecution $execution, array $node, array $context): array
    {
        $config = $node['data'] ?? [];
        $result = $this->aiService->generate($execution->user_id, $config, $context);

        if (! $result['success']) {
            $fallback = $result['fallback'] ?? ($config['fallback_message'] ?? null);
            if ($fallback) {
                return ['success' => true, 'output' => ['ai_response' => $fallback, 'fallback' => true]];
            }

            return ['success' => false, 'output' => $result, 'stop' => true];
        }

        return ['success' => true, 'output' => ['ai_response' => $result['content'], 'usage' => $result['usage'] ?? null]];
    }
}
