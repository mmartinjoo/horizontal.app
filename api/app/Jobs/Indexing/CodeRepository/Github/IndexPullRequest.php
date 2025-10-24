<?php

namespace App\Jobs\Indexing\CodeRepository\GitHub;

use App\Models\Document;
use App\Models\IndexingWorkflowItem;
use App\Services\Integration\CodeRepository\DataTransferObjects\PullRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexPullRequest implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Document $document,
        private PullRequest $pullRequest,
        private int $indexingWorkflowItemId
    ) {}

    public function handle(): void
    {
        $indexingItem = IndexingWorkflowItem::find($this->indexingWorkflowItemId);

        if (!$indexingItem) {
            return;
        }

        $jobIds = $indexingItem->job_ids ?? [];
        $jobIds[] = $this->job->payload()['uuid'];

        $indexingItem->update([
            'status' => 'processing',
            'job_ids' => $jobIds,
        ]);

        try {
            

            $indexingItem->update([
                'status' => 'completed',
            ]);

            $this->updateWorkflowStatus($indexingItem);
        } catch (\Exception $e) {
            $indexingItem->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function updateWorkflowStatus(IndexingWorkflowItem $indexingItem): void
    {
        $workflow = $indexingItem->indexing_workflow;

        if (!$workflow) {
            return;
        }

        $hasQueuedItems = $workflow->items()
            ->whereIn('status', ['queued', 'processing'])
            ->exists();

        if (!$hasQueuedItems) {
            $workflow->update([
                'status' => 'completed',
            ]);
        }
    }
}
