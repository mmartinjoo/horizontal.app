<?php

namespace App\Jobs\Indexing\CodeRepository;

use App\Exceptions\NoContentToIndexException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\IndexingWorkflowStepItem;
use App\Models\Participant;
use App\Services\Indexing\TextChunker;
use App\Services\Integration\CodeRepository\DataTransferObjects\Issue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class IndexIssue implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Issue $issue,
        private int $indexingWorkflowStepId,
    ) {
    }

    public function handle(TextChunker $textChunker)
    {
        $doc = Document::create([
            'source_type' => 'github_issue',
            'source_id' => $this->issue->externalId,
            'source_url' => $this->issue->url,
            'title' => $this->issue->title,
            'priority' => 'high',
            'metadata' => $this->issue,
        ]);
        
        $indexingItem = IndexingWorkflowStepItem::create([
            'indexing_workflow_step_id' => $this->indexingWorkflowStepId,
            'data' => $this->issue,
            'status' => 'processing',
            'document_id' => $doc->id,
            'job_ids' => [$this->job->payload()['uuid']],
        ]);

        $preview = $this->issue->title;
        if ($this->issue->body) {
            $chunks = $textChunker->chunk($this->issue->body);
            if (count($chunks) === 0) {
                $indexingItem->update([
                    'status' => 'warning',
                ]);
                throw new NoContentToIndexException('Chunk is empty: ' . json_encode($this->issue));
            }
            if (count($chunks) === 1 && strlen(trim($chunks->first())) === 0) {
                $indexingItem->update([
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
            'indexed_at' => now(),
        ]);
        $indexingItem->update([
            'status' => 'completed',
        ]);
        $this->updateWorkflowStatus($indexingItem);
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

    private function updateWorkflowStatus(IndexingWorkflowStepItem $indexingItem): void
    {
        $workflow = $indexingItem->indexing_workflow_step;
        if (!$workflow) {
            return;
        }

        $hasQueuedItems = $workflow->items()
            ->whereIn('status', ['queued', 'processing'])
            ->exists();

        if (!$hasQueuedItems) {
            $workflow->update([
                'status' => 'completed',
            ]);
        }
    }
}