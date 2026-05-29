<?php

namespace App\Services\Workflow;

use App\Models\ExecutionLog;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Services\Workflow\Nodes\AiNodeExecutor;
use App\Services\Workflow\Nodes\ApiNodeExecutor;
use App\Services\Workflow\Nodes\ConditionNodeExecutor;
use App\Services\Workflow\Nodes\NodeExecutorInterface;
use App\Services\Workflow\Nodes\SendMessageNodeExecutor;
use App\Services\Workflow\Nodes\TriggerNodeExecutor;
use Illuminate\Support\Facades\Log;

class WorkflowExecutionService
{
    private const MAX_NODES = 20;

    public function __construct(
        private readonly WorkflowValidator $validator
    ) {}

    public function execute(WorkflowExecution $execution): void
    {
        $workflow = Workflow::where('user_id', $execution->user_id)->findOrFail($execution->workflow_id);

        if ($workflow->status !== 'published' || ! $workflow->is_active) {
            $execution->update(['status' => 'failed', 'error_message' => 'Workflow not active']);

            return;
        }

        $errors = $this->validator->validate($workflow->definition);
        if (! empty($errors)) {
            $execution->update(['status' => 'failed', 'error_message' => implode(', ', $errors)]);

            return;
        }

        $execution->update(['status' => 'running', 'started_at' => now()]);
        $context = $execution->context ?? [];

        $definition = $workflow->definition;
        $nodesById = collect($definition['nodes'])->keyBy('id');
        $edges = collect($definition['edges'] ?? []);
        $trigger = collect($definition['nodes'])->firstWhere('type', 'trigger');

        if (! $trigger) {
            $execution->update(['status' => 'failed', 'error_message' => 'No trigger node']);

            return;
        }

        $executors = $this->getExecutors();
        $currentId = $trigger['id'];
        $visited = [];
        $steps = 0;

        try {
            while ($currentId !== null && ! isset($visited[$currentId])) {
                if (++$steps > self::MAX_NODES) {
                    $execution->update(['status' => 'failed', 'error_message' => 'Too many nodes']);

                    return;
                }

                $visited[$currentId] = true;
                $node = $nodesById->get($currentId);
                if (! $node) {
                    break;
                }

                $executor = $executors[$node['type']] ?? null;
                if (! $executor) {
                    $currentId = $this->resolveNextNodeId($edges, $node, null);

                    continue;
                }

                $start = microtime(true);
                $log = ExecutionLog::create([
                    'workflow_execution_id' => $execution->id,
                    'node_id' => $node['id'],
                    'node_type' => $node['type'],
                    'status' => 'running',
                    'input' => $context,
                ]);

                $result = $executor->execute($execution, $node, $context);
                $context = array_merge($context, $result['output'] ?? []);

                $log->update([
                    'status' => ($result['success'] ?? false) ? 'completed' : 'failed',
                    'output' => $result['output'] ?? null,
                    'error_message' => $result['error'] ?? null,
                    'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                ]);

                if (! empty($result['stop'])) {
                    break;
                }

                $currentId = $this->resolveNextNodeId($edges, $node, $result);
            }

            $execution->update([
                'status' => 'completed',
                'context' => $context,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Workflow execution failed', ['execution_id' => $execution->id, 'error' => $e->getMessage()]);
            $execution->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Decide the next node id to run, honouring condition branches.
     *
     * Condition nodes route via the edge whose `sourceHandle` equals the
     * chosen branch ('true' / 'false'). For backward compatibility a matched
     * (true) branch may fall back to a single unlabeled edge, while an
     * unmatched (false) branch with no explicit edge stops the flow.
     */
    private function resolveNextNodeId(\Illuminate\Support\Collection $edges, array $node, ?array $result): ?string
    {
        $outgoing = $edges->where('source', $node['id'])->values();

        if ($outgoing->isEmpty()) {
            return null;
        }

        if (($node['type'] ?? null) === 'condition' && $result !== null) {
            $branch = $result['branch'] ?? (($result['output']['matched'] ?? false) ? 'true' : 'false');

            $branchEdge = $outgoing->first(fn ($e) => ($e['sourceHandle'] ?? null) === $branch);

            if ($branchEdge) {
                return $branchEdge['target'] ?? null;
            }

            if ($branch === 'true') {
                $unlabeled = $outgoing->first(fn ($e) => empty($e['sourceHandle']));

                return $unlabeled['target'] ?? null;
            }

            return null;
        }

        return $outgoing->first()['target'] ?? null;
    }

    private function getExecutors(): array
    {
        return [
            'trigger' => app(TriggerNodeExecutor::class),
            'condition' => app(ConditionNodeExecutor::class),
            'api' => app(ApiNodeExecutor::class),
            'ai' => app(AiNodeExecutor::class),
            'send_message' => app(SendMessageNodeExecutor::class),
        ];
    }
}
