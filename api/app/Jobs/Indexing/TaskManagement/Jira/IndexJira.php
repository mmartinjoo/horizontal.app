<?php

namespace App\Jobs\Indexing\TaskManagement\Jira;

use App\Jobs\Indexing\IndexingStepJob;
use App\Jobs\Indexing\TaskManagement\IndexProject;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepBucket;
use App\Models\JiraProject;
use App\Services\Integration\TaskManagement\DataTransferObjects\Project;
use App\Services\Integration\TaskManagement\Jira\Jira;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class IndexJira extends IndexingStepJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('indexing');
    }

    public function handle(
        Jira $jira,
    ): void {
        /** @var IndexingWorkflowStep $indexingWorkflowStep */
        $indexingWorkflowStep = IndexingWorkflowStep::findOrFail($this->indexingWorkflowStepId);
        $indexingWorkflowStep->update([
            'started_at' => now(),
            'job_id' => $this->job->payload()['uuid'],
        ]);

        $allProjects = $jira->projects();
        $projects = $this->getIndexableResources($allProjects);

        /** @var Project $project */
        foreach ($projects as $project) {
            $bucket = IndexingWorkflowStepBucket::create([
                'indexing_workflow_step_id' => $indexingWorkflowStep->id,
                'title' => "project_" . Str::lower($project->title),
                'status' => 'starting',
            ]);

            $job = new IndexProject(
                project: $project,
                vendor: 'jira',
                indexingWorkflowStepBucketId: $bucket->id,
            );
            dispatch($job);
        }
    }

    /**
     * @return LazyCollection<JiraProject>
     */
    public function getAuthorizedResources(): LazyCollection
    {
        return JiraProject::all()->lazy();
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
                if ($authorizedResources->contains('jira_id', $resource->id)) {
                    yield $resource;
                }
            }
        });
    }
}
