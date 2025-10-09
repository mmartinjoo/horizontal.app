<?php

namespace App\Services\Integration\CodeRepository\GitHub;

use App\Models\Team;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GitHub
{
    public function __construct(
        private string $accessToken
    ) {}

    public function makeRequest(string $endpoint, array $params = []): Response
    {
        $url = $this->buildApiUrl($endpoint);

        $response = Http::withToken($this->accessToken)
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->get($url, $params);

        if (!$response->successful()) {
            Log::error('GitHub API request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return $response;
    }

    public function getRepositories(): array
    {
        $response = $this->makeRequest('/user/repos', [
            'per_page' => 100,
            'sort' => 'updated',
            'affiliation' => 'owner,collaborator,organization_member',
        ]);

        if (!$response->successful()) {
            throw new Exception('Failed to fetch GitHub repositories: ' . $response->body());
        }

        return $response->json();
    }

    public function getPullRequests(string $owner, string $repo, int $months = 3): array
    {
        $pullRequests = [];
        $page = 1;
        $perPage = 100;
        $fromDate = now()->subMonths($months);

        do {
            $response = $this->makeRequest("/repos/{$owner}/{$repo}/pulls", [
                'state' => 'all',
                'sort' => 'updated',
                'direction' => 'desc',
                'per_page' => $perPage,
                'page' => $page,
            ]);

            if (!$response->successful()) {
                throw new Exception('Failed to fetch pull requests: ' . $response->body());
            }

            $prs = $response->json();

            if (empty($prs)) {
                break;
            }

            foreach ($prs as $pr) {
                $updatedAt = Carbon::parse($pr['updated_at']);

                if ($updatedAt->lt($fromDate)) {
                    break 2;
                }

                $pullRequests[] = $pr;
            }

            $page++;
        } while (count($prs) === $perPage);

        return $pullRequests;
    }

    public function getPullRequestComments(string $owner, string $repo, int $prNumber): array
    {
        $allComments = [];

        // Get issue comments (PR conversation comments)
        $response = $this->makeRequest("/repos/{$owner}/{$repo}/issues/{$prNumber}/comments");

        if ($response->successful()) {
            $allComments = array_merge($allComments, $response->json());
        }

        // Get review comments (inline code review comments)
        $response = $this->makeRequest("/repos/{$owner}/{$repo}/pulls/{$prNumber}/comments");

        if ($response->successful()) {
            $reviewComments = $response->json();
            foreach ($reviewComments as &$comment) {
                $comment['type'] = 'review_comment';
            }
            $allComments = array_merge($allComments, $reviewComments);
        }

        return $allComments;
    }

    public function getPullRequestReviews(string $owner, string $repo, int $prNumber): array
    {
        $response = $this->makeRequest("/repos/{$owner}/{$repo}/pulls/{$prNumber}/reviews");

        if (!$response->successful()) {
            return [];
        }

        return $response->json();
    }

    private function buildApiUrl(string $endpoint): string
    {
        $endpoint = ltrim($endpoint, '/');
        return 'https://api.github.com/' . $endpoint;
    }
}
