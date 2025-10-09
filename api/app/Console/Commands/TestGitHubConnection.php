<?php

namespace App\Console\Commands;

use App\Services\Integration\CodeRepository\GitHub\GitHub;
use Illuminate\Console\Command;

class TestGitHubConnection extends Command
{
    protected $signature = 'github:test-connection';
    protected $description = 'Test GitHub API connection';

    public function handle(): int
    {
        $this->info('Testing GitHub API connection...');

        try {
            $github = new GitHub("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");

            $this->info('Fetching repositories...');
            $repos = $github->getRepositories();

            $this->info('✅ Success! Found ' . count($repos) . ' repositories');

            // Show first 5 repos
            $this->table(
                ['Name', 'Owner', 'Private', 'Updated'],
                collect($repos)->take(5)->map(fn($repo) => [
                    $repo['name'],
                    $repo['owner']['login'],
                    $repo['private'] ? 'Yes' : 'No',
                    $repo['updated_at'],
                ])->toArray()
            );

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
