<?php

namespace App\Jobs\Indexing\CodeRepository;

use App\Exceptions\NoContentToIndexException;
use App\Jobs\Indexing\IndexingStepItemJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\IndexingWorkflowStepItem;
use App\Models\Participant;
use App\Services\Indexing\TextChunker;
use App\Services\Integration\CodeRepository\DataTransferObjects\Issue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class IndexIssue extends IndexingStepItemJob implements ShouldQueue
{
    use Queueable;

    private ?int $createdIndexingWorkflowItemId = null;

    public function __construct(
        private Issue $issue,
        private string $vendor,
    ) {
        $this->onQueue('indexing');
    }

    public function handle(TextChunker $textChunker)
    {
        try {
            $doc = Document::create([
                'source' => $this->vendor,
                'source_type' => 'issue',
                'source_id' => $this->issue->externalId,
                'source_url' => $this->issue->url,
                'title' => $this->issue->title,
                'priority' => 'high',
                'metadata' => $this->issue,
            ]);
            $indexingWorkflowItem = IndexingWorkflowStepItem::createForDocument(
                document: $doc,
                bucketId: $this->indexingWorkflowStepBucketId,
                data: (array)$this->issue,
                jobId: $this->job->payload()['uuid'],
            );
            $this->createdIndexingWorkflowItemId = $indexingWorkflowItem->id;

            $preview = $this->issue->title;
            if ($this->issue->body) {
                $chunks = $textChunker->chunk($this->issue->body);
                if (count($chunks) === 0) {
                    $indexingWorkflowItem->update([
                        'status' => 'warning',
                    ]);
                    throw new NoContentToIndexException('Chunk is empty: ' . json_encode($this->issue));
                }
                if (count($chunks) === 1 && strlen(trim($chunks->first())) === 0) {
                    $indexingWorkflowItem->update([
                        'status' => 'warning',
                    ]);
                    throw new NoContentToIndexException('Chunk contains one empty item: ' . json_encode($this->issue));
                }
                foreach ($chunks as $i => $chunk) {
                    DocumentChunk::create([
                        'document_id' => $doc->id,
                        'body' => $chunk,
                        'position' => $i+1,
                    ]);
                }
                $preview = $chunks->first();
            }

            $this->addParticipant($doc, $this->issue->author, 'author');

            $doc->update([
                'preview' => $preview,
            ]);
            $indexingWorkflowItem->update([
                'status' => 'completed',
            ]);
        } catch (Throwable $e) {
            $indexingWorkflowItem->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
        
    }

    private function addParticipant(Document $doc, string $username, string $context): Participant
    {
        $participant = Participant::getOrCreate(Str::slug($username));
        $exists = $doc->participants()
            ->wherePivot('context', $context)
            ->where('participants.id', $participant->id)
            ->exists();

        if (!$exists) {
            $doc->participants()->attach($participant->id, [
                'context' => $context,
            ]);
        }

        return $participant;
    }
}