<?php

namespace App\Jobs\Indexing;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

abstract class IndexingStepJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('indexing');
    }

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
}