<?php

namespace App\Jobs\Indexing\TaskManagement;

use App\Enums\Indexing\WorkflowStatus;
use App\Models\Document;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\Factory;
use App\Services\Integration\TaskManagement\DataTransferObjects\Issue;
use App\Services\Integration\TaskManagement\DataTransferObjects\Project;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexProject implements ShouldQueue
{
    use Queueable;
    
    public function __construct(
        private Project $project,
        private string $vendor,
        private int $indexingWorkflowStepBucketId,
    ) {
        $this->onQueue('indexing');
    }

    public function handle(Factory $integrationFactory)
    {
        $taskManagement = $integrationFactory->createTaskManagement($this->vendor);
        $bucket = IndexingWorkflowStepBucket::findOrFail($this->indexingWorkflowStepBucketId);

        $issues = $taskManagement->issues($this->project);
        $jobs = [];

        /** @var Issue $issue */
        foreach ($issues as $issue) {
            if (!$this->issueNeedsIndexing($issue)) {                
                continue;
            }

            Document::query()
                ->where('source', $this->vendor)
                ->where('source_type', 'issue')
                ->where('source_id', $issue->id)
                ->delete();

            $bucket->increment('overall_items');

            $job = new IndexIssue(
                issue: $issue, 
                adapter: $taskManagement,
                vendor: $this->vendor,
            );
            $job->setIndexingWorkflowStepBucketId($this->indexingWorkflowStepBucketId);
            $jobs[] = $job;
        }

        if (empty($jobs)) {
            $bucket->update([
                'status' => WorkflowStatus::Completed->value,
                'finished_at' => now(),
            ]);
            return;
        }

        foreach ($jobs as $job) {
            dispatch($job);
        }
    }

    private function issueNeedsIndexing(Issue $issue): bool
    {
        $existingDocument = Document::query()
            ->where('source', $this->vendor)
            ->where('source_type', 'issue')
            ->where('source_id', $issue->id)
            ->first();

        if ($existingDocument === null) {
            return true;
        }

        $indexingItem = $existingDocument->indexingItem();
        if (!$indexingItem) {
            return true;
        }

        return $issue->getLastUpdatedAt()->gt(
            $indexingItem->created_at ?? Carbon::parse('1900-01-01 00:00:00'),
        );
    }
}