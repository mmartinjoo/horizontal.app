<?php

namespace App\Jobs\Indexing\Storage\GoogleDrive;

use App\Jobs\Indexing\IndexingIntegration;
use App\Jobs\Indexing\IndexingStepJob;
use App\Jobs\Indexing\Storage\IndexFolder;
use App\Models\GoogleDriveFolder;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\Storage\DataTransferObjects\Folder;
use App\Services\Integration\Storage\GoogleDrive\GoogleDrive;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class IndexGoogleDrive extends IndexingStepJob implements ShouldQueue, IndexingIntegration
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('indexing');
    }

    public function handle(
        GoogleDrive $drive,
    ): void {
        /** @var IndexingWorkflowStep $indexingWorkflowStep */
        $indexingWorkflowStep = IndexingWorkflowStep::findOrFail($this->indexingWorkflowStepId);
        $indexingWorkflowStep->update([
            'started_at' => now(),
            'job_id' => $this->job->payload()['uuid'],
        ]);

        $allFolders = $drive->folders();
        $folders = $this->getIndexableResources($allFolders);

        /** @var Folder $folder */
        foreach ($folders as $folder) {
            $bucket = IndexingWorkflowStepBucket::create([
                'indexing_workflow_step_id' => $indexingWorkflowStep->id,
                'title' => "folder_" . Str::lower($folder->path),
                'status' => 'starting',
            ]);
            $job = new IndexFolder(
                folder: $folder,
                vendor: 'google_drive',
                indexingWorkflowStepBucketId: $bucket->id,
            );
            dispatch($job);
        }
    }

    /**
     * @return LazyCollection<GoogleDriveFolder>
     */
    public function getAuthorizedResources(): LazyCollection
    {
        return GoogleDriveFolder::all()->lazy();
    }

    /**
     * @param LazyCollection<Folder> $resources
     * @return LazyCollection<Folder>
     */
    public function getIndexableResources(LazyCollection $resources): LazyCollection
    {
        $authorizedResources = $this->getAuthorizedResources();
        return LazyCollection::make(function () use ($resources, $authorizedResources) {
            /** @var Folder $resource */
            foreach ($resources as $resource) {
                if ($authorizedResources->contains('external_id', $resource->id)) {
                    yield $resource;
                }
            }
        });
    }
}
