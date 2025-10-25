<?php

namespace App\Jobs\Indexing;

use App\Enums\Indexing\WorkflowStepStatus;
use App\Models\IndexingWorkflowStep;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

abstract class IndexingStepJob implements ShouldQueue
{
    use Queueable;

    protected int $indexingWorkflowId;
    protected int $indexingWorkflowStepId;

    public function setIndexingWorkflowId(int $indexingWorkflowId): void
    {
        $this->indexingWorkflowId = $indexingWorkflowId;
    }

    public function setIndexingWorkflowStepId(int $indexingWorkflowStepId): void
    {
        $this->indexingWorkflowStepId = $indexingWorkflowStepId;
    }

    public function markWorkflowStepAsProcessing(): void
    {
        $workflowStep = IndexingWorkflowStep::findOrFail($this->indexingWorkflowStepId);
        $workflowStep->update([
            'status' => WorkflowStepStatus::Processing->value,
        ]);
    }
}