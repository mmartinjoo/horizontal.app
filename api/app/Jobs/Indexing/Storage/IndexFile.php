<?php

namespace App\Jobs\Indexing\Storage;

use App\Enums\Indexing\WorkflowStepItemStatus;
use App\Exceptions\NoContentToIndexException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\IndexingWorkflowStepItem;
use App\Models\IndexingWorkflowStep;
use App\Models\Participant;
use App\Services\File\PdfParser;
use App\Services\Indexing\TextChunker;
use App\Services\Integration\Storage\DataTransferObjects\File;
use App\Services\Integration\Storage\GoogleDrive\GoogleDrive;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class IndexFile implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public function __construct(
        private File $file,
        private int $indexingWorkflowStepId,
        private string $vendor,
    ) {}

    public function handle(
        GoogleDrive $drive,
        TextChunker $textChunker,
        PdfParser $pdfParser,
        Factory $storageFactory,
    ): void {
        try {
            $document = Document::create([
                'source_type' => 'google_drive',
                'source_id' => $this->file->extraMetadata()['id'],
                'title' => $this->file->path(),
                'metadata' => $this->file,
                'priority' => 'high',
            ]);
            $indexingWorkflowItem = IndexingWorkflowStepItem::create([
                'indexing_workflow_step_id' => $this->indexingWorkflowStepId,
                'data' => $this->file,
                'status' => 'processing',
                'document_id' => $document->id,
                'job_id' => $this->job->payload()['uuid'],
            ]);

            $drive->downloadFile($this->file);
            if ($this->file->mimeType() === 'application/pdf') {
                $this->indexPDF($pdfParser, $textChunker, $indexingWorkflowItem);
                $indexingWorkflowItem->update([
                    'status' => 'completed',
                ]);
                return;
            } else {
                $content = Storage::read($this->file->path());
            }

            if (strlen($content) === 0) {
                $indexingWorkflowItem->update([
                    'status' => 'completed',
                ]);
                throw new NoContentToIndexException('File is empty: '.json_encode($this->file));
            }

            $chunks = $textChunker->chunk($content);
            if (count($chunks) === 0) {
                $indexingWorkflowItem->update([
                    'status' => 'completed',
                ]);
                throw new NoContentToIndexException('Chunk is empty: '.json_encode($this->file).'; content: '.$content);
            }
            if (count($chunks) === 1 && strlen(trim($chunks->first())) === 0) {
                $indexingWorkflowItem->update([
                    'status' => 'completed',
                ]);
                throw new NoContentToIndexException('Chunk contains one empty item: '.json_encode($this->file).'; content: '.$content);
            }

            foreach ($chunks as $i => $chunk) {
                DocumentChunk::create([
                    'document_id' => $document->id,
                    'body' => $chunk,
                    'position' => $i + 1,
                ]);
            }

            $this->addParticipants($document, $storageFactory);

            $document->update([
                'preview' => $chunks->first(),
                'indexed_at' => now(),
            ]);
            $indexingWorkflowItem->update([
                'status' => 'completed',
            ]);
        } catch (Throwable $e) {
            // if the file is a weird, unknown format Postgres can throw a "Character not in repertoire invalid byte sequence for encoding 'UTF8'" exception
            // which cannot be saved in the `error_message` column. so instead of saving the message
            // `update` would throw another exception
            try {
                $indexingWorkflowItem->update([
                    'status' => WorkflowStepItemStatus::Failed->value,
                    'error_message' => $e->getMessage(),
                ]);
            } catch (Throwable $e) {
                $indexingWorkflowItem->update([
                    'status' => WorkflowStepItemStatus::Failed->value,
                    'error_message' => 'probably "Character not in repertoire". check the related job ID',
                ]);
                throw $e;
            }
            throw $e;
        } finally {
            Storage::delete($this->file->path());

            $indexingWorkflowStep = IndexingWorkflowStep::query()                    
                ->where('id', $this->indexingWorkflowStepId)
                ->lockForUpdate()
                ->firstOrFail();

            $indexingWorkflowStep->increment('processed_items');
        }
    }

    private function addParticipants(Document $document, Factory $storageFactory)
    {
        $storage = $storageFactory->create($this->vendor);
        foreach ($storage->getRevisionAuthors($this->file) as $author) {
            $p = Participant::getOrCreate($author);
            $document->participants()->attach($p->id, [
                'context' => 'revision author',
            ]);
        }

        foreach ($this->file->getOwners() as $owner) {
            $p = Participant::getOrCreate($owner);
            $document->participants()->attach($p->id, [
                'context' => 'owner',
            ]);
        }

        foreach ($storage->getComments($this->file) as $comment) {
            $p = Participant::getOrCreate($comment['author']);
            $document->comments()->create([
                'author_id' => $p->id,
                'body' => $comment['content'],
                'commented_at' => $comment['created_at'],
                'comment_id' => $comment['id'],
                'metadata' => $comment,
            ]);
        }

        if ($sharingUser = $this->file->getSharingUser()) {
            $p = Participant::getOrCreate($sharingUser);
            $document->participants()->attach($p->id, [
                'context' => 'sharing user',
            ]);
        }
    }

    private function indexPDF(PdfParser $pdfParser, TextChunker $textChunker, IndexingWorkflowStepItem $indexingWorkflowItem)
    {
        $indexingWorkflowItem->update([
            'status' => 'parsing',
        ]);

        $blocks = $pdfParser->stream($this->file->path());
        $firstChunk = '';

        $indexingWorkflowItem->update([
            'status' => 'parsed',
        ]);

        /** @var string $block */
        foreach ($blocks as $block) {
            $chunks = $textChunker->chunk($block);
            foreach ($chunks as $i => $chunk) {
                if ($i === 0) {
                    $firstChunk = $chunk;
                }
                DocumentChunk::create([
                    'document_id' => $indexingWorkflowItem->document->id,
                    'body' => $chunk,
                    'position' => $i + 1,
                ]);
            }
        }

        $indexingWorkflowItem->document()->update([
            'preview' => $firstChunk,
            'indexed_at' => now(),
        ]);
        $indexingWorkflowItem->update([
            'status' => 'prepared',
        ]);
    }
}
