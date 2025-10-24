<?php

namespace App\Services\Integration\CodeRepository\GitHub;

use App\Services\Integration\CodeRepository\DataTransferObjects\Comment;
use App\Services\Integration\CodeRepository\DataTransferObjects\PullRequest;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;

class GitHub
{
    public function __construct(
        private string $accessToken,
        private string $baseUrl,
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

                if (++$page >= 100) {
                    break;
                }
                usleep(50_000);
            }
        });
        
    }

    /**
     * @return LazyCollection<PullRequest>
     */
    public function pullRequests(Repository $repo, int $months = 3): LazyCollection
    {
        return LazyCollection::make(function () use ($repo, $months) {
            $page = 1;
            $fromDate = now()->subMonths($months);
            while (true) {
                $prs = $this->makeRequest("/repos/{$repo->owner}/{$repo->name}/pulls", [
                    'state' => 'all',
                    'sort' => 'updated',
                    'direction' => 'desc',
                    'per_page' => 100,
                    'page' => $page,
                ]);

                if (empty($prs)) {
                    break;
                }

                foreach ($prs as $pr) {
                    $pullRequest = PullRequest::fromGitHub($pr, $repo);
                    if ($pullRequest->updatedAt->lt($fromDate)) {
                        continue;
                    }
                    yield $pullRequest;
                }

                if (++$page >= 100) {
                    break;
                }
                usleep(50_000);
            }
        });
    }

    /**
     * @return LazyCollection<Comment>
     */
    public function pullRequestComments(PullRequest $pullRequest): LazyCollection
    {
        return LazyCollection::make(function () use ($pullRequest) {
            $page = 1;
            while (true) {                
                $comments = $this->makeRequest("/repos/{$pullRequest->repository->owner}/{$pullRequest->repository->name}/issues/{$pullRequest->number}/comments", [
                    'per_page' => 100,
                    'page' => $page,
                ]);

                if (empty($comments)) {
                    break;
                }

                foreach ($comments as $comment) {
                    yield Comment::fromGithub($comment);
                }

                if (++$page >= 100) {
                    break;
                }
                usleep(50_000);
            }
        });
    }

    private function makeRequest(string $endpoint, array $params = []): array
    {
        return Http::withToken($this->accessToken)
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->throw()
            ->get($this->baseUrl . '/' . $endpoint, $params)
            ->json();
    }
}
