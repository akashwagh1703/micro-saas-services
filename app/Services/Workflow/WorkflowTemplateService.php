<?php

namespace App\Services\Workflow;

use App\Models\Workflow;
use App\Support\WorkflowTemplates;

class WorkflowTemplateService
{
    public function listTemplates(): array
    {
        return array_map(fn ($t) => [
            'slug' => $t['slug'],
            'name' => $t['name'],
            'description' => $t['description'],
            'category' => $t['category'],
            'node_types' => $this->extractNodeTypes($t['definition']),
            'node_count' => count($t['definition']['nodes'] ?? []),
        ], WorkflowTemplates::all());
    }

    public function cloneForUser(int $userId, string $slug): ?Workflow
    {
        $template = WorkflowTemplates::find($slug);

        if (! $template) {
            return null;
        }

        if (Workflow::where('user_id', $userId)->where('source_template', $slug)->exists()) {
            return Workflow::where('user_id', $userId)->where('source_template', $slug)->first();
        }

        return Workflow::create([
            'user_id' => $userId,
            'name' => $template['name'],
            'description' => $template['description'],
            'status' => 'draft',
            'is_active' => false,
            'trigger_type' => $template['trigger_type'],
            'definition' => $template['definition'],
            'source_template' => $slug,
        ]);
    }

    public function seedAllForUser(int $userId): array
    {
        $created = [];

        foreach (WorkflowTemplates::all() as $template) {
            if (! Workflow::where('user_id', $userId)->where('source_template', $template['slug'])->exists()) {
                $created[] = $this->cloneForUser($userId, $template['slug']);
            }
        }

        return array_filter($created);
    }

    private function extractNodeTypes(array $definition): array
    {
        return array_values(array_unique(array_column($definition['nodes'] ?? [], 'type')));
    }
}
