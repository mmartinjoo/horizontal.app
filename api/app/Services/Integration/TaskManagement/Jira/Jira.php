<?php

namespace App\Services\Integration\TaskManagement\Jira;

use App\Jobs\Indexing\TaskManagement\TaskManagement;
use App\Models\JiraIntegration;
use App\Services\Integration\TaskManagement\DataTransferObjects\Issue;
use App\Services\Integration\TaskManagement\DataTransferObjects\IssueComment;
use App\Services\Integration\TaskManagement\DataTransferObjects\Project;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

class Jira implements TaskManagement
{
    public function __construct(
        private JiraTokenManager $tokenManager
    ) {}

    /**
     * @return LazyCollection<Project>
     */
    public function projects(): LazyCollection
    {
        return LazyCollection::make(function () {
            $currentPage = 0;
            $perPage = 100;
            while (true) {
                $response = $this->makeRequest('/rest/api/3/project/search', [
                    'startAt' => $currentPage,
                    'maxResults' => $perPage,
                ]);

                if (!$response->successful()) {
                    throw new Exception('Failed to fetch Jira projects: ' . $response->body());
                }

                $data = $response->json();
                foreach ($data['values'] as $projectData) {
                    yield Project::fromJira($projectData);
                }

                if ($data['isLast']) {
                    break;
                }
                $currentPage++;
                usleep(50_000);
            }
        });
    }

    /**
     * @return LazyCollection<Issue>
     */
    public function issues(Project $project): LazyCollection
    {
        return LazyCollection::make(function () use ($project) {
            $fromDate = now()->subMonths(3)->format('Y-m-d');
            $toDate = now()->format('Y-m-d');
            $jql = "project={$project->id} and created>=\"$fromDate\" and created<=\"$toDate\" order by created desc";

            $queryParams = [
                'jql' => $jql,
                'maxResults' => 1_000,
                'fields' => 'summary,status,assignee,created,updated,description',
            ];

            $endpoint = '/rest/api/3/search/jql?' . http_build_query($queryParams);
            $response = $this->makeRequest($endpoint);

            if (!$response->successful()) {
                throw new Exception('Failed to fetch Jira issues: ' . $response->body());
            }

            $issues = $response->json('issues');
            foreach ($issues as $issue) {
                $description = Arr::get($issue, 'fields.description')
                    ? $this->extractTextFromDocument($issue['fields']['description'])
                    : '';

                yield Issue::fromJira($issue, $description);
            }
        });
    }

    public function comments(Issue $issue): LazyCollection
    {
        return LazyCollection::make(function () use ($issue) {
            $response = $this->makeRequest("/rest/api/3/issue/{$issue->id}/comment");

            if (!$response->successful()) {
                throw new Exception('Failed to fetch issue comments: ' . $response->body());
            }

            $comments = $response->json('comments');
            foreach ($comments as $comment) {
                yield IssueComment::fromJira($comment, $this->extractTextFromDocument($comment['body']));
            }
        });
    }

    public function getWorklogs(Issue $issue): array
    {
        $response = $this->makeRequest("/rest/api/3/issue/{$issue->id}/worklog");

        if (!$response->successful()) {
            throw new Exception('Failed to fetch worklogs: ' . $response->body());
        }

        return $response->json('worklogs');
    }

    public function getWatchers(Issue $issue): array
    {
        $response = $this->makeRequest("/rest/api/3/issue/{$issue->id}/watchers");

        if (!$response->successful()) {
            throw new Exception('Failed to fetch worklogs: ' . $response->body());
        }

        return $response->json('watchers');
    }

    public function getVoters(Issue $issue): array
    {
        $response = $this->makeRequest("/rest/api/3/issue/{$issue->id}/votes");

        if (!$response->successful()) {
            throw new Exception('Failed to fetch votes: ' . $response->body());
        }

        $voters = $response->json('voters');
        return collect($voters)->map(fn (array $voter) => $voter['displayName'])->toArray();
    }


    private function getValidIntegration(): JiraIntegration
    {
        $integration = JiraIntegration::first();

        if (!$integration) {
            throw new Exception('No Jira integration found');
        }

        // Ensure token is valid (refresh if needed)
        if (!$this->tokenManager->ensureValidToken($integration)) {
            throw new Exception('Unable to obtain valid Jira token');
        }

        return $integration->fresh(); // Reload in case token was refreshed
    }

    private function buildApiUrl(JiraIntegration $integration, string $endpoint): string
    {
        if (!$integration->cloud_id) {
            throw new Exception('Cloud ID is required for Jira API calls');
        }

        $endpoint = ltrim($endpoint, '/');

        return 'https://api.atlassian.com/ex/jira/' . $integration->cloud_id . '/' . $endpoint;
    }

    public function makeRequest(string $endpoint, array $data = []): Response
    {
        $integration = $this->getValidIntegration();

        $url = $this->buildApiUrl($integration, $endpoint);

        $response = Http::withToken($integration->access_token)
            ->acceptJson()
            ->throw()
            ->get($url);

        // If token is invalid, try to refresh and retry once
        if ($response->status() === 401) {
            Log::info('Jira API returned 401, attempting token refresh', [
                'integration_id' => $integration->id,
            ]);

            if ($this->tokenManager->refreshToken($integration)) {
                // Retry with refreshed token
                $integration->refresh();
                $response = Http::withToken($integration->access_token)
                    ->acceptJson()
                    ->get($url);
            }
        }

        return $response;
    }

    private function extractTextFromDocument(array $array): string
    {
        $textParts = [];
        foreach ($array as $key => $value) {
            if ($key === 'text' && is_string($value)) {
                $textParts[] = $value;
            } elseif (is_array($value)) {
                $nestedText = $this->extractTextFromDocument($value);
                if (! empty($nestedText)) {
                    $textParts[] = $nestedText;
                }
            }
        }

        return implode(' ', $textParts);
    }
}
