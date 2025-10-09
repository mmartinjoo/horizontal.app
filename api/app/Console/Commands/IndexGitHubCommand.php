<?php

namespace App\Console\Commands;

use App\Jobs\Indexing\CodeRepository\GitHub\IndexGitHub;
use App\Models\Team;
use Illuminate\Console\Command;

class IndexGitHubCommand extends Command
{
    protected $signature = 'github:index {team_id}';
    protected $description = 'Index GitHub PRs for a team';

    public function handle(): int
    {
        $teamId = $this->argument('team_id');
        $team = Team::find($teamId);

        if (!$team) {
            $this->error("Team #{$teamId} not found");
            return self::FAILURE;
        }

        $this->info("Starting GitHub indexing for team: {$team->name}");

        // Dispatch the job
        IndexGitHub::dispatch($team);

        $this->info('✅ Indexing job dispatched!');
        $this->line('Monitor progress with: php artisan github:status');

        return self::SUCCESS;
    }
}
