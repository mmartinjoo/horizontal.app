<?php

namespace App\Jobs\Indexing\TaskManagement\Linear;

use App\Jobs\Indexing\TaskManagement\IndexIssue;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\IndexingWorkflowStepItem;
use App\Models\IndexingWorkflowStep;
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
        /** @var IndexingWorkflowStep $indexing */
        $indexingWorkflow = IndexingWorkflowStep::create([
            'integration' => 'linear',
            'status' => 'syncing',
            'job_id' => $this->job->payload()['uuid'],
        ]);

        $issues = $linear->issues();
        $indexingWorkflow->increment('overall_items', count($issues));

        foreach ($issues as $i => $issue) {
            if (! $this->issueNeedsIndexing($issue)) {
                $indexingWorkflow->increment('skipped_items', 1);
                if ($i === count($issues) - 1) {
                    $indexingWorkflow->update([
                        'status' => 'completed',
                    ]);
                }

                continue;
            }

            $this->processIssue($linear, $issue, $indexingWorkflow);
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

    private function processIssue(Linear $linear, Issue $issue, IndexingWorkflowStep $indexingWorkflow): void
    {
        // Delete existing document if it exists
        $count = Document::query()
            ->where('source_type', 'linear')
            ->where('source_id', $issue->id)
            ->delete();

        $indexingWorkflow->increment('deleted_items', $count);

        // Create new document
        $doc = Document::create([
            'source_type' => 'linear',
            'source_id' => $issue->id,
            'source_url' => $issue->url,
            'title' => $issue->title,
            'metadata' => $issue,
        ]);
        $indexingItem = IndexingWorkflowStepItem::create([
            'indexing_workflow_step_id' => $indexingWorkflow->id,
            'data' => $issue,
            'status' => 'queued',
            'document_id' => $doc->id,
        ]);

        // Process comments, watchers, and participants in the next sub-tasks
        $this->processIssueComments($linear, $doc, $issue);
        $this->processIssueParticipants($linear, $doc, $issue);

        // Dispatch IndexIssue job for content processing
        IndexIssue::dispatch($doc, $issue, $indexingItem->id);
    }

    private function processIssueComments(Linear $linear, Document $doc, Issue $issue): void
    {
        $commentsData = $linear->comments($issue);
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

    private function processIssueParticipants(Linear $linear, Document $doc, Issue $issue): void
    {
        // Process assignee
        if ($issue->assignee) {
            $p = Participant::updateOrCreate(
                [
                    'slug' => Str::slug($issue->assignee),
                    'type' => 'person',
                ],
                [
                    'slug' => Str::slug($issue->assignee),
                    'name' => $issue->assignee,
                    'type' => 'person',
                ],
            );

            $doc->participants()->attach($p->id, [
                'context' => 'assignee',
            ]);
        }

        // Process watchers/subscribers
        $watchersData = $linear->watchers($issue);
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
