<?php

namespace App\Jobs\Indexing\TaskManagement\Jira;

use App\Jobs\Indexing\IndexingStepJob;
use App\Jobs\Indexing\TaskManagement\IndexProject;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\TaskManagement\DataTransferObjects\Project;
use App\Services\Integration\TaskManagement\Jira\Jira;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class IndexJira extends IndexingStepJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        Jira $jira,
    ): void {
        /** @var IndexingWorkflowStep $indexingWorkflowStep */
        $indexingWorkflowStep = IndexingWorkflowStep::findOrFail($this->indexingWorkflowStepId);
        $indexingWorkflowStep->update([
            'started_at' => now(),
            'job_id' => $this->job->payload()['uuid'],
        ]);

        $projects = $jira->projects();

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
}
