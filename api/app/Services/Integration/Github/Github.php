<?php

namespace App\Services\Integration\Github;

use App\Models\Document;
use App\Models\DocumentComment;
use App\Models\DocumentParticipant;
use App\Models\Participant;
use App\Models\Team;
use App\Services\Integration\Github\DataTransferObjects\PullRequest;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Github
{
    public function __construct(
        private string $token
    ) {}

    /**
     * Make an authenticated request to the Github API.
     */
    public function makeRequest(string $endpoint, array $params = []): Response
    {
        $url = "https://api.github.com" . $endpoint;
        $response = Http::withToken($this->token)
            ->acceptJson()
            ->throw()
            ->get($url, $params);
        return $response;
    }

    /**
     * Fetch PRs from a repository in the last 3 months as DTOs.
     */
    public function getRecentPRDTOs(string $repo): array
    {
        $since = Carbon::now()->subMonths(3)->toIso8601String();
        $endpoint = "/repos/{$repo}/pulls";
        $prs = $this->makeRequest($endpoint, [
            'state' => 'all',
            'sort' => 'updated',
            'direction' => 'desc',
            'per_page' => 100
        ])->json();
        $filtered = array_filter($prs, fn($pr) => $pr['created_at'] >= $since);
        $dtos = [];
        foreach ($filtered as $pr) {
            $comments = $this->getPRComments($repo, $pr['number']);
            $dtos[] = PullRequest::fromGithubApi($pr, $comments, $repo);
        }
        return $dtos;
    }

    /**
     * Index a single PR DTO and its related data into the database.
     */
    public function indexPRDto(PullRequest $pr, Team $team): ?Document
    {
        // 1. Insert or update the PR as a Document
        $document = Document::updateOrCreate([
            'source_type' => 'github_pr',
            'source_id' => (string)$pr->id,
            'team_id' => $team->id,
        ], [
            'title' => $pr->title,
            'preview' => $pr->body ?? '',
            'source_url' => $pr->htmlUrl,
            'metadata' => [
                'branch' => $pr->branch,
                'assignee' => $pr->assignee['login'] ?? null,
                'reviewers' => $pr->reviewers,
            ],
            'indexed_at' => now(),
        ]);

        // 2. Index assignee and reviewers as participants
        $participants = [];
        if (!empty($pr->assignee)) {
            $participants[] = $this->findOrCreateParticipant($pr->assignee);
        }
        foreach ($pr->reviewers as $reviewerLogin) {
            $participants[] = $this->findOrCreateParticipant(['login' => $reviewerLogin]);
        }
        foreach ($participants as $participant) {
            DocumentParticipant::updateOrCreate([
                'entity_type' => Document::class,
                'entity_id' => $document->id,
                'participant_id' => $participant->id,
                'context' => 'github_pr',
            ]);
        }

        // 3. Index comments
        foreach ($pr->comments as $comment) {
            $author = $this->findOrCreateParticipant($comment['user']);
            $docComment = DocumentComment::updateOrCreate([
                'document_id' => $document->id,
                'comment_id' => (string)$comment['id'],
            ], [
                'body' => $comment['body'] ?? '',
                'author_id' => $author->id,
                'commented_at' => Carbon::parse($comment['created_at']),
                'metadata' => [
                    'url' => $comment['html_url'] ?? null,
                ],
            ]);
            // Link comment author as participant
            DocumentParticipant::updateOrCreate([
                'entity_type' => DocumentComment::class,
                'entity_id' => $docComment->id,
                'participant_id' => $author->id,
                'context' => 'github_pr_comment',
            ]);
        }

        return $document;
    }

    /**
     * Find or create a participant from a Github user array.
     */
    public function findOrCreateParticipant(array $user): Participant
    {
        return Participant::firstOrCreate([
            'slug' => $user['login'],
        ], [
            'name' => $user['login'],
            'type' => 'person',
        ]);
    }
}
