<?php

namespace App\Jobs\Integration\TaskManagement;

use App\Exceptions\NoContentToIndexException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowItem;
use App\Models\Participant;
use App\Services\Indexing\TextChunker;
use App\Services\Integration\TaskManagement\DataTransferObjects\Issue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class IndexIssue implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Document $document,
        private Issue $issue,
        private int $indexingWorkflowItemId,
    ) {
    }

    public function handle(
        TextChunker $textChunker,
    ): void {
        $indexingWorkflowItem = IndexingWorkflowItem::findOrFail($this->indexingWorkflowItemId);
        $chunks = $textChunker->chunk($this->issue->title . ' ' . $this->issue->description);
        if (count($chunks) === 0) {
            $indexingWorkflowItem->update([
                'status' => 'warning',
            ]);
            throw new NoContentToIndexException('Chunk is empty: ' . json_encode($this->issue) . '; content: ' . $this->issue->toString());
        }
        if (count($chunks) === 1 && strlen(trim($chunks->first())) === 0) {
            $indexingWorkflowItem->update([
                'status' => 'warning',
            ]);
            throw new NoContentToIndexException('Chunk contains one empty item: ' . json_encode($this->issue) . '; content: ' . $this->issue->toString());
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

        if ($this->issue->assignee) {
            $assignee = Participant::updateOrCreate(
                [
                    'slug' => Str::slug($this->issue->assignee),
                    'type' => 'person',
                ],
                [
                    'slug' => Str::slug($this->issue->assignee),
                    'name' => $this->issue->assignee,
                    'type' => 'person',
                ],
            );
            $this->document->participants()->attach($assignee->id, [
                'context' => 'assignee',
            ]);
        }

        $this->updateWorkflowStatus($indexingWorkflowItem);
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
