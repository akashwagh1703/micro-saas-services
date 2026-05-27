<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Services\ActivityLogger;
use App\Services\Workflow\WorkflowTemplateService;
use App\Services\Workflow\WorkflowValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowValidator $validator,
        private readonly WorkflowTemplateService $templateService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $workflows = Workflow::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json($workflows);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'trigger_type' => ['nullable', 'string'],
            'definition' => ['nullable', 'array'],
        ]);

        $workflow = Workflow::create([
            ...$validated,
            'user_id' => $request->user()->id,
            'status' => 'draft',
            'definition' => $validated['definition'] ?? $this->defaultDefinition(),
        ]);

        ActivityLogger::log($request->user()->id, 'workflow_created', 'Workflow created: '.$workflow->name);

        return response()->json(['workflow' => $workflow], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $workflow = Workflow::where('user_id', $request->user()->id)->findOrFail($id);

        return response()->json(['workflow' => $workflow]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $workflow = Workflow::where('user_id', $request->user()->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'trigger_type' => ['nullable', 'string'],
            'definition' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['definition'])) {
            $errors = $this->validator->validate($validated['definition']);
            if (! empty($errors)) {
                return response()->json(['errors' => $errors], 422);
            }
        }

        $workflow->update($validated);

        return response()->json(['workflow' => $workflow]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $workflow = Workflow::where('user_id', $request->user()->id)->findOrFail($id);
        $workflow->delete();

        return response()->json(['message' => 'Workflow deleted']);
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $workflow = Workflow::where('user_id', $request->user()->id)->findOrFail($id);

        $errors = $this->validator->validate($workflow->definition);
        if (! empty($errors)) {
            return response()->json(['errors' => $errors], 422);
        }

        $workflow->update(['status' => 'published', 'is_active' => true]);

        return response()->json(['workflow' => $workflow]);
    }

    public function unpublish(Request $request, int $id): JsonResponse
    {
        $workflow = Workflow::where('user_id', $request->user()->id)->findOrFail($id);
        $workflow->update(['status' => 'draft', 'is_active' => false]);

        return response()->json(['workflow' => $workflow]);
    }

    public function executions(Request $request, int $id): JsonResponse
    {
        Workflow::where('user_id', $request->user()->id)->findOrFail($id);

        $executions = WorkflowExecution::where('user_id', $request->user()->id)
            ->where('workflow_id', $id)
            ->with('logs')
            ->latest()
            ->paginate(20);

        return response()->json($executions);
    }

    public function validateDefinition(Request $request): JsonResponse
    {
        $validated = $request->validate(['definition' => ['required', 'array']]);
        $errors = $this->validator->validate($validated['definition']);

        return response()->json(['valid' => empty($errors), 'errors' => $errors]);
    }

    public function templates(): JsonResponse
    {
        $userId = request()->user()->id;
        $templates = $this->templateService->listTemplates();

        $imported = Workflow::where('user_id', $userId)
            ->whereNotNull('source_template')
            ->pluck('source_template')
            ->all();

        $templates = array_map(function ($t) use ($imported) {
            $t['imported'] = in_array($t['slug'], $imported, true);

            return $t;
        }, $templates);

        return response()->json(['templates' => $templates]);
    }

    public function cloneTemplate(Request $request, string $slug): JsonResponse
    {
        $existing = Workflow::where('user_id', $request->user()->id)
            ->where('source_template', $slug)
            ->first();

        if ($existing) {
            return response()->json([
                'workflow' => $existing,
                'already_existed' => true,
                'message' => 'Template already in your account',
            ]);
        }

        $workflow = $this->templateService->cloneForUser($request->user()->id, $slug);

        if (! $workflow) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        return response()->json([
            'workflow' => $workflow,
            'already_existed' => false,
            'message' => 'Template added to your workflows',
        ], 201);
    }

    public function seedAllTemplates(Request $request): JsonResponse
    {
        $created = $this->templateService->seedAllForUser($request->user()->id);

        return response()->json([
            'message' => count($created).' workflow(s) added',
            'count' => count($created),
        ]);
    }

    private function defaultDefinition(): array
    {
        return [
            'nodes' => [
                [
                    'id' => 'trigger-1',
                    'type' => 'trigger',
                    'position' => ['x' => 100, 'y' => 100],
                    'data' => ['label' => 'Message Received'],
                ],
            ],
            'edges' => [],
        ];
    }
}
