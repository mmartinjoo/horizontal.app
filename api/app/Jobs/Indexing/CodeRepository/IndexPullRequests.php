<?php

namespace App\Jobs\Indexing\CodeRepository;

use App\Models\Document;
use App\Models\IndexingWorkflowStep;
use App\Services\Integration\CodeRepository\CodeRepository;
use App\Services\Integration\CodeRepository\DataTransferObjects\PullRequest;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexPullRequests implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Repository $repository,
        private CodeRepository $connector,
        private int $indexingWorkflowStepId,
    ) {}

    public function handle()
    {
        $indexingWorkflowStep = IndexingWorkflowStep::findOrFail($this->indexingWorkflowStepId);
        $pullRequests = $this->connector->pullRequests($this->repository);

        $indexingWorkflowStep->increment('overall_items', count($pullRequests));

        /** @var PullRequest $pullRequest */
        foreach ($pullRequests as $i => $pullRequest) {
            if (! $this->pullRequestNeedsIndexing($pullRequest)) {
                $indexingWorkflowStep->increment('skipped_items', 1);

                continue;
            }

            // Delete existing document if it exists
            $count = Document::query()
                ->where('source_type', 'github_pr')
                ->where('source_id', $pullRequest->id)
                ->delete();

            $indexingWorkflowStep->increment('deleted_items', $count);

            IndexPullRequest::dispatch($pullRequest, $this->connector, $indexingWorkflowStep->id);
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
}
