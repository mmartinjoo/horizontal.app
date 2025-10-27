<?php

namespace App\Jobs\Indexing\TaskManagement;

use App\Enums\Indexing\WorkflowStepStatus;
use App\Models\Document;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\TaskManagement\DataTransferObjects\Issue;
use App\Services\Integration\TaskManagement\DataTransferObjects\Project;

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
        
        foreach ($issues as $i => $issue) {
            if (!$this->issueNeedsIndexing($issue)) {                
                continue;
            }

            $bucket->increment('overall_items', 1);

            $job = new IndexIssue($issue, $this->adapter);
            $job->setIndexingWorkflowStepBucketId($this->indexingWorkflowStepBucketId);
            $jobs[] = $job;
        }

        if (empty($jobs)) {
            $bucket->update([
                'status' => WorkflowStepStatus::Completed->value,
                'finished_at' => now(),
            ]);
        }

        foreach ($jobs as $job) {
            dispatch($job);
        }
    }

    private function issueNeedsIndexing(Issue $issue): bool
    {
        $existingContent = Document::query()
            ->where('source_id', $issue->id)
            ->where('source_type', 'linear')
            ->first();

        if ($existingContent === null) {
            return true;
        }

        return $issue->getLastUpdatedAt()->gt($existingContent->indexed_at ?? now()->subYears(100));
    }
}