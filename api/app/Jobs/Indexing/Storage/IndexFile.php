<?php

namespace App\Jobs\Indexing\Storage;

use App\Enums\Indexing\WorkflowStatus;
use App\Exceptions\NoContentToIndexException;
use App\Jobs\Indexing\IndexingStepItemJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\IndexingWorkflowStepItem;
use App\Models\Participant;
use App\Services\File\PdfParser;
use App\Services\Indexing\TextChunker;
use App\Services\Integration\Factory;
use App\Services\Integration\Storage\DataTransferObjects\File;
use App\Services\Integration\Storage\GoogleDrive\GoogleDrive;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class IndexFile extends IndexingStepItemJob implements ShouldQueue
{
    use Batchable;
    use Queueable;

    private ?int $createdIndexingWorkflowItemId = null;

    public function __construct(
        private File $file,
        private string $vendor,
    ) {
        $this->onQueue('indexing');
    }

    public function handle(
        GoogleDrive $drive,
        TextChunker $textChunker,
        PdfParser $pdfParser,
        Factory $integrationFactory,
    ): void {
        try {
            $document = Document::create([
                'source' => $this->vendor,
                'source_type' => 'file',
                'source_id' => $this->file->extraMetadata()['id'],
                'title' => $this->file->path(),
                'metadata' => $this->file,
                'priority' => 'high',
            ]);
            $indexingWorkflowItem = IndexingWorkflowStepItem::createForDocument(
                document: $document,
                bucketId: $this->indexingWorkflowStepBucketId,
                data: json_decode(json_encode($this->file), true),
                jobId: $this->job->payload()['uuid'],
            );
            $this->createdIndexingWorkflowItemId = $indexingWorkflowItem->id;

            $drive->downloadFile($this->file);
            if ($this->file->mimeType() === 'application/pdf') {
                $this->indexPDF($pdfParser, $textChunker, $document);
                $indexingWorkflowItem->update([
                    'status' => WorkflowStatus::Completed->value,
                ]);
                return;
            } else {
                $content = Storage::read($this->file->path());
            }

            if (strlen($content) === 0) {
                $indexingWorkflowItem->completed();
                throw new NoContentToIndexException('File is empty: '.json_encode($this->file));
            }

            $chunks = $textChunker->chunk($content);
            if (count($chunks) === 0) {
                $indexingWorkflowItem->completed();
                throw new NoContentToIndexException('Chunk is empty: '.json_encode($this->file).'; content: '.$content);
            }
            if (count($chunks) === 1 && strlen(trim($chunks->first())) === 0) {
                $indexingWorkflowItem->completed();
                throw new NoContentToIndexException('Chunk contains one empty item: '.json_encode($this->file).'; content: '.$content);
            }

            foreach ($chunks as $i => $chunk) {
                DocumentChunk::create([
                    'document_id' => $document->id,
                    'body' => $chunk,
                    'position' => $i + 1,
                ]);
            }

            $this->addParticipants($document, $integrationFactory);

            $document->update([
                'preview' => $chunks->first(),
            ]);
            $indexingWorkflowItem->update([
                'status' => WorkflowStatus::Completed->value,
            ]);
        } catch (Throwable $e) {       
            if (!$this->createdIndexingWorkflowItemId) {
                throw $e;
            }

            $item = IndexingWorkflowStepItem::findOrFail($this->createdIndexingWorkflowItemId);

            // if the file is a weird, unknown format Postgres can throw a "Character not in repertoire invalid byte sequence for encoding 'UTF8'" exception
            // which cannot be saved in the `error_message` column. so instead of saving the message
            // `update` would throw another exception
            try {
                $item->failed($e->getMessage()); 
            } catch (Throwable $e) {
                $item->failed('probably "Character not in repertoire". check the related job ID');
                throw $e;
            }
            throw $e;
        } finally {
            Storage::delete($this->file->path());
        }
    }

    private function addParticipants(Document $document, Factory $integrationFactory)
    {
        $storage = $integrationFactory->createStorage($this->vendor);
        foreach ($storage->revisionAuthors($this->file) as $author) {
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

        foreach ($storage->comments($this->file) as $comment) {
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

    private function indexPDF(PdfParser $pdfParser, TextChunker $textChunker, Document $document)
    {
        $blocks = $pdfParser->stream($this->file->path());
        $firstChunk = '';
        /** @var string $block */
        foreach ($blocks as $block) {
            $chunks = $textChunker->chunk($block);
            foreach ($chunks as $i => $chunk) {
                if ($i === 0) {
                    $firstChunk = $chunk;
                }
                DocumentChunk::create([
                    'document_id' => $document->id,
                    'body' => $chunk,
                    'position' => $i + 1,
                ]);
            }
        }

        $document->update([
            'preview' => $firstChunk,
        ]);
    }
}
