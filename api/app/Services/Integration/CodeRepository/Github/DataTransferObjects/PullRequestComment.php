<?php

namespace App\Services\Integration\CodeRepository\Github\DataTransferObjects;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class PullRequestComment
{
    public function __construct(
        public string $id,
        public string $author,
        public string $body,
        public Carbon $createdAt,
        public Carbon $updatedAt,
        public ?string $type = null,
        public ?string $path = null,
        public ?int $line = null,
        public array $metadata = [],
    ) {}

    public static function fromGitHub(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            author: $data['user']['login'] ?? 'Unknown',
            body: $data['body'] ?? '',
            createdAt: Carbon::parse($data['created_at']),
            updatedAt: Carbon::parse($data['updated_at']),
            type: $data['type'] ?? 'comment',
            path: $data['path'] ?? null,
            line: $data['line'] ?? $data['original_line'] ?? null,
            metadata: $data,
        );
    }

    public static function collectGitHub(array $comments): Collection
    {
        return collect($comments)->map(fn($comment) => self::fromGitHub($comment));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'author' => $this->author,
            'body' => $this->body,
            'created_at' => $this->createdAt->toIso8601String(),
            'updated_at' => $this->updatedAt->toIso8601String(),
            'type' => $this->type,
            'path' => $this->path,
            'line' => $this->line,
        ];
    }
}
