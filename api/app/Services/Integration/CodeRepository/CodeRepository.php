<?php

namespace App\Services\Integration\CodeRepository;

use App\Services\Integration\CodeRepository\DataTransferObjects\Comment;
use App\Services\Integration\CodeRepository\DataTransferObjects\PullRequest;
use Illuminate\Support\LazyCollection;

interface CodeRepository
{
    /**
     * @return LazyCollection<Comment>
     */
    public function pullRequestComments(PullRequest $pullRequest): LazyCollection;
}