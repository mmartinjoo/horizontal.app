<?php

namespace App\Jobs\Indexing\CodeRepository\GitHub;

use App\Jobs\Indexing\CodeRepository\GitHub\IndexPullRequest;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\IndexingWorkflow;
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

                $comments = $github->pullRequestComments(
                    pullRequest: $pullRequest,
                );
            
                IndexPullRequest::dispatch($pullRequest, $comments, $indexing->id);
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
}
