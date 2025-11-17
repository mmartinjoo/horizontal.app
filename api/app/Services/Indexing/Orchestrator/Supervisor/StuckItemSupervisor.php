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
        $stuckItems = IndexingWorkflowStepItem::query()
            ->whereIn('indexing_workflow_step_bucket_id', $workflow->buckets->pluck('id'))
            ->where('status', WorkflowStatus::Processing->value)
            ->where('created_at', '<=', now()->subSeconds($timeoutSecond))
            ->get();

        foreach ($stuckItems as $item) {
            $item->update([
                'status' => WorkflowStatus::Failed->value,
                'error_message' => 'Item was stuck. Set to failed by ' . get_class($this),
            ]);
        }

        return $stuckItems;

        // TODO: stop jobs
    }
}