<?php

namespace App\Jobs\Integration\TaskManagement\Jira;

use App\Jobs\Integration\TaskManagement\IndexIssue;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\DocumentWorklog;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowItem;
use App\Models\JiraIntegration;
use App\Models\JiraProject;
use App\Models\Participant;
use App\Models\Team;
use App\Services\Integration\TaskManagement\Issue;
use App\Services\Integration\TaskManagement\IssueComment;
use App\Services\Integration\TaskManagement\IssueWorklog;
use App\Services\Integration\TaskManagement\Jira\Jira;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class IndexJira implements ShouldQueue
{
    use Queueable;

    public function __construct(private Team $team)
    {
    }

    public function handle(
        Jira $jira,
    ): void {
        /** @var IndexingWorkflow $indexing */
        $indexing = IndexingWorkflow::create([
            'integration' => 'jira',
            'status' => 'syncing',
            'team_id' => $this->team->id,
            'job_id' => $this->job->payload()['uuid'],
        ]);

        $jiraIntegration = JiraIntegration::where('team_id', $this->team->id)->firstOrFail();
        $projects = $jira->getProjects($this->team);
        foreach ($projects as $projectData) {
            $project = JiraProject::query()
                ->where('key', $projectData['key'])
                ->where('team_id', $this->team->id)
                ->first();

            if (!$project) {
                JiraProject::create([
                    'team_id' => $this->team->id,
                    'jira_integration_id' => $jiraIntegration->id,
                    'title' => $projectData['name'],
                    'key' => $projectData['key'],
                    'jira_id' => $projectData['id'],
                ]);
            }

            $prios = ['high', 'medium', 'low'];
            foreach ($prios as $prio) {
                $dateRange = match($prio) {
                    'high' => ['from' => now()->subMonths(1), 'to' => now()],
                    'medium' => ['from' => now()->subMonths(3), 'to' => now()->subMonths(1)],
                    'low' => ['from' => now()->subMonths(6), 'to' => now()->subMonths(3)],
                };
                $issues = $jira->getIssues($this->team, $projectData['key'], $dateRange['from'], $dateRange['to']);
                $indexing->increment('overall_items', count($issues));
                foreach ($issues as $i => $issueData) {
                    $description = $this->extractTextFromDocument($issueData['fields']['description'] ?? []);
                    $issue = Issue::fromJira($issueData, $description);
                    if (!$this->issueNeedsIndexing($issue)) {
                        $indexing->increment('skipped_items', 1);
                        if ($i === count($issues) - 1) {
                            $indexing->update([
                                'status' => 'completed',
                            ]);
                        }
                        continue;
                    }
                    $count = Document::query()
                        ->where('team_id', $this->team->id)
                        ->where('source_type', 'jira')
                        ->where('source_id', $issue->id)
                        ->delete();

                    $indexing->increment('deleted_items', $count);
                    $doc = Document::create([
                        'team_id' => $this->team->id,
                        'source_type' => 'jira',
                        'source_id' => $issue->id,
                        'source_url' => $issue->url,
                        'title' => $issue->title,
                        'priority' => $prio,
                        'metadata' => $issueData,
                    ]);
                    $indexingItem = IndexingWorkflowItem::create([
                        'indexing_workflow_id' => $indexing->id,
                        'data' => $issue,
                        'status' => 'queued',
                        'document_id' => $doc->id,
                    ]);
                    IndexIssue::dispatch($doc, $issue, $indexingItem->id);

                    $commentsData = $jira->getIssueComments($this->team, $issue);
                    $bodies = [];
                    foreach ($commentsData as $commentData) {
                        $bodies[] = $this->extractTextFromDocument($commentData['body'] ?? []);
                    }
                    $comments = IssueComment::collectJira($commentsData, $bodies);
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

                    $worklogsData = $jira->getWorklogs($this->team, $issue);
                    $descriptions = [];
                    foreach ($worklogsData as $worklogData) {
                        $descriptions[] = $this->extractTextFromDocument($worklogData['comment'] ?? []);
                    }
                    $worklogs = IssueWorklog::collectJira($worklogsData, $descriptions);
                    foreach ($worklogs as $worklog) {
                        $p = Participant::updateOrCreate(
                            [
                                'slug' => Str::slug($worklog->author),
                                'type' => 'person',
                            ],
                            [
                                'slug' => Str::slug($worklog->author),
                                'name' => $worklog->author,
                                'type' => 'person',
                            ],
                        );
                        DocumentWorklog::create([
                            'document_id' => $doc->id,
                            'author_id' => $p->id,
                            'description' => $worklog->description,
                            'logged_at' => $worklog->updatedAt,
                            'worklog_id' => $worklog->id,
                            'metadata' => $worklog,
                        ]);
                    }

                    $watchersData = $jira->getWatchers($this->team, $issue);
                    $watchers = collect($watchersData)->map(fn($watcher) => $watcher['displayName']);
                    foreach ($watchers as $watcher) {
                        $p = Participant::updateOrCreate(
                            [
                                'slug' => Str::slug($watcher),
                                'type' => 'person',
                            ],
                            [
                                'slug' => Str::slug($watcher),
                                'name' => $watcher,
                                'type' => 'person',
                            ],
                        );
                        $doc->participants()->attach($p->id, [
                            'context' => 'watcher',
                        ]);
                    }

                    $voters = $jira->getVoters($this->team, $issue);
                    foreach ($voters as $voter) {
                        $p = Participant::updateOrCreate(
                            [
                                'slug' => Str::slug($voter),
                                'type' => 'person',
                            ],
                            [
                                'slug' => Str::slug($voter),
                                'name' => $voter,
                                'type' => 'person',
                            ],
                        );
                        $doc->participants()->attach($p->id, [
                            'context' => 'voter',
                        ]);
                    }
                }
            }
        }
    }

    private function extractTextFromDocument(array $array): string
    {
        $textParts = [];
        foreach ($array as $key => $value) {
            if ($key === 'text' && is_string($value)) {
                $textParts[] = $value;
            } elseif (is_array($value)) {
                $nestedText = $this->extractTextFromDocument($value);
                if (!empty($nestedText)) {
                    $textParts[] = $nestedText;
                }
            }
        }
        return implode(' ', $textParts);
    }

    private function issueNeedsIndexing(Issue $issue): bool
    {
        $existingContent = Document::query()
            ->where('source_id', $issue->id)
            ->first();

        if ($existingContent === null) {
            return true;
        }

        return $issue->getLastUpdatedAt()->gt($existingContent->indexed_at ?? Carbon::parse('1900-01-01 00:00:00'));
    }
}
