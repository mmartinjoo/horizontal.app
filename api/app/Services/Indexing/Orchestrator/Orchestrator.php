<?php

namespace App\Services\Indexing\Orchestrator;

use App\Enums\Indexing\WorkflowStatus;
use App\Jobs\Indexing\IndexingStepJob;
use App\Jobs\Indexing\Orchestrator\ScheduleAdditionalNodeBuilding;
use App\Jobs\Indexing\Orchestrator\ScheduleCommunityBuilding;
use App\Jobs\Indexing\Orchestrator\ScheduleGraphBuilding;
use App\Jobs\Indexing\Supervisor\SuperviseStuckBuckets;
use App\Jobs\Indexing\Supervisor\SuperviseStuckItems;
use App\Jobs\Indexing\Supervisor\SuperviseWorkflow;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowStep;
use App\Services\GraphDB\GraphDB;
use App\Services\GraphDB\GraphDBFactory;
use App\Services\Indexing\Orchestrator\Supervisor\StuckBucketSupervisor;
use App\Services\Indexing\Orchestrator\Supervisor\StuckItemSupervisor;
use App\Services\Indexing\Orchestrator\Supervisor\WorkflowSupervisor;
use App\Services\Integration\ProviderService;
use Exception;
use Throwable;

class Orchestrator
{
    private GraphDB $graphDB;
    public function __construct()
    {
    }

    public function schedule()
    {
        $graphDBFactory = app(GraphDBFactory::class);
        $this->graphDB = $graphDBFactory->create();
        $workflow = IndexingWorkflow::create([
            'started_at' => now(),
            'status' => WorkflowStatus::Starting->value,
        ]);

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
        $config = config("features.integrations.{$integration}");
        if (!$config['active']) {
            throw new Exception('Orchestrator: integration is inactive: '.$integration);
        }

        $indexingJobClassName = $config['indexing_job_class_name'];
        if (!$indexingJobClassName) {
            throw new Exception('Orchestrator: unknown integration: '.$integration);
        }

        try {
            $indexingJob = new $indexingJobClassName;
            if (!$indexingJob) {
                throw new Exception('index job is null');
            }
            return $indexingJob;
        } catch (Throwable $ex) {
            throw new Exception(
                message: 'Orchestrator: indexing job cannot be created for '.$integration, 
                code: 0, 
                previous: $ex,
            );
        }
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
        $providerService = app(ProviderService::class);
        $activeProviders = $providerService->getActiveProviders();

        foreach ($activeProviders as $config) {
            $integrationModelClassName = $config['integration_model_class_name'];
            $count = $integrationModelClassName::count();

            if ($count > 0) {
                $integrations[] = $config['slug'];
            }
        }

        return $integrations;
    }
}
