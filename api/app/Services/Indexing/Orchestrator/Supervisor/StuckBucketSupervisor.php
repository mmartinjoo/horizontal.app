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

        $stuckInProcessing = IndexingWorkflowStepBucket::query()
            ->whereIn('indexing_workflow_step_id', $workflow->steps()->pluck('id'))
            ->where('status', WorkflowStatus::Processing->value)
            ->where('started_at', '<=', now()->subSeconds($timeoutSecond))
            ->get();

        $stuckInStarting = IndexingWorkflowStepBucket::query()
            ->whereIn('indexing_workflow_step_id', $workflow->steps()->pluck('id'))
            ->where('status', WorkflowStatus::Starting->value)
            ->where('created_at', '<=', now()->subSeconds(45*60))
            ->get();

        foreach ($stuckInProcessing as $bucket) {
            $bucket->update([
                'status' => WorkflowStatus::Failed->value,
                'processed_items' => $bucket->overall_items,
                'finished_at' => now(),
                'error_message' => 'Bucket was stuck in processing. Set to failed by ' . get_class($this),
            ]);
        }
        foreach ($stuckInStarting as $bucket) {
            $bucket->update([
                'status' => WorkflowStatus::Failed->value,
                'processed_items' => $bucket->overall_items,
                'finished_at' => now(),
                'error_message' => 'Bucket was stuck in starting. Set to failed by ' . get_class($this),
            ]);
        }

        return $stuckInProcessing->merge($stuckInStarting);

        // TODO: stop jobs from items
    }
}