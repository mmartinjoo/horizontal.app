<?php

namespace App\Jobs\Indexing\Communication;

use App\Enums\Indexing\WorkflowStatus;
use App\Jobs\Indexing\IndexingStepItemJob;
use App\Models\Document;
use App\Models\IndexingWorkflowStepItem;
use App\Models\Participant;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use App\Services\Integration\Communication\DataTransferObjects\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class IndexThread extends IndexingStepItemJob implements ShouldQueue
{
    use Queueable;

    private ?int $createdIndexingWorkStepItemId = null;

    public function __construct(
        private Message $thread,
        private string $vendor,
    ) {
        $this->onQueue('indexing');
    }

    public function handle()
    {
        try {
            $indexMessageJob = new IndexMessage($this->thread, 'slack');
            $indexMessageJob->setIndexingWorkflowStepBucketId($this->indexingWorkflowStepBucketId);
            dispatch_sync($indexMessageJob);

            $document = Document::query()
                ->where('source', $this->vendor)
                ->where('source_type', 'message')
                ->where('source_id', $this->thread->externalId)
                ->firstOrFail();

            $indexingWorkflowItem = IndexingWorkflowStepItem::query()
                ->where('document_id', $document->id)
                ->firstOrFail();

            $this->createdIndexingWorkStepItemId = $indexingWorkflowItem->id;

            /** @var User $mentionedUser */
            foreach ($this->thread->mentions as $mentionedUser) {
                $p = Participant::getOrCreate($mentionedUser->realName);
                $document->participants()->attach($p->id, [
                    'context' => 'mentioned',
                ]);
            }

            /** @var Message $reply */
            foreach ($this->thread->replies as $reply) {
                $p = Participant::getOrCreate($reply->author->realName);
                $comment = $document->comments()->create([
                    'author_id' => $p->id,
                    'body' => $reply->message,
                    'comment_id' => $reply->externalId,
                    'metadata' => $reply,
                    'commented_at' => $reply->createdAt,
                ]);

                /** @var User $mentionedUser */
                foreach ($reply->mentions as $mentionedUser) {
                    $p = Participant::getOrCreate($mentionedUser->realName);
                    $comment->participants()->attach($p->id, [
                        'context' => 'mentioned',
                    ]);
                }
            }
            $indexingWorkflowItem->update([
                'status' => WorkflowStatus::Completed->value,
            ]);
        } catch (Throwable $e) {
            if ($this->createdIndexingWorkStepItemId) {
                $item = IndexingWorkflowStepItem::findOrFail($this->createdIndexingWorkStepItemId);
                $item->update(attributes: [
                    'status' => WorkflowStatus::Failed->value,
                    'error_message' => $e->getMessage(),
                ]); 
            }
                       
            throw $e;
        }       
    }
}
