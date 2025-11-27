<?php

namespace App\Services\Integration\CodeRepository\Github;

use App\Models\GithubIntegration;
use App\Services\Integration\CodeRepository\CodeRepository;
use App\Services\Integration\CodeRepository\DataTransferObjects\Comment;
use App\Services\Integration\CodeRepository\DataTransferObjects\Issue;
use App\Services\Integration\CodeRepository\DataTransferObjects\PullRequest;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use App\Services\Integration\CodeRepository\Github\GithubOAuth;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;

class Github implements CodeRepository
{
    private string $accessToken;

    public function __construct(
        private GithubOAuth $githubAuth,
        private string $baseUrl,
    ) {        
    }

    public function repositories(): LazyCollection
    {
        return LazyCollection::make(function () {
            $page = 1;
            while (true) {
                $res = $this->makeRequest('/installation/repositories', [
                    'per_page' => 100,
                    'page' => $page,
                    'sort' => 'updated',
                    'affiliation' => 'owner,collaborator,organization_member',
                ]);

                $repos = $res->json('repositories');
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
                $res = $this->makeRequest("/repos/{$repo->owner}/{$repo->name}/pulls", [
                    'state' => 'all',
                    'sort' => 'updated',
                    'direction' => 'desc',
                    'per_page' => 100,
                    'page' => $page,
                ]);

                $prs = $res->json();
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
                $res = $this->makeRequest("/repos/{$pullRequest->repository->owner}/{$pullRequest->repository->name}/issues/{$pullRequest->number}/comments", [
                    'per_page' => 100,
                    'page' => $page,
                ]);

                $comments = $res->json();
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

    public function issues(Repository $repository): LazyCollection
    {
        return LazyCollection::make(function () use ($repository) {
            $page = 1;
            while (true) {
                $res = $this->makeRequest("/repos/{$repository->owner}/{$repository->name}/issues", [
                    'page' => $page,
                    'per_page' => 100,
                ]);

                $issues = $res->json();
                if (empty($issues)) {
                    break;
                }

                foreach ($issues as $issue) {
                    // In GitHub API PRs are also issues
                    $pullRequest = Arr::get($issue, 'pull_request');
                    if ($pullRequest) {
                        continue;
                    }
                    yield Issue::fromGithub($issue);
                }

                if (++$page >= 100) {
                    break;
                }
                usleep(50_000);
            }
        });
    }

    private function makeRequest(string $endpoint, array $params = []): Response
    {
        $this->initAccessToken();
        return Http::withToken($this->accessToken)
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->throw()
            ->get(rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/'), $params);
    }

    private function initAccessToken()
    {
        $integration = GithubIntegration::first();
        if (!$integration) {
            throw new Exception('No valid GitHub integration found.');
        }
        $this->accessToken = $this->githubAuth->getInstallationToken($integration->installation_id);
    }
}
