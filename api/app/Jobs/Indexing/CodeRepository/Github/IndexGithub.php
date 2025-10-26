<?php

namespace App\Jobs\Indexing\CodeRepository\GitHub;

use App\Enums\Indexing\WorkflowStepStatus;
use App\Jobs\Indexing\CodeRepository\IndexIssues;
use App\Jobs\Indexing\CodeRepository\IndexPullRequests;
use App\Jobs\Indexing\IndexingStepJob;
use App\Models\IndexingWorkflowStep;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use App\Services\Integration\CodeRepository\GitHub\GitHub;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\LazyCollection;

class IndexGitHub extends IndexingStepJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        GitHub $github,
    ): void {
        /** @var IndexingWorkflowStep $indexingWorkflowStep */
        $indexingWorkflowStep = IndexingWorkflowStep::findOrFail($this->indexingWorkflowStepId);
        $indexingWorkflowStep->update([
            // 'status' => WorkflowStepStatus::Processing->value,
            'job_id' => $this->job->payload()['uuid'],
        ]);

        /** @var LazyCollection<Repository> $repositories */
        $repositories = $github->repositories();

        /** @var Repository $repo */
        foreach ($repositories as $repo) {
            IndexPullRequests::dispatch($repo, $github, $indexingWorkflowStep->id);
            IndexIssues::dispatch($repo, $github, $indexingWorkflowStep->id);
        }
    }
}
