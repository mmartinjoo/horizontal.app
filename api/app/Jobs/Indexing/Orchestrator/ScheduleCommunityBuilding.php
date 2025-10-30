<?php

namespace App\Jobs\Indexing\Orchestrator;

use App\Enums\Indexing\WorkflowStatus;
use App\Jobs\Indexing\KnowledgeGraph\BuildCommunities;
use App\Jobs\Indexing\Supervisor\SuperviseWorkflow;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowStep;
use App\Services\Indexing\Orchestrator\Supervisor\WorkflowSupervisor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScheduleCommunityBuilding implements ShouldQueue
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
        if (!$this->hasBuildingRelatedNodesFinished($workflow)) {
            dispatch(new ScheduleCommunityBuilding($this->workflowId))
                ->delay(30);

            return;
        }

        if ($workflow->status !== WorkflowStatus::ReadForNextStep->value) {
            dispatch(new ScheduleCommunityBuilding($this->workflowId))
                ->delay(10);

            return;
        }
        
        $step = IndexingWorkflowStep::create([
            'indexing_workflow_id' => $this->workflowId,
            'name' => 'build_communities',
            'status' => WorkflowStatus::Starting,
            'service' => 'api',
            'job_id' => $this->job->payload()['uuid'],
        ]);

        $job = new BuildCommunities();
        $job->setIndexingWorkflowId($workflow->id);
        $job->setIndexingWorkflowStepId($step->id);
        dispatch($job);

        $supervisor = new SuperviseWorkflow(
            $workflow->id,
            new WorkflowSupervisor(),
        );
        dispatch($supervisor);
    }

    private function hasBuildingRelatedNodesFinished(IndexingWorkflow $workflow): bool
    {
        foreach ($workflow->steps as $step) {
            if ($step->name === 'build_related_nodes' && $step->status === WorkflowStatus::Completed->value) {
                return true;
            }
        }
        return false;
    }
}