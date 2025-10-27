<?php

namespace App\Jobs\Indexing\CodeRepository;

use App\Enums\Indexing\WorkflowStatus;
use App\Models\Document;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\CodeRepository\DataTransferObjects\Issue;
use App\Services\Integration\CodeRepository\DataTransferObjects\PullRequest;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use App\Services\Integration\Factory;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexRepository implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Repository $repository,
        private string $vendor,
        private int $indexingWorkflowStepBucketId,
    ) {
        $this->onQueue('indexing');
    }

    public function handle(Factory $integrationFactory)
    {
        $codeRepository = $integrationFactory->createCodeRepository($this->vendor);

        $bucket = IndexingWorkflowStepBucket::findOrFail($this->indexingWorkflowStepBucketId);

        $pullRequests = $codeRepository->pullRequests($this->repository);
        $issues = $codeRepository->issues($this->repository);
        $jobs = [];

        /** @var PullRequest $pullRequest */
        foreach ($pullRequests as $pullRequest) {
            if (!$this->pullRequestNeedsIndexing($pullRequest)) {
                continue;
            }

            Document::query()
                ->where('source', $this->vendor)
                ->where('source_type', 'pull_request')
                ->where('source_id', $pullRequest->id)
                ->delete();

            $bucket->increment('overall_items');

            $job = new IndexPullRequest(
                pullRequest: $pullRequest,
                codeRepository: $codeRepository,
                vendor: $this->vendor,
            );
            $job->setIndexingWorkflowStepBucketId($this->indexingWorkflowStepBucketId);
            $jobs[] = $job;
        }

        /** @var Issue $issue */
        foreach ($issues as $issue) {
            if (!$this->issueNeedsIndexing($issue)) {
                continue;
            }

            Document::query()
                ->where('source', $this->vendor)
                ->where('source_type', 'issue')
                ->where('source_id', $issue->externalId)
                ->delete();

            $bucket->increment('overall_items');

            $job = new IndexIssue(
                issue: $issue,
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
        }

        foreach ($jobs as $job) {
            dispatch($job);
        }
    }

    private function pullRequestNeedsIndexing(PullRequest $pullRequest): bool
    {
        /** @var Document $existingDocument */
        $existingDocument = Document::query()
            ->where('source', $this->vendor)
            ->where('source_id', $pullRequest->id)
            ->where('source_type', 'pull_request')
            ->first();

        if ($existingDocument === null) {
            return true;
        }

        $indexingItem = $existingDocument->indexingItem();
        if (!$indexingItem) {
            return true;
        }

        return $pullRequest->updatedAt->gt(
            $indexingItem->created_at ?? Carbon::parse('1900-01-01 00:00:00')
        );
    }

    /**
     * No need to keep issues updated. We just index the title and body.
     */
    private function issueNeedsIndexing(Issue $issue): bool
    {
        return !Document::query()
            ->where('source', $this->vendor)
            ->where('source_id', $issue->externalId)
            ->where('source_type', 'issue')
            ->exists();
    }
}