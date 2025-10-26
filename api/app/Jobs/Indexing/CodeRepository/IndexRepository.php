<?php

namespace App\Jobs\Indexing\CodeRepository;

use App\Enums\Indexing\WorkflowStepStatus;
use App\Models\Document;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\CodeRepository\CodeRepository;
use App\Services\Integration\CodeRepository\DataTransferObjects\Issue;
use App\Services\Integration\CodeRepository\DataTransferObjects\PullRequest;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexRepository implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Repository $repository,
        private CodeRepository $adapter,
        private int $indexingWorkflowStepBucketId,
    ) {}

    public function handle()
    {
        $bucket = IndexingWorkflowStepBucket::findOrFail($this->indexingWorkflowStepBucketId);

        $pullRequests = $this->adapter->pullRequests($this->repository);
        $issues = $this->adapter->issues($this->repository);

        $bucket->increment(
            'overall_items', 
            count($pullRequests)+count($issues),
        );

        if (count($pullRequests)+count($issues) === 0) {
            $bucket->update([
                'status' => WorkflowStepStatus::Completed->value,
                'finished_at' => now(),
            ]);
            return;
        }

        /** @var PullRequest $pullRequest */
        foreach ($pullRequests as $pullRequest) {
            if (!$this->pullRequestNeedsIndexing($pullRequest)) {
                $bucket->increment('processed_items', 1);
                $bucket->increment('skipped_items', 1);
                continue;
            }

            $job = new IndexPullRequest($pullRequest, $this->adapter);
            $job->setIndexingWorkflowStepBucketId($this->indexingWorkflowStepBucketId);
            dispatch($job);
        }

        /** @var Issue $issue */
        foreach ($issues as $issue) {
            if (!$this->issueNeedsIndexing($issue)) {
                $bucket->increment('skipped_items', 1);
                $bucket->increment('processed_items', 1);
                continue;
            }

            // Delete existing document if it exists
            $count = Document::query()
                ->where('source_type', 'github_issue')
                ->where('source_id', $issue->externalId)
                ->delete();

            $bucket->increment('deleted_items', $count);

            $job = new IndexIssue($issue);
            $job->setIndexingWorkflowStepBucketId($this->indexingWorkflowStepBucketId);
            dispatch($job);
        }
    }

    private function pullRequestNeedsIndexing(PullRequest $pullRequest): bool
    {
        $existingContent = Document::query()
            ->where('source_id', $pullRequest->id)
            ->where('source_type', 'github_pr')
            ->first();

        if ($existingContent === null) {
            return true;
        }

        return $pullRequest->updatedAt->gt(
            $existingContent->indexed_at ?? Carbon::parse('1900-01-01 00:00:00')
        );
    }

    /**
     * No need to keep issues updated. We just index the title and body.
     */
    private function issueNeedsIndexing(Issue $issue): bool
    {
        return ! Document::query()
            ->where('source_id', $issue->externalId)
            ->where('source_type', 'github_issue')
            ->exists();
    }
}