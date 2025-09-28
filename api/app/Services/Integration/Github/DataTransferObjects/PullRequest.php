<?php

namespace App\Services\Integration\Github\DataTransferObjects;

class PullRequest
{
    public function __construct(
        public readonly int $id,
        public readonly int $number,
        public readonly string $title,
        public readonly ?string $body,
        public readonly string $htmlUrl,
        public readonly string $branch,
        public readonly ?array $assignee,
        public readonly array $reviewers,
        public readonly array $comments,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly string $repo,
    ) {}

    public static function fromGithubApi(array $pr, array $comments, string $repo): self
    {
        return new self(
            id: $pr['id'],
            number: $pr['number'],
            title: $pr['title'] ?? '',
            body: $pr['body'] ?? null,
            htmlUrl: $pr['html_url'] ?? '',
            branch: $pr['head']['ref'] ?? '',
            assignee: $pr['assignee'] ?? null,
            reviewers: array_map(fn($r) => $r['login'], $pr['requested_reviewers'] ?? []),
            comments: $comments,
            createdAt: $pr['created_at'],
            updatedAt: $pr['updated_at'],
            repo: $repo,
        );
    }
}
