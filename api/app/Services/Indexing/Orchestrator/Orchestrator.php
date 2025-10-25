<?php

namespace App\Services\Indexing\Orchestrator;

use App\Enums\Indexing\WorkflowStatus;
use App\Enums\Indexing\WorkflowStepStatus;
use App\Jobs\Indexing\CodeRepository\GitHub\IndexGitHub;
use App\Jobs\Indexing\IndexingStepJob;
use App\Jobs\Indexing\Storage\GoogleDrive\IndexGoogleDrive;
use App\Jobs\Indexing\Supervisor\SuperviseWorkflowStep;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowStep;
use App\Services\Indexing\Orchestrator\Supervisor\WorkflowStepSupervisor;
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
        $integrations = ['github', 'google_drive'];
        $jobs = [];

        foreach ($integrations as $integration) {
            $job = $this->createIndexingJob($integration);
            $workflowStep = IndexingWorkflowStep::create([
                'indexing_workflow_id' => $workflow->id,
                'name' => "index_{$integration}",
                'status' => WorkflowStepStatus::Starting->value,
                'service' => 'api',     // there are jobs in the graphbuilder service that need to be supervised as well
            ]);

            // jobs store IDs to avoid state and serialisation issues
            $job->setIndexingWorkflowId($workflow->id);
            $job->setIndexingWorkflowStepId($workflowStep->id);

            // each indexing job gets one supervisor job
            $jobs[] = [
                'indexing_job' => $job,
                'supervisor_job' => $this->createSupervisorJob($workflowStep),
            ];
        }
    
        foreach ($jobs as $jobData) {
            dispatch($jobData['indexing_job']);
            dispatch($jobData['supervisor_job']);
        }

        $workflow->update([
            'status' => WorkflowStatus::Processing,
        ]);
    }

    private function createIndexingJob(string $integration): IndexingStepJob
    {
        return match ($integration) {
            'google_drive' => new IndexGoogleDrive,
            'github' => new IndexGitHub,
            default => throw new Exception('unknown integration'),
        };
    }

    private function createSupervisorJob(IndexingWorkflowStep $workflowStep): SuperviseWorkflowStep
    {
        return new SuperviseWorkflowStep(
            $workflowStep->id,
            new WorkflowStepSupervisor(
                $workflowStep->id
            ),
        );
    }
}
