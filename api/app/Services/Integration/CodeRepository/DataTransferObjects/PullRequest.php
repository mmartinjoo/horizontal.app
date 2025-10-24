<?php

namespace App\Services\Integration\CodeRepository\DataTransferObjects;

use Carbon\Carbon;
use Illuminate\Support\Arr;

class PullRequest
{
    public function __construct(
        public string $id,
        public int $number,
        public string $title,
        public ?string $description,
        public string $url,
        public ?string $assignee,
        public array $reviewers,
        public string $author,
        public Repository $repository,
        public Carbon $createdAt,
        public Carbon $updatedAt,
    ) {
    }

    public static function fromGitHub(array $data, Repository $repo): self
    {
        $reviewers = [];
        foreach (Arr::get($data, 'requested_reviewers', []) as $reviewer) {
            if (!$reviewer['login']) {
                continue;
            }
            $reviewers[] = $reviewer['login'];
        }

        return new self(
            id: (string) $data['id'],
            number: $data['number'],
            title: $data['title'],
            description: Arr::get($data, 'body'),
            url: $data['html_url'],
            assignee: Arr::get($data, 'assignee.login'),
            reviewers: $reviewers,
            author: Arr::get($data, 'user.login'),
            repository: $repo,
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at']),
        );
    }
}