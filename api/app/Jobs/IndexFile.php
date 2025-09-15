<?php

namespace App\Jobs;

use App\Exceptions\EmbeddingException;
use App\Exceptions\ExtractingEntitiesException;
use App\Exceptions\NoContentToIndexException;
use App\Integrations\Storage\File;
use App\Integrations\Storage\GoogleDrive;
use App\Models\DocumentChunk;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowItem;
use App\Services\GraphDB\GraphDB;
use App\Services\Indexing\EntityExtractor;
use App\Services\Indexing\TextChunker;
use App\Services\LLM\Embedder;
use App\Services\PdfParser;
use App\Services\VectorStore\VectorStore;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class IndexFile implements ShouldQueue
{
    use Queueable;
    use Batchable;

    public function __construct(
        private int $indexingWorkflowItemId,
        private File $file,
    ) {
    }

    public function handle(
        GoogleDrive $drive,
        TextChunker $textChunker,
        PdfParser $pdfParser,
        Embedder $embedder,
        VectorStore $vectorStore,
        EntityExtractor $entityExtractor,
        GraphDB $graphDB,
    ): void {
        try {
            $indexingWorkflowItem = IndexingWorkflowItem::find($this->indexingWorkflowItemId);
            $jobIds = $indexingWorkflowItem->job_ids;
            $jobIds[] = $this->job->payload()['uuid'];

            $indexingWorkflowItem->update([
                'status' => 'downloading',
                'job_ids' => $jobIds,
            ]);

            $drive->downloadFile($this->file);
            $indexingWorkflowItem->update([
                'status' => 'downloaded',
            ]);

            if ($this->file->mimeType() === 'application/pdf') {
                $this->indexPDF($pdfParser, $textChunker, $indexingWorkflowItem);
                $this->createEmbedding($indexingWorkflowItem, $embedder, $vectorStore, $graphDB);
//                $this->createEntities($indexingWorkflowItem, $entityExtractor, $graphDB);
                $indexingWorkflowItem->update([
                    'status' => 'completed',
                ]);
                $this->updateWorkflowStatus($indexingWorkflowItem);
                return;
            } else {
                $content = Storage::read($this->file->path());
            }

            if (strlen($content) === 0) {
                $indexingWorkflowItem->update([
                    'status' => 'warning',
                ]);
                throw new NoContentToIndexException('File is empty: ' . json_encode($this->file));
            }

            $chunks = $textChunker->chunk($content);
            if (count($chunks) === 0) {
                $indexingWorkflowItem->update([
                    'status' => 'warning',
                ]);
                throw new NoContentToIndexException('Chunk is empty: ' . json_encode($this->file) . '; content: ' . $content);
            }
            if (count($chunks) === 1 && strlen(trim($chunks->first())) === 0) {
                $indexingWorkflowItem->update([
                    'status' => 'warning',
                ]);
                throw new NoContentToIndexException('Chunk contains one empty item: ' . json_encode($this->file) . '; content: ' . $content);
            }

            foreach ($chunks as $i => $chunk) {
                DocumentChunk::create([
                    'document_id' => $indexingWorkflowItem->document->id,
                    'body' => $chunk,
                    'position' => $i+1,
                ]);
            }

            $indexingWorkflowItem->document()->update([
                'preview' => $chunks->first(),
                'indexed_at' => now(),
            ]);
            $indexingWorkflowItem->update([
                'status' => 'prepared',
            ]);
            $this->createEmbedding($indexingWorkflowItem, $embedder, $vectorStore, $graphDB);
//            $this->createEntities($indexingWorkflowItem, $entityExtractor, $graphDB);
            $indexingWorkflowItem->update([
                'status' => 'completed',
            ]);
            $this->updateWorkflowStatus($indexingWorkflowItem);
        } finally {
            Storage::delete($this->file->path());
        }
    }

    private function indexPDF(PdfParser $pdfParser, TextChunker $textChunker, IndexingWorkflowItem $indexingWorkflowItem)
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
                    'position' => $i+1,
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

    private function createEmbedding(
        IndexingWorkflowItem $indexingWorkflowItem,
        Embedder $embedder,
        VectorStore $vectorStore,
        GraphDB $graphDB,
    ) {
        try {
            $indexingWorkflowItem->update([
                'status' => 'vectorizing',
            ]);

            foreach ($indexingWorkflowItem->document->chunks as $chunk) {
                $embedding = $embedder->createEmbedding($chunk->getEmbeddableContent());
                $vectorStore->upsert($chunk, $embedding);

//                $graphDB->createNodeWithRelation(
//                    newNodeLabel: 'FileChunk',
//                    newNodeAttributes: [
//                        'id' => $chunk->id,
//                        'embedding' => $embedding,
//                    ],
//                    relation: 'CHUNK_OF',
//                    relatedNodeLabel: 'File',
//                    relatedNodeID: $indexingWorkflowItem->document->id,
//                );
            }

            $indexingWorkflowItem->update([
                'status' => 'vectorizing_completed',
            ]);
        } catch (Throwable $e) {
            $indexingWorkflowItem->update([
                'status' => 'warning',
                'error_message' => $e->getMessage(),
            ]);
            throw EmbeddingException::wrap($e);
        }
    }

    private function createEntities(
        IndexingWorkflowItem $indexingWorkflowItem,
        EntityExtractor $entityExtractor,
        GraphDB $graphDB,
    ) {
        try {
            $indexingWorkflowItem->update([
                'status' => 'extracting_entities',
            ]);

            /** @var DocumentChunk $chunk */
            foreach ($indexingWorkflowItem->document->chunks as $chunk) {
                $topics = $entityExtractor->extractTopics($chunk->body);
                $chunk->createTopics($topics['topics']);
                foreach ($chunk->topics as $topic) {
                    $graphDB->createNodeWithRelation(
                        newNodeLabel: 'Topic',
                        newNodeAttributes: [
                            'id' => $topic->id,
                            'name' => $topic->name,
                            'embedding' => $topic->embedding,
                        ],
                        relation: 'MENTIONED_IN',
                        relatedNodeLabel: 'FileChunk',
                        relatedNodeID: $chunk->id,
                        relationAttributes: [
                            'context' => $topic->pivot->context,
                            'embedding' => $topic->pivot->embedding,
                        ],
                    );
                }
            }

            $indexingWorkflowItem->update([
                'status' => 'extracting_entities_completed',
            ]);
        } catch (Throwable $e) {
            $indexingWorkflowItem->update([
                'status' => 'warning',
                'error_message' => $e->getMessage(),
            ]);
            throw ExtractingEntitiesException::wrap($e);
        }
    }

    private function updateWorkflowStatus(IndexingWorkflowItem $indexingWorkflowItem)
    {
        /** @var IndexingWorkflow $workflow */
        $workflow = $indexingWorkflowItem->indexing_workflow;
        $hasQueuedItems = $workflow->items()
            ->where('status', 'queued')
            ->exists();

        if (!$hasQueuedItems) {
            $workflow->update([
                'status' => 'completed',
            ]);
        }
    }
}
