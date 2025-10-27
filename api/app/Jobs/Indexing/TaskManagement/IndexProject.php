<?php

namespace App\Jobs\Indexing\TaskManagement;

use App\Enums\Indexing\WorkflowStepStatus;
use App\Models\Document;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\TaskManagement\DataTransferObjects\Issue;
use App\Services\Integration\TaskManagement\DataTransferObjects\Project;
use Carbon\Carbon;

class IndexProject
{
    public function __construct(
        private Project $project,
        private TaskManagement $adapter,
        private int $indexingWorkflowStepBucketId,
    ) {}

    public function handle()
    {
        $bucket = IndexingWorkflowStepBucket::findOrFail($this->indexingWorkflowStepBucketId);
        $issues = $this->adapter->issues($this->project);
        $jobs = [];

        /** @var Issue $issue */
        foreach ($issues as $issue) {
            if (!$this->issueNeedsIndexing($issue)) {                
                continue;
            }

            Document::query()
                ->where('source_id', $issue->id)
                ->delete();

            $bucket->increment('overall_items');

            $job = new IndexIssue($issue, $this->adapter);
            $job->setIndexingWorkflowStepBucketId($this->indexingWorkflowStepBucketId);
            $jobs[] = $job;
        }

        if (empty($jobs)) {
            $bucket->update([
                'status' => WorkflowStepStatus::Completed->value,
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
            ->where('source_id', $issue->id)
            ->where('source_type', 'linear')
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