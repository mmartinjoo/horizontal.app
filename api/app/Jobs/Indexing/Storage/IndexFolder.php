<?php

namespace App\Jobs\Indexing\Storage;

use App\Enums\Indexing\WorkflowStepStatus;
use App\Models\Document;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\Factory;
use App\Services\Integration\Storage\DataTransferObjects\File;
use App\Services\Integration\Storage\DataTransferObjects\Folder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexFolder implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Folder $folder,
        private string $vendor,
        private int $indexingWorkflowStepBucketId,
    ) {}

    public function handle(Factory $factory)
    {
        $bucket = IndexingWorkflowStepBucket::findOrFail($this->indexingWorkflowStepBucketId);
        
        $integration = $factory->createStorage($this->vendor);
        $files = $integration->listDirectoryContents($this->folder->path);
        $jobs = [];

        foreach ($files as $file) {
            $bucket->increment('overall_items');

            $job = new IndexFile($file, $this->vendor);
            $job->setIndexingWorkflowStepBucketId($this->indexingWorkflowStepBucketId);
            $jobs[] = $job;
        }

        if (empty($jobs)) {
            $bucket->update([
                'status' => WorkflowStepStatus::Completed->value,
                'finished_at' => now(),
            ]);
        }

        foreach ($jobs as $job) {
            dispatch($job);
        }
    }

    private function fileNeedsIndexing(File $file): bool
    {
        $existingContent = Document::query()
            ->where('source_id', $file->extraMetadata()['id'])
            ->where('source_type', $this->vendor)
            ->first();

        if ($existingContent === null) {
            return true;
        }

        return $file->getUpdatedAt()->gt($existingContent->indexed_at ?? '1900-01-01 00:00:00');
    }
}