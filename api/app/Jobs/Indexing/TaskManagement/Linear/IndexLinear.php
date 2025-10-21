<?php

namespace App\Jobs\Indexing\TaskManagement\Linear;

use App\Jobs\Indexing\TaskManagement\IndexIssue;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\Participant;
use App\Services\Integration\TaskManagement\DataTransferObjects\Issue;
use App\Services\Integration\TaskManagement\DataTransferObjects\IssueComment;
use App\Services\Integration\TaskManagement\Linear\Linear;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class IndexLinear implements ShouldQueue
{
    use Queueable;

    public function handle(Linear $linear): void
    {
        $issues = $linear->getIssues();

        foreach ($issues as $issueData) {
            $transformedIssue = $linear->transformIssueForDocument($issueData);
            $description = $transformedIssue['description'];

            $issue = Issue::fromLinear($issueData, $description);

            if (!$this->issueNeedsIndexing($issue)) {
                continue;
            }

            $this->processIssue($linear, $issue, $issueData);
        }
    }

    private function issueNeedsIndexing(Issue $issue): bool
    {
        $existingContent = Document::query()
            ->where('source_id', $issue->id)
            ->where('source_type', 'linear')
            ->first();

        if ($existingContent === null) {
            return true;
        }

        return $issue->getLastUpdatedAt()->gt($existingContent->indexed_at ?? now()->subYears(100));
    }

    private function processIssue(Linear $linear, Issue $issue, array $issueData): void
    {
        // Delete existing document if it exists
        $count = Document::query()
            ->where('source_type', 'linear')
            ->where('source_id', $issue->id)
            ->delete();

        // Create new document
        $doc = Document::create([
            'source_type' => 'linear',
            'source_id' => $issue->id,
            'source_url' => $issue->url,
            'title' => $issue->title,
            'metadata' => $issueData,
        ]);

        // Process comments, watchers, and participants in the next sub-tasks
        $this->processIssueComments($linear, $doc, $issueData);
        $this->processIssueParticipants($linear, $doc, $issueData);

        // Dispatch IndexIssue job for content processing
        IndexIssue::dispatch($doc, $issue);
    }

    private function processIssueComments(Linear $linear, Document $doc, array $issueData): void
    {
        $commentsData = $linear->getIssueComments($issueData['id']);
        $comments = IssueComment::collectLinear($commentsData);

        foreach ($comments as $comment) {
            $p = Participant::updateOrCreate(
                [
                    'slug' => Str::slug($comment->author),
                    'type' => 'person',
                ],
                [
                    'slug' => Str::slug($comment->author),
                    'name' => $comment->author,
                    'type' => 'person',
                ],
            );

            DocumentComment::create([
                'document_id' => $doc->id,
                'author_id' => $p->id,
                'body' => $comment->body,
                'commented_at' => $comment->createdAt,
                'comment_id' => $comment->id,
                'metadata' => $comment,
            ]);
        }
    }

    private function processIssueParticipants(Linear $linear, Document $doc, array $issueData): void
    {
        // Process assignee
        if (isset($issueData['assignee']) && !empty($issueData['assignee']['displayName'])) {
            $assignee = $issueData['assignee'];
            $p = Participant::updateOrCreate(
                [
                    'slug' => Str::slug($assignee['displayName']),
                    'type' => 'person',
                ],
                [
                    'slug' => Str::slug($assignee['displayName']),
                    'name' => $assignee['displayName'],
                    'type' => 'person',
                ],
            );

            $doc->participants()->attach($p->id, [
                'context' => 'assignee',
            ]);
        }

        // Process watchers/subscribers
        $watchersData = $linear->getIssueWatchers($issueData['id']);
        foreach ($watchersData as $watcher) {
            if (empty($watcher['displayName'])) {
                continue;
            }

            $p = Participant::updateOrCreate(
                [
                    'slug' => Str::slug($watcher['displayName']),
                    'type' => 'person',
                ],
                [
                    'slug' => Str::slug($watcher['displayName']),
                    'name' => $watcher['displayName'],
                    'type' => 'person',
                ],
            );

            $doc->participants()->attach($p->id, [
                'context' => 'watcher',
            ]);
        }
    }
}