<?php

namespace App\Jobs\Indexing\CodeRepository\GitHub;

use App\Jobs\Indexing\CodeRepository\IndexIssue;
use App\Jobs\Indexing\CodeRepository\IndexPullRequest;
use App\Models\Document;
use App\Models\IndexingWorkflow;
use App\Services\Integration\CodeRepository\DataTransferObjects\Issue;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use App\Services\Integration\CodeRepository\DataTransferObjects\PullRequest;
use App\Services\Integration\CodeRepository\GitHub\GitHub;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\LazyCollection;

class IndexGitHub implements ShouldQueue
{
    use Queueable;

    public function handle(
        GitHub $github,
    ): void {
        /** @var IndexingWorkflow $indexing */
        $indexing = IndexingWorkflow::create([
            'integration' => 'github',
            'status' => 'syncing',
            'job_id' => $this->job->payload()['uuid'],
        ]);

        /** @var LazyCollection<Repository> $repositories */
        $repositories = $github->repositories();

        /** @var Repository $repo */
        foreach ($repositories as $repo) {
            $pullRequests = $github->pullRequests($repo);
            $issues = $github->issues($repo);

            $indexing->increment('overall_items', count($pullRequests)+count($issues));

            /** @var PullRequest $pullRequest */
            foreach ($pullRequests as $pullRequest) {
                if (!$this->pullRequestNeedsIndexing($pullRequest)) {
                    $indexing->increment('skipped_items', 1);
                    continue;
                }

                // Delete existing document if it exists
                $count = Document::query()
                    ->where('source_type', 'github_pr')
                    ->where('source_id', $pullRequest->id)
                    ->delete();

                $indexing->increment('deleted_items', $count);
                IndexPullRequest::dispatch($pullRequest, $github, $indexing->id);
            }

            /** @var Issue $issue */
            foreach ($issues as $issue) {
                if (!$this->issueNeedsIndexing($issue)) {
                    $indexing->increment('skipped_items', 1);
                    continue;
                }

                // Delete existing document if it exists
                $count = Document::query()
                    ->where('source_type', 'github_issue')
                    ->where('source_id', $issue->externalId)
                    ->delete();

                $indexing->increment('deleted_items', $count);
                IndexIssue::dispatch($issue, $indexing->id);
            }
        }

        $indexing->update(['status' => 'completed']);
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

    private function issueNeedsIndexing(Issue $issue): bool
    {
        $existingContent = Document::query()
            ->where('source_id', $issue->externalId)
            ->where('source_type', 'github_pr')
            ->first();

        if ($existingContent === null) {
            return true;
        }

        return $issue->createdAt->gt(
            $existingContent->indexed_at ?? Carbon::parse('1900-01-01 00:00:00')
        );
    }
}
