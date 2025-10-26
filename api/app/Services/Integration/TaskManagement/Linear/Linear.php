<?php

namespace App\Services\Integration\TaskManagement\Linear;

use App\Jobs\Indexing\TaskManagement\TaskManagement;
use App\Models\LinearIntegration;
use App\Services\Integration\CodeRepository\DataTransferObjects\Comment;
use App\Services\Integration\TaskManagement\DataTransferObjects\Issue;
use App\Services\Integration\TaskManagement\DataTransferObjects\IssueComment;
use App\Services\Integration\TaskManagement\DataTransferObjects\Project;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;

class Linear implements TaskManagement
{
    public function __construct(
        private LinearTokenManager $tokenManager
    ) {}

    public function projects(): LazyCollection
    {
        return LazyCollection::make(function () {
            $after = null;
            $hasNextPage = true;
            while ($hasNextPage) {
                $result = $this->getProjectsPaginated(
                    limit: 100,
                    after: $after,
                );
                $hasNextPage = $result['pageInfo']['hasNextPage'];
                $after = $result['pageInfo']['endCursor'];

                foreach ($result['projects'] as $projectData) {
                    yield Project::fromLinear(
                        $projectData,
                    );
                }

                // 50ms delay to avoid rate limits
                usleep(50_000);
            }            
        });
    }

    /**
     * @return LazyCollection<Issue>
     */
    public function issues(Project $project): LazyCollection
    {
        $after = null;
        $hasNextPage = true;

        return LazyCollection::make(function () use ($project, $hasNextPage, $after) {
            while ($hasNextPage) {
                $result = $this->getIssuesPaginated(
                    limit: 100,
                    after: $after,
                    projectId: $project->id,
                );
                $hasNextPage = $result['pageInfo']['hasNextPage'];
                $after = $result['pageInfo']['endCursor'];

                foreach ($result['issues'] as $issueData) {
                    $transformedIssue = $this->transformIssueForDocument($issueData);
                    yield Issue::fromLinear(
                        $transformedIssue,
                        $transformedIssue['description']
                    );
                }

                // 50ms delay to avoid rate limits
                usleep(50_000);
            }
        });
    }

    /**
     * @return LazyCollection<IssueComment>
     */
    public function comments(Issue $issue): LazyCollection
    {
        return LazyCollection::make(function () use ($issue) {
            $after = null;
            $hasNextPage = true;
            while ($hasNextPage) {
                $result = $this->getCommentsPaginated(
                    limit: 100,
                    after: $after,
                    issueId: $issue->id,
                );
                $hasNextPage = $result['pageInfo']['hasNextPage'];
                $after = $result['pageInfo']['endCursor'];

                foreach ($result['comments'] as $commentData) {
                    yield IssueComment::fromLinear(
                        $commentData,
                    );
                }

                // 50ms delay to avoid rate limits
                usleep(50_000);
            }            
        });
    }

    public function watchers(Issue $issue, int $first = 50): array
    {
        $response = $this->makeGraphQLRequest($this->getIssueWatchersQuery(), [
            'issueId' => $issue->id,
            'first' => $first,
        ]);

        $data = $response->json('data.issue.subscribers.nodes');

        if (! $data) {
            return [];
        }

        return $data;
    }

    public function makeGraphQLRequest(string $query, array $variables = []): Response
    {
        $integration = $this->getValidIntegration();

        $response = Http::withToken($integration->access_token)
            ->acceptJson()
            ->post($this->getApiUrl(), [
                'query' => $query,
                'variables' => $variables,
            ]);

        // If token is invalid, try to refresh and retry once
        if ($response->status() === 401) {
            if ($this->tokenManager->refreshToken($integration)) {
                // Retry with refreshed token
                $integration->refresh();
                $response = Http::withToken($integration->access_token)
                    ->acceptJson()
                    ->post($this->getApiUrl(), [
                        'query' => $query,
                        'variables' => $variables,
                    ]);
            }
        }

        if (! $response->successful()) {
            throw new Exception('Linear GraphQL request failed: '.$response->body());
        }

        return $response;
    }

    private function extractTextFromDocument(string $documentJson): string
    {
        if (empty($documentJson)) {
            return '';
        }

        $document = json_decode($documentJson, true);

        if (! $document || ! isset($document['content'])) {
            return $documentJson;
        }

        return $this->extractTextFromNodes($document['content']);
    }

    /**
     * @return array{'issues': array, 'pageInfo': array}
     */
    private function getIssuesPaginated(int $limit = 250, ?string $after = null, ?string $projectId = null): array
    {
        $variables = ['first' => $limit];
        if ($after) {
            $variables['after'] = $after;
        }
        if ($projectId) {
            $variables['projectId'] = $projectId;
        }

        $response = $this->makeGraphQLRequest($this->getIssuesQuery(), $variables);
        $data = $response->json('data.issues');
        if (! $data) {
            throw new Exception('No issues data received from Linear API');
        }

        return [
            'issues' => $data['nodes'],
            'pageInfo' => $data['pageInfo'],
        ];
    }

    /**
     * @return array{'projects': array, 'pageInfo': array}
     */
    private function getProjectsPaginated(int $limit = 100, ?string $after = null): array
    {
        $variables = ['first' => $limit];
        if ($after) {
            $variables['after'] = $after;
        }

        $response = $this->makeGraphQLRequest($this->getProjectsQuery(), $variables);
        $data = $response->json('data.projects');
        if (!$data) {
            throw new Exception('No project data received from Linear API');
        }

        return [
            'projects' => $data['nodes'],
            'pageInfo' => $data['pageInfo'],
        ];
    }

    /**
     * @return array{'comments': array, 'pageInfo': array}
     */
    private function getCommentsPaginated($issueId, int $limit = 100, ?string $after = null): array
    {
        $variables = ['first' => $limit];
        if ($after) {
            $variables['after'] = $after;
        }
        $variables['issueId'] = $issueId;

        $response = $this->makeGraphQLRequest($this->getIssueCommentsQuery(), $variables);
        $data = $response->json('data.issue.comments');
        if (!$data) {
            throw new Exception('No comment data received from Linear API');
        }

        return [
            'comments' => $data['nodes'],
            'pageInfo' => $data['pageInfo'],
        ];
    }

    private function extractTextFromNodes(array $nodes): string
    {
        $textParts = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            if (isset($node['text'])) {
                $textParts[] = $node['text'];
            }

            if (isset($node['content']) && is_array($node['content'])) {
                $nestedText = $this->extractTextFromNodes($node['content']);
                if (! empty($nestedText)) {
                    $textParts[] = $nestedText;
                }
            }
        }

        return implode(' ', $textParts);
    }

    public function transformIssueForDocument(array $issueData): array
    {
        $description = '';
        if (isset($issueData['description'])) {
            $description = $this->extractTextFromDocument($issueData['description']);
        }

        return [
            'linear_id' => $issueData['id'],
            'identifier' => $issueData['identifier'],
            'title' => $issueData['title'],
            'description' => $description,
            'state' => $issueData['state']['name'] ?? '',
            'assignee' => $issueData['assignee']['displayName'] ?? '',
            'assignee_email' => $issueData['assignee']['email'] ?? '',
            'created_at' => $issueData['createdAt'],
            'updated_at' => $issueData['updatedAt'],
            'url' => $issueData['url'],
            'raw_data' => $issueData,
        ];
    }

    public function transformCommentForDocument(array $commentData): array
    {
        $body = '';
        if (isset($commentData['body'])) {
            $body = $this->extractTextFromDocument($commentData['body']);
        }

        return [
            'linear_id' => $commentData['id'],
            'body' => $body,
            'author' => $commentData['user']['displayName'] ?? '',
            'author_email' => $commentData['user']['email'] ?? '',
            'created_at' => $commentData['createdAt'],
            'updated_at' => $commentData['updatedAt'],
            'raw_data' => $commentData,
        ];
    }

    public function transformUserForParticipant(array $userData): array
    {
        return [
            'linear_id' => $userData['id'],
            'name' => $userData['displayName'] ?? '',
            'email' => $userData['email'] ?? '',
            'raw_data' => $userData,
        ];
    }

    private function getApiUrl(): string
    {
        return 'https://api.linear.app/graphql';
    }

    private function getIssuesQuery(): string
    {
        return '
            query GetIssues($first: Int, $after: String, $projectId: ID) {
                issues(first: $first, after: $after, filter: { project: { id: { eq: $projectId } } }) {
                    nodes {
                        id
                        identifier
                        title
                        description
                        state {
                            name
                        }
                        assignee {
                            id
                            displayName
                            email
                        }
                        createdAt
                        updatedAt
                        url
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ';
    }

    private function getIssueCommentsQuery(): string
    {
        return '
            query GetIssueComments($issueId: String!, $first: Int) {
                issue(id: $issueId) {
                    comments(first: $first) {
                        nodes {
                            id
                            body
                            createdAt
                            updatedAt
                            user {
                                id
                                displayName
                                email
                            }
                        }
                        pageInfo {
                            hasNextPage
                            endCursor
                        }
                    }
                }
            }
        ';
    }

    private function getIssueWatchersQuery(): string
    {
        return '
            query GetIssueWatchers($issueId: String!, $first: Int) {
                issue(id: $issueId) {
                    subscribers(first: $first) {
                        nodes {
                            id
                            displayName
                            email
                        }
                        pageInfo {
                            hasNextPage
                            endCursor
                        }
                    }
                }
            }
        ';
    }

    private function getProjectsQuery(): string
    {
        return '
            query GetProjects($first: Int) {
                projects(first: $first) {
                    nodes {
                        id
                        name
                        description
                        state
                        startDate
                        targetDate
                        completedAt
                        createdAt
                        updatedAt
                        url
                        lead {
                            id
                            displayName
                            email
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                }
            }
        ';
    }

    private function getValidIntegration(): LinearIntegration
    {
        $integration = LinearIntegration::first();

        if (! $integration) {
            throw new Exception('No Linear integration found');
        }

        // Ensure token is valid (refresh if needed)
        if (! $this->tokenManager->ensureValidToken($integration)) {
            throw new Exception('Unable to obtain valid Linear token');
        }

        return $integration->fresh(); // Reload in case token was refreshed
    }
}
