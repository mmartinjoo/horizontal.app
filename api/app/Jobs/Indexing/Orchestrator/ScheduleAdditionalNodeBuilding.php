<?php

namespace App\Jobs\Indexing\Orchestrator;

use App\Enums\Indexing\WorkflowStatus;
use App\Jobs\Indexing\KnowledgeGraph\BuildRelatedNodes;
use App\Jobs\Indexing\Supervisor\SuperviseWorkflow;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowStep;
use App\Services\Indexing\Orchestrator\Supervisor\WorkflowSupervisor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScheduleAdditionalNodeBuilding implements ShouldQueue
{
    use Queueable;

    public function __construct(private int $workflowId)
    {
    }

    public function handle()
    {
        $workflow = IndexingWorkflow::findOrFail($this->workflowId);
        if ($workflow->status === WorkflowStatus::Failed->value || $workflow->status === WorkflowStatus::Timeout->value) {
            return;
        }
        if (!$this->hasGraphBuildingFinished($workflow)) {
            dispatch(new ScheduleAdditionalNodeBuilding($this->workflowId))
                ->delay(30);

            return;
        }

        if ($workflow->status !== WorkflowStatus::ReadForNextStep->value) {
            dispatch(new ScheduleAdditionalNodeBuilding($this->workflowId))
                ->delay(10);

            return;
        }

        $exists = IndexingWorkflowStep::query()
            ->where('name', 'build_related_nodes')
            ->exists();

        if ($exists) {
            return;
        }
        
        $step = IndexingWorkflowStep::create([
            'indexing_workflow_id' => $this->workflowId,
            'name' => 'build_related_nodes',
            'status' => WorkflowStatus::Starting,
            'service' => 'api',
            'job_id' => $this->job->payload()['uuid'],
        ]);

        $job = new BuildRelatedNodes();
        $job->setIndexingWorkflowId($workflow->id);
        $job->setIndexingWorkflowStepId($step->id);
        dispatch($job);

        $supervisor = new SuperviseWorkflow(
            $workflow->id,
            new WorkflowSupervisor(),
        );
        dispatch($supervisor);
    }

    private function hasGraphBuildingFinished(IndexingWorkflow $workflow): bool
    {
        foreach ($workflow->steps as $step) {
            if ($step->name === 'build_graph' && ($step->status === WorkflowStatus::Completed->value || $step->status === WorkflowStatus::CompletedWithErrors->value)) {
                return true;
            }
        }
        return false;
    }
}