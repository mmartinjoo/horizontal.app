<?php

namespace App\Services\Indexing\Orchestrator\Supervisor;

use App\Enums\Indexing\WorkflowStatus;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowStepBucket;
use Illuminate\Support\Collection;

class StuckBucketSupervisor
{
    /**
     * @return Collection<IndexingWorkflowStepBucket>
     */
    public function superviseBuckets(IndexingWorkflow $workflow): Collection
    { 
        $timeoutSecond = config('supervisor.stuck_bucket_timeout', 1800);

        $stuckBuckets = IndexingWorkflowStepBucket::query()
            ->whereIn('indexing_workflow_step_id', $workflow->steps()->pluck('id'))
            ->where('status', WorkflowStatus::Processing->value)
            ->where('created_at', '<=', now()->subSeconds($timeoutSecond))
            ->get();

        foreach ($stuckBuckets as $bucket) {
            $bucket->update([
                'status' => WorkflowStatus::Failed->value,
                'processed_items' => $bucket->overall_items,
                'finished_at' => now(),
                'error_message' => 'Bucket was stuck. Set to failed by ' . get_class($this),
            ]);
        }

        return $stuckBuckets;

        // TODO: stop jobs from items
    }
}