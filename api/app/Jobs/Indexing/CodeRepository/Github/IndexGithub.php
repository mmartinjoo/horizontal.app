<?php

namespace App\Jobs\Indexing\CodeRepository\GitHub;

use App\Jobs\Indexing\CodeRepository\IndexIssues;
use App\Jobs\Indexing\CodeRepository\IndexPullRequests;
use App\Models\IndexingWorkflow;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use App\Services\Integration\CodeRepository\GitHub\GitHub;
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
            IndexPullRequests::dispatch($repo, $github, $indexing->id);
            IndexIssues::dispatch($repo, $github, $indexing->id);
        }
    }
}
