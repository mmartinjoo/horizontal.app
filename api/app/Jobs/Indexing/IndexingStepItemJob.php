<?php

namespace App\Jobs\Indexing;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

abstract class IndexingStepItemJob implements ShouldQueue
{
    use Queueable;

    protected int $indexingWorkflowStepId;

    public function setIndexingWorkflowStepId(int $indexingWorkflowStepId): void
    {
        $this->indexingWorkflowStepId = $indexingWorkflowStepId;
    }
}