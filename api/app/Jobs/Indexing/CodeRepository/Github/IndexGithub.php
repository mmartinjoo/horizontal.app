<?php

namespace App\Jobs\Indexing\CodeRepository\GitHub;

use App\Jobs\Indexing\CodeRepository\GitHub\IndexPullRequest;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentComment;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowItem;
use App\Models\Participant;
use App\Services\Indexing\TextChunker;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use App\Services\Integration\CodeRepository\DataTransferObjects\PullRequest;
use App\Services\Integration\CodeRepository\GitHub\GitHub;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class IndexGitHub implements ShouldQueue
{
    use Queueable;

    public function handle(
        TextChunker $textChunker,
        GitHub $github,
    ): void {
        /** @var IndexingWorkflow $indexing */
        $indexing = IndexingWorkflow::create([
            'integration' => 'github',
            'status' => 'syncing',
            'job_id' => $this->job->payload()['uuid'],
        ]);

        /** @var LazyCollection<Repository> $repositories */
        $repositories = $github->repositories();

        /** @var Repository $repo */
        foreach ($repositories as $repo) {
            $pullRequests = $github->pullRequests(
                repo: $repo,
            );

            $indexing->increment('overall_items', count($pullRequests));

            foreach ($pullRequests as $i => $pullRequest) {
                if (!$this->pullRequestNeedsIndexing($pullRequest)) {
                    $indexing->increment('skipped_items', 1);
                    continue;
                }

                // Delete existing document if it exists
                $count = Document::query()
                    ->where('source_type', 'github_pr')
                    ->where('source_id', $pullRequest->id)
                    ->delete();

                $indexing->increment('deleted_items', $count);

                // Determine priority based on PR age
                $priority = $this->determinePriority($pullRequest);

                // Create document for the PR
                $doc = Document::create([
                    'source_type' => 'github_pr',
                    'source_id' => $pullRequest->id,
                    'source_url' => $pullRequest->url,
                    'title' => $pullRequest->title,
                    'priority' => $priority,
                    'metadata' => $pullRequest,
                ]);

                // Create indexing workflow item
                $indexingItem = IndexingWorkflowItem::create([
                    'indexing_workflow_id' => $indexing->id,
                    'data' => $pullRequest->toArray(),
                    'status' => 'queued',
                    'document_id' => $doc->id,
                ]);

                // Dispatch job to index PR content
                IndexPullRequest::dispatch($doc, $pullRequest, $indexingItem->id);

                // Chunk and store PR description
                if ($pullRequest->description) {
                    $this->chunkAndStoreContent($doc, $pullRequest->description, $textChunker);
                }

                // Add author as participant
                $this->addParticipant($doc, $pullRequest->author, 'author');

                // Add assignee as participant
                if ($pullRequest->assignee) {
                    $this->addParticipant($doc, $pullRequest->assignee, 'assignee');
                }

                // Add reviewers as participants
                foreach ($pullRequest->reviewers as $reviewer) {
                    $this->addParticipant($doc, $reviewer, 'reviewer');
                }

                // Fetch and store comments
                $comments = $github->pullRequestComments(
                    repo: $repo,
                    pullRequest: $pullRequest,
                );

                foreach ($comments as $comment) {
                    $participant = $this->addParticipant($doc, $comment->author, 'commenter');

                    DocumentComment::create([
                        'document_id' => $doc->id,
                        'author_id' => $participant->id,
                        'body' => $comment->body,
                        'commented_at' => $comment->createdAt,
                        'comment_id' => $comment->id,
                        'metadata' => $comment,
                    ]);

                }

                // Update document with preview (first chunk)
                $firstChunk = DocumentChunk::where('document_id', $doc->id)
                    ->orderBy('position')
                    ->first();

                if ($firstChunk) {
                    $doc->update([
                        'preview' => $firstChunk->body,
                        'indexed_at' => now(),
                    ]);
                }
            }
        }

        $indexing->update(['status' => 'completed']);
    }

    private function chunkAndStoreContent(Document $doc, string $content, TextChunker $textChunker): void
    {
        if (empty(trim($content))) {
            return;
        }

        $chunks = $textChunker->chunk($content);

        // Get the current max position for this document
        $maxPosition = DocumentChunk::where('document_id', $doc->id)
            ->max('position') ?? 0;

        foreach ($chunks as $i => $chunk) {
            if (empty(trim($chunk))) {
                continue;
            }

            DocumentChunk::create([
                'document_id' => $doc->id,
                'body' => $chunk,
                'position' => $maxPosition + $i + 1,
            ]);
        }
    }

    private function addParticipant(Document $doc, string $username, string $context): Participant
    {
        $participant = Participant::updateOrCreate(
            [
                'slug' => Str::slug($username),
                'type' => 'person',
            ],
            [
                'slug' => Str::slug($username),
                'name' => $username,
                'type' => 'person',
            ]
        );

        // Check if participant is already attached with this context
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

    private function pullRequestNeedsIndexing(PullRequest $pullRequest): bool
    {
        $existingContent = Document::query()
            ->where('source_id', $pullRequest->id)
            ->where('source_type', 'github_pr')
            ->first();

        if ($existingContent === null) {
            return true;
        }

        return $pullRequest->updatedAt->gt(
            $existingContent->indexed_at ?? Carbon::parse('1900-01-01 00:00:00')
        );
    }

    private function determinePriority(PullRequest $pullRequest): string
    {
        $age = now()->diffInDays($pullRequest->createdAt);

        // High priority: Recent PRs (last month)
        if ($age <= 30) {
            return 'high';
        }

        // Medium priority: PRs from 1-2 months ago
        if ($age <= 60) {
            return 'medium';
        }

        // Low priority: Older PRs
        return 'low';
    }
}
