<?php

namespace App\Jobs\Indexing\Storage\GoogleDrive;

use App\Jobs\Indexing\Storage\IndexFile;
use App\Models\Document;
use App\Models\IndexingWorkflowStep;
use App\Services\Integration\Storage\DataTransferObjects\File;
use App\Services\Integration\Storage\GoogleDrive\GoogleDrive;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexGoogleDrive implements ShouldQueue
{
    use Queueable;

    public function handle(
        GoogleDrive $drive,
    ): void {
        /** @var IndexingWorkflowStep $indexing */
        $indexing = IndexingWorkflowStep::create([
            'integration' => 'google_drive',
            'status' => 'downloading',
            'job_id' => $this->job->payload()['uuid'],
        ]);

        $files = $drive->listDirectoryContents();
        $indexing->update([
            'status' => 'processing',
            'overall_items' => count($files),
        ]);

        foreach ($files as $i => $file) {
            if (! $this->fileNeedsIndexing($file)) {
                $indexing->increment('skipped_items', 1);
                if ($i === count($files) - 1) {
                    $indexing->update([
                        'status' => 'completed',
                    ]);
                }

                continue;
            }

            $count = Document::query()
                ->where('source_type', 'google_drive')
                ->where('source_id', $file->extraMetadata()['id'])
                ->delete();

            $indexing->increment('deleted_items', $count);
            IndexFile::dispatch($file, $indexing->id, 'google_drive');
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
