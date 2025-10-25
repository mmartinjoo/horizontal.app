<?php

namespace App\Services\Indexing\Orchestrator;

use App\Enums\Indexing\WorkflowStatus;
use App\Enums\Indexing\WorkflowStepStatus;
use App\Jobs\Indexing\CodeRepository\GitHub\IndexGitHub;
use App\Jobs\Indexing\IndexingStepJob;
use App\Jobs\Indexing\Storage\GoogleDrive\IndexGoogleDrive;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowStep;
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
        $integrations = ['github'];
        $jobs = [];
        foreach ($integrations as $integration) {
            $job = $this->createJob($integration);
            $workflowStep = IndexingWorkflowStep::create([
                'indexing_workflow_id' => $workflow->id,
                'name' => "index_{$integration}",
                'status' => WorkflowStepStatus::Starting->value,
                'service' => 'api',
            ]);
            $job->setIndexingWorkflowId($workflow->id);
            $job->setIndexingWorkflowStepId($workflowStep->id);
            $jobs[] = $job;
        }
    
        foreach ($jobs as $job) {
            dispatch($job);
        }

        $workflow->update([
            'status' => WorkflowStatus::Processing,
        ]);
    }

    private function createJob(string $integration): IndexingStepJob
    {
        return match ($integration) {
            'google_drive' => new IndexGoogleDrive,
            'github' => new IndexGitHub,
            default => throw new Exception('unknown integration'),
        };
    }
}
