<?php

namespace App\Services\Workflow\Nodes;

use App\Models\WorkflowExecution;

class ConditionNodeExecutor implements NodeExecutorInterface
{
    public function execute(WorkflowExecution $execution, array $node, array $context): array
    {
        $data = $node['data'] ?? [];
        $field = $data['field'] ?? 'message';
        $operator = $data['operator'] ?? 'contains';
        $value = $data['value'] ?? '';

        $actual = (string) ($context[$field] ?? $context['message'] ?? '');
        $matched = match ($operator) {
            'equals' => $actual === $value,
            'starts_with' => str_starts_with($actual, $value),
            'ends_with' => str_ends_with($actual, $value),
            default => str_contains(strtolower($actual), strtolower($value)),
        };

        return [
            'success' => true,
            'output' => ['matched' => $matched],
            'stop' => ! $matched,
        ];
    }
}
