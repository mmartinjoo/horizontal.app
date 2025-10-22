<?php

namespace App\Services\Integration\TaskManagement\Linear;

use App\Models\LinearIntegration;
use App\Services\Integration\TaskManagement\DataTransferObjects\Issue;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;

class Linear
{
    public function __construct(
        private LinearTokenManager $tokenManager
    ) {}

    private function getValidIntegration(): LinearIntegration
    {
        $integration = LinearIntegration::first();

        if (!$integration) {
            throw new Exception('No Linear integration found');
        }

        // Ensure token is valid (refresh if needed)
        if (!$this->tokenManager->ensureValidToken($integration)) {
            throw new Exception('Unable to obtain valid Linear token');
        }

        return $integration->fresh(); // Reload in case token was refreshed
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

        if (!$response->successful()) {
            throw new Exception('Linear GraphQL request failed: ' . $response->body());
        }

        return $response;
    }

    /**
     * @return LazyCollection<Issue>
     */
    public function issues(): LazyCollection
    {
        $after = null;
        $hasNextPage = true;
        return LazyCollection::make(function () use ($hasNextPage, $after) {
            while ($hasNextPage) {
                $result = $this->getIssuesPaginated(
                    limit: 250,
                    after: $after,
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
            }
        });
    }

    public function comments(Issue $issue, int $first = 50): array
    {
        $response = $this->makeGraphQLRequest($this->getIssueCommentsQuery(), [
            'issueId' => $issue->id,
            'first' => $first,
        ]);

        $data = $response->json('data.issue.comments.nodes');

        if (!$data) {
            return [];
        }

        return $data;
    }

    public function watchers(Issue $issue, int $first = 50): array
    {
        $response = $this->makeGraphQLRequest($this->getIssueWatchersQuery(), [
            'issueId' => $issue->id,
            'first' => $first,
        ]);

        $data = $response->json('data.issue.subscribers.nodes');

        if (!$data) {
            return [];
        }

        return $data;
    }

    private function extractTextFromDocument(string $documentJson): string
    {
        if (empty($documentJson)) {
            return '';
        }

        $document = json_decode($documentJson, true);

        if (!$document || !isset($document['content'])) {
            return $documentJson;
        }

        return $this->extractTextFromNodes($document['content']);
    }

    /**
     * @return array{'issues': array, 'pageInfo': array}
     */
    private function getIssuesPaginated(int $limit = 250, ?string $after = null): array
    {
        $variables = ['first' => $limit];
        if ($after) {
            $variables['after'] = $after;
        }

        $response = $this->makeGraphQLRequest($this->getIssuesQuery(), $variables);
        $data = $response->json('data.issues');
        if (!$data) {
            throw new Exception('No issues data received from Linear API');
        }

        return [
            'issues' => $data['nodes'],
            'pageInfo' => $data['pageInfo']
        ];
    }

    private function extractTextFromNodes(array $nodes): string
    {
        $textParts = [];

        foreach ($nodes as $node) {
            if (!is_array($node)) {
                continue;
            }

            if (isset($node['text'])) {
                $textParts[] = $node['text'];
            }

            if (isset($node['content']) && is_array($node['content'])) {
                $nestedText = $this->extractTextFromNodes($node['content']);
                if (!empty($nestedText)) {
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
            query GetIssues($first: Int, $after: String) {
                issues(first: $first, after: $after) {
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
}