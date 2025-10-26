<?php

namespace App\Jobs\Indexing\TaskManagement;

use App\Services\Integration\TaskManagement\DataTransferObjects\Project;
use App\Services\Integration\TaskManagement\DataTransferObjects\Issue;
use App\Services\Integration\TaskManagement\DataTransferObjects\IssueComment;
use Illuminate\Support\LazyCollection;

interface TaskManagement
{
    /**
     * @return LazyCollection<Issue>
     */
    public function issues(Project $project): LazyCollection;
    /**
     * @return LazyCollection<IssueComment>
     */
    public function comments(Issue $issue): LazyCollection;
}