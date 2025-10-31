<?php

namespace App\Services\Indexing\Orchestrator;

use App\Enums\Indexing\WorkflowStatus;
use App\Jobs\Indexing\CodeRepository\GitHub\IndexGitHub;
use App\Jobs\Indexing\Communication\GoogleChat\IndexGoogleChat;
use App\Jobs\Indexing\Communication\Slack\IndexSlack;
use App\Jobs\Indexing\IndexingStepJob;
use App\Jobs\Indexing\Orchestrator\ScheduleAdditionalNodeBuilding;
use App\Jobs\Indexing\Orchestrator\ScheduleCommunityBuilding;
use App\Jobs\Indexing\Orchestrator\ScheduleGraphBuilding;
use App\Jobs\Indexing\Storage\GoogleDrive\IndexGoogleDrive;
use App\Jobs\Indexing\Supervisor\SuperviseStuckBuckets;
use App\Jobs\Indexing\Supervisor\SuperviseWorkflow;
use App\Jobs\Indexing\TaskManagement\Jira\IndexJira;
use App\Jobs\Indexing\TaskManagement\Linear\IndexLinear;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowStep;
use App\Services\Indexing\Orchestrator\Supervisor\StuckBucketSupervisor;
use App\Services\Indexing\Orchestrator\Supervisor\WorkflowSupervisor;
use Exception;

class Orchestrator
{
    public function schedule()
    {
        $workflow = IndexingWorkflow::create([
            'started_at' => now(),
            'status' => WorkflowStatus::Starting->value,
        ]);

        // This will be merged into one `integrations` table
        $integrations = ['google_drive', 'linear', 'slack', 'github'];
        $jobs = [];

        foreach ($integrations as $integration) {
            $job = $this->createIndexingJob($integration);
            $workflowStep = IndexingWorkflowStep::create([
                'indexing_workflow_id' => $workflow->id,
                'name' => "index_{$integration}",
                'status' => WorkflowStatus::Starting->value,
                'service' => 'api',     // there are jobs in the graphbuilder service that need to be supervised as well
            ]);

            // jobs store IDs to avoid state and serialisation issues
            $job->setIndexingWorkflowId($workflow->id);
            $job->setIndexingWorkflowStepId($workflowStep->id);

            $jobs[] = $job;
        }
    
        foreach ($jobs as $job) {
            dispatch($job);
        }

        $workflowSupervisor = $this->createWorkflowSupervisor($workflow);
        dispatch($workflowSupervisor);

        $stuckBucketSupervisor = $this->createStuckBucketSupervisor($workflow);
        dispatch($stuckBucketSupervisor);

        dispatch(new ScheduleGraphBuilding($workflow->id));
        dispatch(new ScheduleAdditionalNodeBuilding($workflow->id));
        dispatch(new ScheduleCommunityBuilding($workflow->id));
    }

    private function createIndexingJob(string $integration): IndexingStepJob
    {
        return match ($integration) {
            'google_drive' => new IndexGoogleDrive(),
            'github' => new IndexGitHub(),
            'slack' => new IndexSlack(),
            'linear' => new IndexLinear(),
            'google_chat' => new IndexGoogleChat(),
            'jira' => new IndexJira(),
            default => throw new Exception('unknown integration'),
        };
    }

    private function createWorkflowSupervisor(IndexingWorkflow $workflow): SuperviseWorkflow
    {
        return new SuperviseWorkflow(
            $workflow->id,
            new WorkflowSupervisor(),
        );
    }

    private function createStuckBucketSupervisor(IndexingWorkflow $workflow): SuperviseStuckBuckets
    {
        return new SuperviseStuckBuckets(
            $workflow->id,
            new StuckBucketSupervisor(),
        );
    }
}
