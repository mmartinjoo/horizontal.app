<?php

namespace App\Jobs\Indexing\Storage\GoogleDrive;

use App\Jobs\Indexing\IndexingStepJob;
use App\Jobs\Indexing\Storage\IndexFile;
use App\Models\Document;
use App\Models\IndexingWorkflowStep;
use App\Services\Integration\Storage\DataTransferObjects\File;
use App\Services\Integration\Storage\GoogleDrive\GoogleDrive;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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

        $files = $drive->listDirectoryContents();
        $indexingWorkflowStep->increment('overall_items', count($files));

        foreach ($files as $file) {
            if (!$this->fileNeedsIndexing($file)) {
                $indexingWorkflowStep->increment('processed_items', 1);
                $indexingWorkflowStep->increment('skipped_items', 1);
                continue;
            }

            $count = Document::query()
                ->where('source_type', 'google_drive')
                ->where('source_id', $file->extraMetadata()['id'])
                ->delete();

            $indexingWorkflowStep->increment('deleted_items', $count);
            IndexFile::dispatch($file, $indexingWorkflowStep->id, 'google_drive');
        }
    }

    private function fileNeedsIndexing(File $file): bool
    {
        $existingContent = Document::query()
            ->where('source_id', $file->extraMetadata()['id'])
            ->first();

        if ($existingContent === null) {
            return true;
        }

        return $file->getUpdatedAt()->gt($existingContent->indexed_at ?? '1900-01-01 00:00:00');
    }
}
