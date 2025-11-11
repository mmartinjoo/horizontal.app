<?php

namespace App\Jobs\Indexing\CodeRepository\GitHub;

use App\Jobs\Indexing\CodeRepository\IndexRepository;
use App\Jobs\Indexing\IndexingStepJob;
use App\Models\GithubRepository;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use App\Services\Integration\CodeRepository\GitHub\GitHub;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class IndexGitHub extends IndexingStepJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('indexing');
    }

    public function handle(
        GitHub $github,
    ): void {
        /** @var IndexingWorkflowStep $indexingWorkflowStep */
        $indexingWorkflowStep = IndexingWorkflowStep::findOrFail($this->indexingWorkflowStepId);
        $indexingWorkflowStep->update([
            'started_at' => now(),
            'job_id' => $this->job->payload()['uuid'],
        ]);

        /** @var LazyCollection<Repository> $repositories */
        $allRepositories = $github->repositories();
        $repositories = $this->getIndexableResources($allRepositories);

        /** @var Repository $repo */
        foreach ($repositories as $repo) {
            $bucket = IndexingWorkflowStepBucket::create([
                'indexing_workflow_step_id' => $indexingWorkflowStep->id,
                'title' => "repository_" . Str::lower($repo->name),
                'status' => 'starting',
            ]);
            $job = new IndexRepository(
                repository: $repo,
                vendor: 'github',
                indexingWorkflowStepBucketId: $bucket->id,
            );
            dispatch($job);
        }
    }

    /**
     * @return LazyCollection<GithubRepository>
     */
    public function getAuthorizedResources(): LazyCollection
    {
        return GithubRepository::all()->lazy();
    }

    /**
     * @param LazyCollection<Repository> $resources
     * @return LazyCollection<Repository>
     */
    public function getIndexableResources(LazyCollection $resources): LazyCollection
    {
        $authorizedResources = $this->getAuthorizedResources();
        return LazyCollection::make(function () use ($resources, $authorizedResources) {
            /** @var Repository $resource */
            foreach ($resources as $resource) {
                if ($authorizedResources->contains('external_id', $resource->externalId)) {
                    yield $resource;
                }
            }
        });
    }
}
