<?php

namespace App\Services\Workflow;

class WorkflowValidator
{
    public function validate(?array $definition): array
    {
        $errors = [];

        if (! $definition || empty($definition['nodes'])) {
            $errors[] = 'Workflow must have at least one node';

            return $errors;
        }

        $nodes = collect($definition['nodes']);
        $edges = collect($definition['edges'] ?? []);

        $triggerNodes = $nodes->where('type', 'trigger');

        if ($triggerNodes->isEmpty()) {
            $errors[] = 'Workflow must have a trigger node';
        }

        if ($triggerNodes->count() > 1) {
            $errors[] = 'Workflow can only have one trigger node';
        }

        $nodeIds = $nodes->pluck('id')->all();
        $connected = [];

        foreach ($edges as $edge) {
            $connected[$edge['source'] ?? ''] = true;
            $connected[$edge['target'] ?? ''] = true;

            if ($edge['source'] === $edge['target']) {
                $errors[] = 'Circular loop detected';
            }
        }

        foreach ($nodes as $node) {
            if ($node['type'] !== 'trigger' && empty($connected[$node['id']])) {
                $errors[] = "Node {$node['id']} is disconnected";
            }
        }

        if ($this->hasCycle($edges->all(), $nodeIds)) {
            $errors[] = 'Workflow contains circular dependencies';
        }

        return $errors;
    }

    private function hasCycle(array $edges, array $nodeIds): bool
    {
        $graph = array_fill_keys($nodeIds, []);
        foreach ($edges as $edge) {
            $graph[$edge['source']][] = $edge['target'];
        }

        $visited = [];
        $stack = [];

        foreach ($nodeIds as $nodeId) {
            if ($this->dfsCycle($nodeId, $graph, $visited, $stack)) {
                return true;
            }
        }

        return false;
    }

    private function dfsCycle(string $node, array $graph, array &$visited, array &$stack): bool
    {
        if (isset($stack[$node])) {
            return true;
        }
        if (isset($visited[$node])) {
            return false;
        }

        $visited[$node] = true;
        $stack[$node] = true;

        foreach ($graph[$node] ?? [] as $neighbor) {
            if ($this->dfsCycle($neighbor, $graph, $visited, $stack)) {
                return true;
            }
        }

        unset($stack[$node]);

        return false;
    }

    public function getLinearNodeOrder(array $definition): array
    {
        $nodes = collect($definition['nodes'])->keyBy('id');
        $edges = collect($definition['edges'] ?? []);
        $trigger = $nodes->firstWhere('type', 'trigger');

        if (! $trigger) {
            return [];
        }

        $order = [];
        $currentId = $trigger['id'];
        $visited = [];

        while ($currentId && ! isset($visited[$currentId])) {
            $visited[$currentId] = true;
            $node = $nodes->get($currentId);
            if ($node) {
                $order[] = $node;
            }

            $nextEdge = $edges->firstWhere('source', $currentId);
            $currentId = $nextEdge['target'] ?? null;
        }

        return $order;
    }
}
