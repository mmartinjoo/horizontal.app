<?php

namespace App\Console\Commands;

use App\Services\Integration\CodeRepository\GitHub\GitHub;
use Illuminate\Console\Command;

class TestGitHubPRs extends Command
{
    protected $signature = 'github:test-prs {owner} {repo}';
    protected $description = 'Test fetching PRs from a repository';

    public function handle(): int
    {
        $owner = $this->argument('owner');
        $repo = $this->argument('repo');

        $this->info("Fetching PRs from {$owner}/{$repo}...");

        try {
            $github = new GitHub('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');

            $prs = $github->getPullRequests($owner, $repo, 3);

            $this->info('✅ Found ' . count($prs) . ' PRs from last 3 months');

            if (empty($prs)) {
                $this->warn('No PRs found in the last 3 months');
                return self::SUCCESS;
            }

            // Show first 5 PRs
            $this->table(
                ['#', 'Title', 'State', 'Author', 'Updated'],
                collect($prs)->take(5)->map(fn($pr) => [
                    $pr['number'],
                    substr($pr['title'], 0, 50),
                    $pr['state'],
                    $pr['user']['login'],
                    $pr['updated_at'],
                ])->toArray()
            );

            // Test getting comments for first PR
            if (count($prs) > 0) {
                $firstPR = $prs[0];
                $this->newLine();
                $this->info("Testing comments for PR #{$firstPR['number']}...");

                $comments = $github->getPullRequestComments($owner, $repo, $firstPR['number']);
                $this->info('✅ Found ' . count($comments) . ' comments');

                $reviews = $github->getPullRequestReviews($owner, $repo, $firstPR['number']);
                $this->info('✅ Found ' . count($reviews) . ' reviews');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
