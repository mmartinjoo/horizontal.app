<?php

namespace App\Services\Integration\CodeRepository\GitHub;

use App\Services\Integration\CodeRepository\DataTransferObject\Repository;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Illuminate\Support\LazyCollection;

class GitHub
{
    public function __construct(
        private string $accessToken
    ) {}

    public function repositories(): LazyCollection
    {
        return LazyCollection::make(function () {
            $page = 1;
            while (true) {
                $repos = $this->makeRequest('/user/repos', [
                    'per_page' => 100,
                    'page' => $page,
                    'sort' => 'updated',
                    'affiliation' => 'owner,collaborator,organization_member',
                ]);

                if (empty($repos)) {
                    break;
                }

                foreach ($repos as $repo) {
                    yield Repository::fromGitHub($repo);
                }

                $page++;
                usleep(50_000);
            }
        });
        
    }

    public function getPullRequests(string $owner, string $repo, int $months = 3): array
    {
        $pullRequests = [];
        $page = 1;
        $perPage = 100;
        $fromDate = now()->subMonths($months);

        do {
            $prs = $this->makeRequest("/repos/{$owner}/{$repo}/pulls", [
                'state' => 'all',
                'sort' => 'updated',
                'direction' => 'desc',
                'per_page' => $perPage,
                'page' => $page,
            ]);

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
        $issueComments = $this->makeRequest("/repos/{$owner}/{$repo}/issues/{$prNumber}/comments");
        $allComments = array_merge($allComments, $issueComments);

        // Get review comments (inline code review comments)
        $reviewComments = $this->makeRequest("/repos/{$owner}/{$repo}/pulls/{$prNumber}/comments");
        foreach ($reviewComments as &$comment) {
            $comment['type'] = 'review_comment';
        }
        $allComments = array_merge($allComments, $reviewComments);

        return $allComments;
    }

    public function getPullRequestReviews(string $owner, string $repo, int $prNumber): array
    {
        return $this->makeRequest("/repos/{$owner}/{$repo}/pulls/{$prNumber}/reviews");
    }

    private function buildApiUrl(string $endpoint): string
    {
        $endpoint = ltrim($endpoint, '/');
        return 'https://api.github.com/' . $endpoint;
    }

    private function makeRequest(string $endpoint, array $params = []): array
    {
        return Http::withToken($this->accessToken)
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->throw()
            ->get($this->buildApiUrl($endpoint), $params)
            ->json();
    }
}
