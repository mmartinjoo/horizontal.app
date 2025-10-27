<?php

namespace App\Jobs\Indexing\TaskManagement;

use App\Enums\Indexing\WorkflowStatus;
use App\Exceptions\NoContentToIndexException;
use App\Jobs\Indexing\IndexingStepItemJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentComment;
use App\Models\IndexingWorkflowStepItem;
use App\Models\Participant;
use App\Services\Indexing\TextChunker;
use App\Services\Integration\TaskManagement\DataTransferObjects\Issue;
use App\Services\Integration\TaskManagement\DataTransferObjects\IssueComment;
use App\Services\Integration\TaskManagement\TaskManagement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class IndexIssue extends IndexingStepItemJob implements ShouldQueue
{
    use Queueable;

    private ?int $createdIndexingWorkflowItemId = null;

    public function __construct(
        private Issue $issue,
        private TaskManagement $adapter,
        private string $vendor,
    ) {
        $this->onQueue('indexing');
    }

    public function handle(
        TextChunker $textChunker,
    ): void {
        try {
            $document = Document::create([
                'source' => $this->vendor,
                'source_type' => 'issue',
                'source_id' => $this->issue->id,
                'source_url' => $this->issue->url,
                'title' => $this->issue->title,
                'metadata' => $this->issue,
            ]);
            $indexingWorkflowItem = IndexingWorkflowStepItem::create([
                'indexing_workflow_step_bucket_id' => $this->indexingWorkflowStepBucketId,
                'data' => $this->issue,
                'status' => WorkflowStatus::Processing->value,
                'document_id' => $document->id,
                'job_id' => $this->job->payload()['uuid'],
            ]);
            $this->createdIndexingWorkflowItemId = $indexingWorkflowItem->id;

            $chunks = $textChunker->chunk($this->issue->title.' '.$this->issue->description);
            if (count($chunks) === 0) {
                $indexingWorkflowItem->update([
                    'status' => 'warning',
                ]);
                throw new NoContentToIndexException('Chunk is empty: '.json_encode($this->issue).'; content: '.$this->issue->toString());
            }
            if (count($chunks) === 1 && strlen(trim($chunks->first())) === 0) {
                $indexingWorkflowItem->update([
                    'status' => 'warning',
                ]);
                throw new NoContentToIndexException('Chunk contains one empty item: '.json_encode($this->issue).'; content: '.$this->issue->toString());
            }

            foreach ($chunks as $i => $chunk) {
                DocumentChunk::create([
                    'document_id' => $indexingWorkflowItem->document->id,
                    'body' => $chunk,
                    'position' => $i + 1,
                ]);
            }
            $document->update([
                'preview' => $chunks->first(),
            ]);        

            if ($this->issue->assignee) {
                $assignee = Participant::getOrCreate($this->issue->assignee);
                $document->participants()->attach($assignee->id, [
                    'context' => 'assignee',
                ]);
            }

            $this->processIssueComments($document);

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

    private function processIssueComments(Document $document): void
    {
        $comments = $this->adapter->comments($this->issue);

        /** @var IssueComment $comment */
        foreach ($comments as $comment) {
            $p = Participant::getOrCreate($comment->author);
            DocumentComment::create([
                'document_id' => $document->id,
                'author_id' => $p->id,
                'body' => $comment->body,
                'commented_at' => $comment->createdAt,
                'comment_id' => $comment->id,
                'metadata' => $comment,
            ]);
        }
    }
}
