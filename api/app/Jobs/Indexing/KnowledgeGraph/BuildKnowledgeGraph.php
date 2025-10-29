<?php

namespace App\Jobs\Indexing\KnowledgeGraph;

use App\Enums\Indexing\WorkflowStatus;
use App\Jobs\Indexing\IndexingStepJob;
use App\Models\IndexingWorkflowStep;
use App\Services\KnowledgeGraph\GraphBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildKnowledgeGraph extends IndexingStepJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('indexing');
    }

    public function handle(GraphBuilder $graphBuilder)
    {
        $workflowStep = IndexingWorkflowStep::findOrFail($this->indexingWorkflowStepId);
        $workflowStep->update([
            'status' => WorkflowStatus::Processing,
            'started_at' => now(),
            'job_id' => $this->job->payload()['uuid'],
        ]);
        $graphBuilder->buildKG($workflowStep);
    }
}
