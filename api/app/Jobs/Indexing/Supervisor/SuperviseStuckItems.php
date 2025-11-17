<?php

namespace App\Jobs\Indexing\Supervisor;

use App\Enums\Indexing\WorkflowStatus;
use App\Models\IndexingWorkflow;
use App\Services\Indexing\Orchestrator\Supervisor\StuckItemSupervisor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SuperviseStuckItems implements ShouldQueue
{
    use Queueable;

    private int $intervalInSecond = 10;

    public function __construct(
        private int $workflowId,
        private StuckItemSupervisor $supervisor,
    ) {
    }

    public function handle()
    {
        logger()->info("supervising stuck items for workflow #{$this->workflowId}");
        $workflow = IndexingWorkflow::find($this->workflowId);

        if (in_array($workflow->status, [WorkflowStatus::Completed->value, WorkflowStatus::CompletedWithErrors->value, WorkflowStatus::Failed->value, WorkflowStatus::Timeout->value])) {
            logger()->info("workflow already finished: " . $workflow->status);
            return;
        }

        $stuckItems = $this->supervisor->superviseItems($workflow);
        if (!$stuckItems->isEmpty()) {
            logger()->info("{$stuckItems->count()} items were stauck: " . json_encode($stuckItems->pluck('id')));
        }        

        $this->nextTick();
    }

    private function nextTick()
    {
        logger()->info("waiting {$this->intervalInSecond}s...");

        dispatch(new SuperviseStuckItems(
            workflowId: $this->workflowId,
            supervisor: $this->supervisor,
        ))
            ->delay($this->intervalInSecond);
    }
}