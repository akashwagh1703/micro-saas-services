<?php

namespace App\Jobs;

use App\Models\WorkflowExecution;
use App\Services\Workflow\WorkflowExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExecuteWorkflowJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(
        public int $executionId
    ) {}

    public function handle(WorkflowExecutionService $service): void
    {
        $execution = WorkflowExecution::find($this->executionId);

        if (! $execution) {
            return;
        }

        $service->execute($execution);
    }
}
