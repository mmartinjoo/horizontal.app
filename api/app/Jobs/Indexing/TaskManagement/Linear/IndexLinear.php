<?php

namespace App\Jobs\Indexing\TaskManagement\Linear;

use App\Jobs\Indexing\IndexingStepJob;
use App\Jobs\Indexing\TaskManagement\IndexProject;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\TaskManagement\DataTransferObjects\Project;
use App\Services\Integration\TaskManagement\Linear\Linear;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class IndexLinear extends IndexingStepJob implements ShouldQueue
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

        $projects = $linear->projects();

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
}
