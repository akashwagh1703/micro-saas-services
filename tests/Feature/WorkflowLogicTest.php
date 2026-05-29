<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Workflow;
use App\Models\WorkflowExecution;
use App\Services\Workflow\Nodes\ConditionNodeExecutor;
use App\Services\Workflow\WorkflowExecutionService;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class WorkflowLogicTest extends TestCase
{
    public function test_condition_node_reports_matched_branch(): void
    {
        $executor = new ConditionNodeExecutor;
        $node = ['data' => ['field' => 'message', 'operator' => 'contains', 'value' => 'hi']];

        $matched = $executor->execute(new WorkflowExecution, $node, ['message' => 'oh hi there']);
        $this->assertTrue($matched['output']['matched']);
        $this->assertSame('true', $matched['branch']);

        $unmatched = $executor->execute(new WorkflowExecution, $node, ['message' => 'goodbye']);
        $this->assertFalse($unmatched['output']['matched']);
        $this->assertSame('false', $unmatched['branch']);
    }

    public function test_branch_routing_follows_source_handle(): void
    {
        $service = app(WorkflowExecutionService::class);
        $resolve = new ReflectionMethod($service, 'resolveNextNodeId');
        $resolve->setAccessible(true);

        $cond = ['id' => 'c1', 'type' => 'condition'];
        $branched = new Collection([
            ['source' => 'c1', 'target' => 'yes-node', 'sourceHandle' => 'true'],
            ['source' => 'c1', 'target' => 'no-node', 'sourceHandle' => 'false'],
        ]);

        $this->assertSame('yes-node', $resolve->invoke($service, $branched, $cond, ['branch' => 'true']));
        $this->assertSame('no-node', $resolve->invoke($service, $branched, $cond, ['branch' => 'false']));
    }

    public function test_legacy_single_edge_condition_is_backward_compatible(): void
    {
        $service = app(WorkflowExecutionService::class);
        $resolve = new ReflectionMethod($service, 'resolveNextNodeId');
        $resolve->setAccessible(true);

        $cond = ['id' => 'c1', 'type' => 'condition'];
        $legacy = new Collection([
            ['source' => 'c1', 'target' => 'next-node'],
        ]);

        // Matched -> follow the single unlabeled edge.
        $this->assertSame('next-node', $resolve->invoke($service, $legacy, $cond, ['branch' => 'true']));
        // Not matched & no explicit "false" edge -> stop the flow.
        $this->assertNull($resolve->invoke($service, $legacy, $cond, ['branch' => 'false']));
    }

    public function test_non_condition_node_follows_first_edge(): void
    {
        $service = app(WorkflowExecutionService::class);
        $resolve = new ReflectionMethod($service, 'resolveNextNodeId');
        $resolve->setAccessible(true);

        $node = ['id' => 'a1', 'type' => 'api'];
        $edges = new Collection([['source' => 'a1', 'target' => 'b1']]);

        $this->assertSame('b1', $resolve->invoke($service, $edges, $node, ['success' => true]));
    }

    public function test_trigger_keyword_filtering(): void
    {
        $job = new ProcessIncomingWhatsAppMessage(0);
        $matches = new ReflectionMethod($job, 'triggerMatches');
        $matches->setAccessible(true);

        $anyWorkflow = new Workflow;
        $anyWorkflow->definition = ['nodes' => [['type' => 'trigger', 'data' => ['keywords' => 'hi, hello', 'match' => 'any']]]];
        $this->assertTrue($matches->invoke($job, $anyWorkflow, 'please say hi'));
        $this->assertFalse($matches->invoke($job, $anyWorkflow, 'unrelated text'));

        $allWorkflow = new Workflow;
        $allWorkflow->definition = ['nodes' => [['type' => 'trigger', 'data' => ['keywords' => 'order, status', 'match' => 'all']]]];
        $this->assertTrue($matches->invoke($job, $allWorkflow, 'what is my order status'));
        $this->assertFalse($matches->invoke($job, $allWorkflow, 'order please'));

        $exactWorkflow = new Workflow;
        $exactWorkflow->definition = ['nodes' => [['type' => 'trigger', 'data' => ['keywords' => 'menu', 'match' => 'exact']]]];
        $this->assertTrue($matches->invoke($job, $exactWorkflow, 'MENU'));
        $this->assertFalse($matches->invoke($job, $exactWorkflow, 'show menu'));

        $noKeywords = new Workflow;
        $noKeywords->definition = ['nodes' => [['type' => 'trigger', 'data' => []]]];
        $this->assertTrue($matches->invoke($job, $noKeywords, 'literally anything'));
    }
}
