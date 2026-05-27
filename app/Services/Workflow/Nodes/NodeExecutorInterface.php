<?php

namespace App\Services\Workflow\Nodes;

use App\Models\WorkflowExecution;

interface NodeExecutorInterface
{
    public function execute(WorkflowExecution $execution, array $node, array $context): array;
}
