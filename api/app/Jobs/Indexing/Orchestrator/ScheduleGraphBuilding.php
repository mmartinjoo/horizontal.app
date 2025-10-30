<?php

namespace App\Jobs\Indexing\Orchestrator;

use App\Enums\Indexing\WorkflowStatus;
use App\Jobs\Indexing\KnowledgeGraph\BuildKnowledgeGraph;
use App\Jobs\Indexing\Supervisor\SuperviseWorkflow;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowStep;
use App\Services\Indexing\Orchestrator\Supervisor\WorkflowSupervisor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScheduleGraphBuilding implements ShouldQueue
{
    use Queueable;

    public function __construct(private int $workflowId)
    {
    }

    public function handle()
    {
        $workflow = IndexingWorkflow::findOrFail($this->workflowId);
        if ($workflow->status === WorkflowStatus::Failed->value || $workflow->status === WorkflowStatus::Timeout) {
            return;
        }
        if ($workflow->status !== WorkflowStatus::ReadForNextStep->value) {
            dispatch(new ScheduleGraphBuilding($this->workflowId))
                ->delay(10);

            return;
        }
        
        $step = IndexingWorkflowStep::create([
            'indexing_workflow_id' => $this->workflowId,
            'name' => 'build_graph',
            'status' => WorkflowStatus::Starting,
            'service' => 'api',
        ]);

        $job = new BuildKnowledgeGraph();
        $job->setIndexingWorkflowId($workflow->id);
        $job->setIndexingWorkflowStepId($step->id);
        dispatch($job);

        $supervisor = new SuperviseWorkflow(
            $workflow->id,
            new WorkflowSupervisor(),
        );
        dispatch($supervisor);
    }
}