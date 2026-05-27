<?php

namespace App\Services\Workflow\Nodes;

use App\Models\WorkflowExecution;
use App\Services\Api\ExternalApiService;

class ApiNodeExecutor implements NodeExecutorInterface
{
    public function __construct(
        private readonly ExternalApiService $apiService
    ) {}

    public function execute(WorkflowExecution $execution, array $node, array $context): array
    {
        $config = $node['data'] ?? [];
        $result = $this->apiService->execute($config, $context);

        if (! $result['success'] && ! empty($config['use_fallback'])) {
            return [
                'success' => true,
                'output' => ['fallback' => true, 'error' => $result['error'] ?? null],
            ];
        }

        return [
            'success' => $result['success'],
            'output' => $result,
            'stop' => ! $result['success'] && empty($config['use_fallback']),
        ];
    }
}
