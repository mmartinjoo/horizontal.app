<?php

namespace App\Jobs\Indexing\TaskManagement\Linear;

use App\Jobs\Indexing\IndexingIntegration;
use App\Jobs\Indexing\IndexingStepJob;
use App\Jobs\Indexing\TaskManagement\IndexProject;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepBucket;
use App\Models\LinearProject;
use App\Services\Integration\TaskManagement\DataTransferObjects\Project;
use App\Services\Integration\TaskManagement\Linear\Linear;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class IndexLinear extends IndexingStepJob implements ShouldQueue, IndexingIntegration
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('indexing');
    }

    public function handle(Linear $linear): void
    {
        /** @var IndexingWorkflowStep $step */
        $step = IndexingWorkflowStep::findOrFail($this->indexingWorkflowStepId);
        $step->update([
            'started_at' => now(),
            'job_id' => $this->job->payload()['uuid'],
        ]);

        $allProjects = $linear->projects();
        $projects = $this->getIndexableResources($allProjects);

        /** @var Project $project */
        foreach ($projects as $project) {
            $bucket = IndexingWorkflowStepBucket::create([
                'indexing_workflow_step_id' => $step->id,
                'title' => "project_" . Str::lower($project->title),
                'status' => 'starting',
            ]);
            $job = new IndexProject(
                project: $project,
                vendor: 'linear',
                indexingWorkflowStepBucketId: $bucket->id,
            );
            dispatch($job);
        }
    }

    /**
     * @return LazyCollection<LinearProject>
     */
    public function getAuthorizedResources(): LazyCollection
    {
        return LinearProject::all()->lazy();
    }

    /**
     * @param LazyCollection<Project> $resources
     * @return LazyCollection<Project>
     */
    public function getIndexableResources(LazyCollection $resources): LazyCollection
    {
        $authorizedResources = $this->getAuthorizedResources();
        return LazyCollection::make(function () use ($resources, $authorizedResources) {
            /** @var Project $resource */
            foreach ($resources as $resource) {
                if ($authorizedResources->contains('external_id', $resource->id)) {
                    yield $resource;
                }
            }
        });
    }
}
