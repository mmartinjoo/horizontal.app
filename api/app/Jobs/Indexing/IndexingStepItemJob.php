<?php

namespace App\Jobs\Indexing;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

abstract class IndexingStepItemJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('indexing');
    }

    protected int $indexingWorkflowStepBucketId;

    public function setIndexingWorkflowStepBucketId(int $indexingWorkflowStepBucketId): void
    {
        $this->indexingWorkflowStepBucketId = $indexingWorkflowStepBucketId;
    }
}