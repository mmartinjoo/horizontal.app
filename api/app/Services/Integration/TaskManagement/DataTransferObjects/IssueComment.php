<?php

namespace App\Services\Integration\TaskManagement\DataTransferObjects;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class IssueComment
{
    public function __construct(
        public string $id,
        public string $body,
        public string $author,
        public Carbon $createdAt,
    ) {
    }

    /**
     * @return Collection<IssueComment>
     */
    public static function collectJira(array $jiraComments, array $parsedBodies): Collection
    {
        $comments = collect();
        foreach ($jiraComments as $i => $comment) {
            $comments[] = new static(
                id: $comment['id'],
                body: $parsedBodies[$i] ?? '',
                author: $comment['author']['displayName'],
                createdAt: Carbon::parse($comment['created']),
            );
        }
        return $comments;
    }

    /**
     * @return Collection<IssueComment>
     */
    public static function collectLinear(array $linearComments): Collection
    {
        $comments = collect();
        foreach ($linearComments as $comment) {
            $comments[] = new static(
                id: $comment['id'],
                body: $comment['body'] ?? '',
                author: $comment['user']['displayName'] ?? '',
                createdAt: Carbon::parse($comment['createdAt']),
            );
        }
        return $comments;
    }
}
