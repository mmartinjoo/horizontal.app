<?php

namespace App\Jobs\Indexing\Supervisor;

use App\Enums\Indexing\WorkflowStatus;
use App\Models\IndexingWorkflow;
use App\Services\Indexing\Orchestrator\Supervisor\StuckBucketSupervisor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Exception;

class SuperviseStuckBuckets implements ShouldQueue
{
    use Queueable;

    private int $intervalInSecond = 10;

    public function __construct(
        private int $workflowId,
        private StuckBucketSupervisor $supervisor,
    ) {
    }

    public function handle()
    {
        logger()->info("supervising stuck buckets for workflow #{$this->workflowId}");
        $workflow = IndexingWorkflow::find($this->workflowId);

        if (in_array($workflow->status, [WorkflowStatus::Completed->value, WorkflowStatus::CompletedWithErrors->value, WorkflowStatus::Failed->value, WorkflowStatus::Timeout->value])) {
            logger()->info("workflow already finished: " . $workflow->status);
            return;
        }

        $stuckBuckets = $this->supervisor->superviseBuckets($workflow);
        if (!$stuckBuckets->isEmpty()) {
            logger()->info("{$stuckBuckets->count()} buckets were stauck: " . json_encode($stuckBuckets->pluck('id')));
        }        

        $this->nextTick();
    }

    private function nextTick()
    {
        logger()->info("waiting {$this->intervalInSecond}s...");

        dispatch(new SuperviseStuckBuckets(
            workflowId: $this->workflowId,
            supervisor: $this->supervisor,
        ))
            ->delay($this->intervalInSecond);
    }
}