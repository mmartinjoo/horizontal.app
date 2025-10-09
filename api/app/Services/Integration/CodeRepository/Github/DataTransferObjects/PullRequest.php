<?php

namespace App\Services\Integration\CodeRepository\Github\DataTransferObjects;

use Carbon\Carbon;

class PullRequest
{
    public function __construct(
        public string $id,
        public int $number,
        public string $title,
        public ?string $description,
        public string $state,
        public string $url,
        public ?string $assignee,
        public array $reviewers,
        public string $author,
        public string $branchName,
        public ?string $baseBranch,
        public Carbon $createdAt,
        public Carbon $updatedAt,
        public ?Carbon $mergedAt,
        public ?Carbon $closedAt,
        public bool $draft,
        public bool $merged,
        public int $additions,
        public int $deletions,
        public int $changedFiles,
        public array $labels,
        public array $metadata = [],
    ) {}

    public static function fromGitHub(array $data): self
    {
        $reviewers = [];
        if (isset($data['requested_reviewers'])) {
            foreach ($data['requested_reviewers'] as $reviewer) {
                $reviewers[] = $reviewer['login'] ?? 'Unknown';
            }
        }

        $labels = [];
        if (isset($data['labels'])) {
            foreach ($data['labels'] as $label) {
                $labels[] = $label['name'];
            }
        }

        return new self(
            id: (string) $data['id'],
            number: $data['number'],
            title: $data['title'],
            description: $data['body'] ?? null,
            state: $data['state'],
            url: $data['html_url'],
            assignee: $data['assignee']['login'] ?? null,
            reviewers: $reviewers,
            author: $data['user']['login'] ?? 'Unknown',
            branchName: $data['head']['ref'] ?? 'unknown',
            baseBranch: $data['base']['ref'] ?? null,
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at']),
            mergedAt: isset($data['merged_at']) ? Carbon::parse($data['merged_at']) : null,
            closedAt: isset($data['closed_at']) ? Carbon::parse($data['closed_at']) : null,
            draft: $data['draft'] ?? false,
            merged: $data['merged'] ?? false,
            additions: $data['additions'] ?? 0,
            deletions: $data['deletions'] ?? 0,
            changedFiles: $data['changed_files'] ?? 0,
            labels: $labels,
            metadata: $data,
        );
    }

    public function getLastUpdatedAt(): Carbon
    {
        $dates = array_filter([
            $this->updatedAt,
            $this->mergedAt,
            $this->closedAt,
        ]);

        return collect($dates)->max() ?? $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'description' => $this->description,
            'state' => $this->state,
            'url' => $this->url,
            'assignee' => $this->assignee,
            'reviewers' => $this->reviewers,
            'author' => $this->author,
            'branch_name' => $this->branchName,
            'base_branch' => $this->baseBranch,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
            'merged_at' => $this->mergedAt?->toIso8601String(),
            'closed_at' => $this->closedAt?->toIso8601String(),
            'draft' => $this->draft,
            'merged' => $this->merged,
            'additions' => $this->additions,
            'deletions' => $this->deletions,
            'changed_files' => $this->changedFiles,
            'labels' => $this->labels,
        ];
    }
}
