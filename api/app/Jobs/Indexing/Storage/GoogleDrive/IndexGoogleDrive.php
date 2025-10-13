<?php

namespace App\Jobs\Indexing\Storage\GoogleDrive;

use App\Jobs\Indexing\Storage\IndexFile;
use App\Models\Document;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowItem;
use App\Models\Participant;
use App\Services\Integration\Storage\DataTransferObjects\File;
use App\Services\Integration\Storage\GoogleDrive\GoogleDrive;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class IndexGoogleDrive implements ShouldQueue
{
    use Queueable;

    public function handle(
        GoogleDrive $drive,
    ): void {
        /** @var IndexingWorkflow $indexing */
        $indexing = IndexingWorkflow::create([
            'integration' => 'google_drive',
            'status' => 'downloading',
            'job_id' => $this->job->payload()['uuid'],
        ]);

        $files = $drive->listDirectoryContents('horizontal.app');
        $indexing->update([
            'status' => 'downloaded',
            'overall_items' => count($files),
        ]);

        foreach ($files as $i => $file) {
            if (!$this->fileNeedsIndexing($file)) {
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
            $document = Document::create([
                'source_type' => 'google_drive',
                'source_id' => $file->extraMetadata()['id'],
                'title' => $file->path(),
                'metadata' => $file,
                'priority' => 'high',
            ]);
            $indexingItem = IndexingWorkflowItem::create([
                'indexing_workflow_id' => $indexing->id,
                'data' => $file,
                'status' => 'queued',
                'document_id' => $document->id,
            ]);
            IndexFile::dispatch($indexingItem->id, $file);

            foreach ($drive->getRevisionAuthors($file) as $author) {
                $p = Participant::updateOrCreate(
                    [
                        'slug' => Str::slug($author),
                        'type' => 'person',
                    ],
                    [
                        'slug' => Str::slug($author),
                        'name' => $author,
                        'type' => 'person',
                    ],
                );
                $document->participants()->attach($p->id, [
                    'context' => 'revision author',
                ]);
            }

            foreach ($file->getOwners() as $owner) {
                $p = Participant::updateOrCreate(
                    [
                        'slug' => Str::slug($owner),
                        'type' => 'person',
                    ],
                    [
                        'slug' => Str::slug($owner),
                        'name' => $owner,
                        'type' => 'person',
                    ],
                );
                $document->participants()->attach($p->id, [
                    'context' => 'owner',
                ]);
            }

            foreach ($drive->getComments($file) as $comment) {
                $p = Participant::updateOrCreate(
                    [
                        'slug' => Str::slug($comment['author']),
                        'type' => 'person',
                    ],
                    [
                        'slug' => Str::slug($comment['author']),
                        'name' => $comment['author'],
                        'type' => 'person',
                    ],
                );
                $document->comments()->create([
                    'author_id' => $p->id,
                    'body' => $comment['content'],
                    'commented_at' => $comment['created_at'],
                    'comment_id' => $comment['id'],
                    'metadata' => $comment,
                ]);
            }

            if ($sharingUser = $file->getSharingUser()) {
                $p = Participant::updateOrCreate(
                    [
                        'slug' => Str::slug($sharingUser),
                        'type' => 'person',
                    ],
                    [
                        'slug' => Str::slug($sharingUser),
                        'name' => $sharingUser,
                        'type' => 'person',
                    ],
                );
                $document->participants()->attach($p->id, [
                    'context' => 'sharing user',
                ]);
            }
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
