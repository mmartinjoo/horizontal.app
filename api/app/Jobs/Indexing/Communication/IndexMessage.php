<?php

namespace App\Jobs\Indexing\Communication;

use App\Enums\Indexing\WorkflowStatus;
use App\Exceptions\NoContentToIndexException;
use App\Jobs\Indexing\IndexingStepItemJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\IndexingWorkflowStepItem;
use App\Models\Participant;
use App\Services\Indexing\TextChunker;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use App\Services\Integration\Communication\DataTransferObjects\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class IndexMessage extends IndexingStepItemJob implements ShouldQueue
{
    use Queueable;

    private ?int $createdIndexingWorkflowItemId = null;

    public function __construct(
        private Message $message,
        private string $vendor,
    ) {
        $this->onQueue('indexing');
    }

    public function handle(TextChunker $textChunker)
    {
        try {
            $document = Document::create([
                'source' => $this->vendor,
                'source_type' => 'message',
                'source_id' => $this->message->externalId,
                'source_url' => $this->message->url,
                'title' => "{$this->message->author->realName}'s message in #{$this->message->channel->name}",
                'priority' => 'high',
                'metadata' => $this->message,
            ]);

            $indexingWorkflowItem = IndexingWorkflowStepItem::create([
                'indexing_workflow_step_bucket_id' => $this->indexingWorkflowStepBucketId,
                'data' => $this->message,
                'status' => WorkflowStatus::Processing->value,
                'document_id' => $document->id,
                'job_id' => $this->job->payload()['uuid'],
            ]);
            $this->createdIndexingWorkflowItemId = $indexingWorkflowItem->id;

            $chunks = $textChunker->chunk($this->message->message);
            if (count($chunks) === 0) {
                throw new NoContentToIndexException('Chunk is empty: ' . json_encode($this->message));
            }
            if (count($chunks) === 1 && strlen(trim($chunks->first())) === 0) {
                throw new NoContentToIndexException('Chunk contains one empty item: ' . json_encode($this->message));
            }

            $document->update([
                'preview' => $chunks->first(),
            ]);
            
            if ($this->message->author) {
                $author = Participant::getOrCreate($this->message->author?->realName);
                $document->participants()->attach($author->id, [
                    'context' => 'author',
                ]);
            }

            /** @var User $mentionedUser */
            foreach ($this->message->mentions as $mentionedUser) {
                $p = Participant::getOrCreate($mentionedUser->realName);
                $document->participants()->attach($p->id, [
                    'context' => 'mentioned',
                ]);
            }

            foreach ($chunks as $i => $chunk) {
                DocumentChunk::create([
                    'document_id' => $document->id,
                    'body' => $chunk,
                    'position' => $i+1,
                ]);
            }

            $indexingWorkflowItem->update([
                'status' => WorkflowStatus::Completed->value,
            ]);
        } catch (Throwable $e) {
            if ($this->createdIndexingWorkflowItemId) {
                $item = IndexingWorkflowStepItem::findOrFail($this->createdIndexingWorkflowItemId);
                $item->update(attributes: [
                    'status' => WorkflowStatus::Failed->value,
                    'error_message' => $e->getMessage(),
                ]); 
            }                       
            throw $e;
        }
    }
}
