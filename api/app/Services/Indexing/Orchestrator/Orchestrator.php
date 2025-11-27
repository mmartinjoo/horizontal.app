<?php

namespace App\Services\Indexing\Orchestrator;

use App\Enums\Indexing\WorkflowStatus;
use App\Jobs\Indexing\CodeRepository\Github\IndexGithub;
use App\Jobs\Indexing\Communication\GoogleChat\IndexGoogleChat;
use App\Jobs\Indexing\Communication\Slack\IndexSlack;
use App\Jobs\Indexing\IndexingStepJob;
use App\Jobs\Indexing\Orchestrator\ScheduleAdditionalNodeBuilding;
use App\Jobs\Indexing\Orchestrator\ScheduleCommunityBuilding;
use App\Jobs\Indexing\Orchestrator\ScheduleGraphBuilding;
use App\Jobs\Indexing\Storage\GoogleDrive\IndexGoogleDrive;
use App\Jobs\Indexing\Supervisor\SuperviseStuckBuckets;
use App\Jobs\Indexing\Supervisor\SuperviseStuckItems;
use App\Jobs\Indexing\Supervisor\SuperviseWorkflow;
use App\Jobs\Indexing\TaskManagement\Jira\IndexJira;
use App\Jobs\Indexing\TaskManagement\Linear\IndexLinear;
use App\Models\GithubIntegration;
use App\Models\GoogleChatIntegration;
use App\Models\GoogleDriveIntegration;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowStep;
use App\Models\JiraIntegration;
use App\Models\LinearIntegration;
use App\Models\SlackIntegration;
use App\Services\GraphDB\GraphDB;
use App\Services\Indexing\Orchestrator\Supervisor\StuckBucketSupervisor;
use App\Services\Indexing\Orchestrator\Supervisor\StuckItemSupervisor;
use App\Services\Indexing\Orchestrator\Supervisor\WorkflowSupervisor;
use Exception;

class Orchestrator
{
    public function __construct(private GraphDB $graphDB)
    {        
    }

    public function schedule()
    {        
        $workflow = IndexingWorkflow::create([
            'started_at' => now(),
            'status' => WorkflowStatus::Starting->value,
        ]);

        // This will be merged into one `integrations` table
        $integrations = $this->getEnabledIntegrations();
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

        $this->graphDB->query('MATCH (n) DETACH DELETE n');
    
        foreach ($jobs as $job) {
            dispatch($job);
        }

        $workflowSupervisor = $this->createWorkflowSupervisor($workflow);
        dispatch($workflowSupervisor);

        $stuckBucketSupervisor = $this->createStuckBucketSupervisor($workflow);
        dispatch($stuckBucketSupervisor);

        $stuckItemSupervisor = $this->createStuckItemSupervisor($workflow);
        dispatch($stuckItemSupervisor);

        dispatch(new ScheduleGraphBuilding($workflow->id));
        dispatch(new ScheduleAdditionalNodeBuilding($workflow->id));
        dispatch(new ScheduleCommunityBuilding($workflow->id));
    }

    private function createIndexingJob(string $integration): IndexingStepJob
    {
        return match ($integration) {
            'google_drive' => new IndexGoogleDrive(),
            'github' => new IndexGithub(),
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

    private function createStuckItemSupervisor(IndexingWorkflow $workflow): SuperviseStuckItems
    {
        return new SuperviseStuckItems(
            $workflow->id,
            new StuckItemSupervisor(),
        );
    }

    /**
     * @return string[]
     */
    public function getEnabledIntegrations(): array
    {
        $integrations = [];
        if (GithubIntegration::count() > 0) {
            $integrations[] = 'github';
        }
        if (GoogleChatIntegration::count() > 0) {
            $integrations[] = 'google_chat';
        }
        if (GoogleDriveIntegration::count() > 0) {
            $integrations[] = 'google_drive';
        }
        if (JiraIntegration::count() > 0) {
            $integrations[] = 'jira';
        }
        if (LinearIntegration::count() > 0) {
            $integrations[] = 'linear';
        }
        if (SlackIntegration::count() > 0) {
            $integrations[] = 'slack';
        }
        return $integrations;
    }
}
