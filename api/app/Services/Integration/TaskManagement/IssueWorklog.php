<?php

namespace App\Services\Integration\TaskManagement;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class IssueWorklog
{
    public function __construct(
        public string $id,
        public string $author,
        public string $description,
        public Carbon $updatedAt,
    ) {
    }

    /**
     * @return Collection<IssueWorklog>
     */
    public static function collectJira(array $jiraWorklogs, array $parsedDescriptions): Collection
    {
        $worklogs = collect();
        foreach ($jiraWorklogs as $i => $worklog) {
            $worklogs[] = new static(
                id: $worklog['id'],
                author: $worklog['author']['displayName'],
                description: $parsedDescriptions[$i] ?? '',
                updatedAt: Carbon::parse($worklog['updated']),
            );
        }
        return $worklogs;
    }
}
