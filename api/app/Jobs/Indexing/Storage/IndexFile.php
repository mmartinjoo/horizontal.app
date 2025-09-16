<?php

namespace App\Jobs\Indexing\Storage;

use App\Exceptions\NoContentToIndexException;
use App\Models\DocumentChunk;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowItem;
use App\Services\File\PdfParser;
use App\Services\Indexing\TextChunker;
use App\Services\Integration\Storage\DataTransferObjects\File;
use App\Services\Integration\Storage\GoogleDrive\GoogleDrive;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

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
