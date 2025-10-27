<?php

namespace App\Jobs\Indexing\CodeRepository;

use App\Exceptions\NoContentToIndexException;
use App\Jobs\Indexing\IndexingStepItemJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentComment;
use App\Models\IndexingWorkflowStepItem;
use App\Models\Participant;
use App\Services\Indexing\TextChunker;
use App\Services\Integration\CodeRepository\CodeRepository;
use App\Services\Integration\CodeRepository\DataTransferObjects\PullRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Exception;

class IndexPullRequest extends IndexingStepItemJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private PullRequest $pullRequest,
        private CodeRepository $codeRepository,
    ) {}

    public function handle(TextChunker $textChunker): void
    {
        try {
            $doc = Document::create([
                'source_type' => 'github_pr',
                'source_id' => $this->pullRequest->id,
                'source_url' => $this->pullRequest->url,
                'title' => $this->pullRequest->title,
                'priority' => 'high',
                'metadata' => $this->pullRequest,
            ]);
            
            $indexingWorkflowStepItem = IndexingWorkflowStepItem::create([
                'indexing_workflow_step_bucket_id' => $this->indexingWorkflowStepBucketId,
                'data' => $this->pullRequest,
                'status' => 'processing',
                'document_id' => $doc->id,
                'job_id' => $this->job->payload()['uuid'],
            ]);

            $preview = $this->pullRequest->title;
            if ($this->pullRequest->description) {
                $chunks = $textChunker->chunk($this->pullRequest->description);
                if (count($chunks) === 0) {
                    $indexingWorkflowStepItem->update([
                        'status' => 'warning',
                    ]);
                    throw new NoContentToIndexException('Chunk is empty: ' . json_encode($this->pullRequest));
                }
                if (count($chunks) === 1 && strlen(trim($chunks->first())) === 0) {
                    $indexingWorkflowStepItem->update([
                        'status' => 'warning',
                    ]);
                    throw new NoContentToIndexException('Chunk contains one empty item: ' . json_encode($this->pullRequest));
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

            $this->addParticipant($doc, $this->pullRequest->author, 'author');

            if ($this->pullRequest->assignee) {
                $this->addParticipant($doc, $this->pullRequest->assignee, 'assignee');
            }

            foreach ($this->pullRequest->reviewers as $reviewer) {
                $this->addParticipant($doc, $reviewer, 'reviewer');
            }

            $comments = $this->codeRepository->pullRequestComments($this->pullRequest);
            foreach ($comments as $comment) {
                $participant = $this->addParticipant($doc, $comment->author, 'commenter');
                DocumentComment::create([
                    'document_id' => $doc->id,
                    'author_id' => $participant->id,
                    'body' => $comment->body,
                    'commented_at' => $comment->createdAt,
                    'comment_id' => $comment->externalId,
                    'metadata' => $comment,
                ]);
            }

            $doc->update([
                'preview' => $preview,
            ]);
            $indexingWorkflowStepItem->update([
                'status' => 'completed',
            ]);
        } catch (Exception $e) {
            $indexingWorkflowStepItem->update([
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
