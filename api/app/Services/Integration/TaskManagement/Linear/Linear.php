<?php

namespace App\Services\Integration\TaskManagement\Linear;

use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class Linear
{
    public function __construct()
    {
    }

    public function makeGraphQLRequest(string $query, array $variables = []): Response
    {
        $response = Http::withToken($this->getApiToken())
            ->acceptJson()
            ->post($this->getApiUrl(), [
                'query' => $query,
                'variables' => $variables,
            ]);

        if (!$response->successful()) {
            throw new Exception('Linear GraphQL request failed: ' . $response->body());
        }

        return $response;
    }

    public function getIssues(int $first = 50): array
    {
        $response = $this->makeGraphQLRequest($this->getIssuesQuery(), [
            'first' => $first,
        ]);

        $data = $response->json('data.issues.nodes');

        if (!$data) {
            throw new Exception('No issues data received from Linear API');
        }

        return $data;
    }

    public function getIssueComments(string $issueId, int $first = 50): array
    {
        $response = $this->makeGraphQLRequest($this->getIssueCommentsQuery(), [
            'issueId' => $issueId,
            'first' => $first,
        ]);

        $data = $response->json('data.issue.comments.nodes');

        if (!$data) {
            return [];
        }

        return $data;
    }

    public function getIssueWatchers(string $issueId, int $first = 50): array
    {
        $response = $this->makeGraphQLRequest($this->getIssueWatchersQuery(), [
            'issueId' => $issueId,
            'first' => $first,
        ]);

        $data = $response->json('data.issue.subscribers.nodes');

        if (!$data) {
            return [];
        }

        return $data;
    }

    public function extractTextFromDocument(string $documentJson): string
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

    private function getApiToken(): string
    {
        $token = config('services.linear.api_token');

        if (!$token) {
            throw new Exception('Linear API token not configured');
        }

        return $token;
    }

    private function getApiUrl(): string
    {
        return 'https://api.linear.app/graphql';
    }

    private function getIssuesQuery(): string
    {
        return '
            query GetIssues($first: Int) {
                issues(first: $first) {
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