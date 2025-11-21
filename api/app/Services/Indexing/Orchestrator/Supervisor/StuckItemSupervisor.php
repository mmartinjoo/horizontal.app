<?php

namespace App\Services\Indexing\Orchestrator\Supervisor;

use App\Enums\Indexing\WorkflowStatus;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowStepBucket;
use App\Models\IndexingWorkflowStepItem;
use Illuminate\Support\Collection;

class StuckItemSupervisor
{
    /**
     * @return Collection<IndexingWorkflowStepBucket>
     */
    public function superviseItems(IndexingWorkflow $workflow): Collection
    { 
        $timeoutSecond = config('supervisor.stuck_item_timeout', 900);
        $stuckInProcessing = IndexingWorkflowStepItem::query()
            ->whereIn('indexing_workflow_step_bucket_id', $workflow->buckets->pluck('id'))
            ->where('status', WorkflowStatus::Processing->value)
            ->where('started_at', '<=', now()->subSeconds($timeoutSecond))
            ->get();

        $stuckInStarting = IndexingWorkflowStepItem::query()
            ->whereIn('indexing_workflow_step_bucket_id', $workflow->buckets->pluck('id'))
            ->where('status', WorkflowStatus::Starting->value)
            ->where('created_at', '<=', now()->subSeconds(120*60))
            ->get();

        foreach ($stuckInProcessing as $item) {
            $item->update([
                'status' => WorkflowStatus::Failed->value,
                'finished_at' => now(),
                'error_message' => 'Item was stuck in processing. Set to failed by ' . get_class($this),
            ]);
        }
        foreach ($stuckInStarting as $item) {
            $item->update([
                'status' => WorkflowStatus::Failed->value,
                'finished_at' => now(),
                'error_message' => 'Item was stuck in starting. Set to failed by ' . get_class($this),
            ]);
        }

        return $stuckInProcessing->merge($stuckInStarting);

        // TODO: stop jobs
    }
}