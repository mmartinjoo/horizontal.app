<?php

namespace App\Services\Integration\CodeRepository;

use App\Services\Integration\CodeRepository\DataTransferObjects\Comment;
use App\Services\Integration\CodeRepository\DataTransferObjects\Issue;
use App\Services\Integration\CodeRepository\DataTransferObjects\PullRequest;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use Illuminate\Support\LazyCollection;

interface CodeRepository
{
    /**
     * @return LazyCollection<PullRequest>
     */
    public function pullRequests(Repository $repository): LazyCollection;

    /**
     * @return LazyCollection<Issue>
     */
    public function issues(Repository $repository): LazyCollection;

    /**
     * @return LazyCollection<Comment>
     */
    public function pullRequestComments(PullRequest $pullRequest): LazyCollection;
}