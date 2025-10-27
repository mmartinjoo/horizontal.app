<?php

namespace App\Jobs\Indexing\Storage\GoogleDrive;

use App\Jobs\Indexing\IndexingStepJob;
use App\Jobs\Indexing\Storage\IndexFolder;
use App\Models\Document;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\Storage\DataTransferObjects\File;
use App\Services\Integration\Storage\DataTransferObjects\Folder;
use App\Services\Integration\Storage\GoogleDrive\GoogleDrive;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class IndexGoogleDrive extends IndexingStepJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        GoogleDrive $drive,
    ): void {
        /** @var IndexingWorkflowStep $indexingWorkflowStep */
        $indexingWorkflowStep = IndexingWorkflowStep::findOrFail($this->indexingWorkflowStepId);
        $indexingWorkflowStep->update([
            'started_at' => now(),
            'job_id' => $this->job->payload()['uuid'],
        ]);

        $folders = $drive->folders();      

        /** @var Folder $folder */
        foreach ($folders as $folder) {
            $bucket = IndexingWorkflowStepBucket::create([
                'indexing_workflow_step_id' => $indexingWorkflowStep->id,
                'title' => "folder_" . Str::lower($folder->path),
                'status' => 'starting',
            ]);
            $indexingWorkflowStep->increment('overall_items');

            $job = new IndexFolder(
                folder: $folder,
                vendor: 'google_drive',
                indexingWorkflowStepBucketId: $bucket->id,
            );
            dispatch($job);
        }
    }
}
