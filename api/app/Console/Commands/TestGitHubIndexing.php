<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\Indexing\TextChunker;
use App\Services\Integration\CodeRepository\Github\DataTransferObjects\PullRequest;
use App\Services\Integration\CodeRepository\Github\DataTransferObjects\PullRequestComment;
use App\Services\Integration\CodeRepository\GitHub\GitHub;
use Illuminate\Console\Command;

class TestGitHubIndexing extends Command
{
    protected $signature = 'github:test-indexing {owner} {repo}';
    protected $description = 'Test indexing process without saving to database';

    public function handle(TextChunker $textChunker): int
    {
        $owner = $this->argument('owner');
        $repo = $this->argument('repo');

        $this->info("Testing indexing for {$owner}/{$repo}...");

        try {
            $github = new GitHub('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');

            $prs = $github->getPullRequests($owner, $repo, 3);

            if (empty($prs)) {
                $this->warn('No PRs found');
                return self::SUCCESS;
            }

            // Test first PR
            $prData = $prs[0];
            $pr = PullRequest::fromGitHub($prData);

            $this->newLine();
            $this->info("Testing PR #{$pr->number}: {$pr->title}");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

            // Test DTO conversion
            $this->info('✅ DTO conversion successful');
            $this->line("  • ID: {$pr->id}");
            $this->line("  • Author: {$pr->author}");
            $this->line("  • Assignee: " . ($pr->assignee ?? 'None'));
            $this->line("  • Reviewers: " . implode(', ', $pr->reviewers) ?: 'None');
            $this->line("  • Branch: {$pr->branchName} → {$pr->baseBranch}");
            $this->line("  • State: {$pr->state}");
            $this->line("  • Stats: +{$pr->additions} -{$pr->deletions} files:{$pr->changedFiles}");

            // Test description chunking
            if ($pr->description) {
                $chunks = $textChunker->chunk($pr->description);
                $this->newLine();
                $this->info('✅ Description chunked: ' . count($chunks) . ' chunks');
                $this->line("  • First chunk preview: " . substr($chunks->first(), 0, 100) . '...');
            }

            // Test comments
            $this->newLine();
            $this->info('Fetching comments...');
            $commentsData = $github->getPullRequestComments($owner, $repo, $pr->number);
            $comments = PullRequestComment::collectGitHub($commentsData);

            $this->info('✅ Found ' . $comments->count() . ' comments');

            if ($comments->count() > 0) {
                $firstComment = $comments->first();
                $this->line("  • First comment by: {$firstComment->author}");
                $this->line("  • Comment type: {$firstComment->type}");

                $commentChunks = $textChunker->chunk($firstComment->body);
                $this->line("  • Comment chunks: " . count($commentChunks));
            }

            // Test reviews
            $this->newLine();
            $this->info('Fetching reviews...');
            $reviewsData = $github->getPullRequestReviews($owner, $repo, $pr->number);
            $this->info('✅ Found ' . count($reviewsData) . ' reviews');

            if (count($reviewsData) > 0) {
                foreach ($reviewsData as $review) {
                    $reviewer = $review['user']['login'] ?? 'Unknown';
                    $state = $review['state'];
                    $this->line("  • {$reviewer}: {$state}");
                }
            }

            // Summary
            $this->newLine();
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('✅ Test completed successfully!');
            $this->newLine();
            $this->line('Would create:');
            $this->line("  • 1 Document record");
            $this->line("  • " . (isset($chunks) ? count($chunks) : 0) . " DocumentChunk records (from description)");
            $this->line("  • {$comments->count()} DocumentComment records");
            $this->line("  • " . (1 + ($pr->assignee ? 1 : 0) + count($pr->reviewers) + $comments->pluck('author')->unique()->count()) . " Participant records");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
