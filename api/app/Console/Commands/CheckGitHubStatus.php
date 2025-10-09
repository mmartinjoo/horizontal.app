<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\IndexingWorkflow;
use Illuminate\Console\Command;

class CheckGitHubStatus extends Command
{
    protected $signature = 'github:status {team_id?}';
    protected $description = 'Check GitHub indexing status';

    public function handle(): int
    {
        $teamId = $this->argument('team_id');

        $query = IndexingWorkflow::where('integration', 'github');

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        $workflows = $query->latest()->take(5)->get();

        if ($workflows->isEmpty()) {
            $this->warn('No indexing workflows found');
            return self::SUCCESS;
        }

        $this->info('Recent GitHub Indexing Workflows:');
        $this->newLine();

        foreach ($workflows as $workflow) {
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("Workflow ID: {$workflow->id}");
            $this->line("Team: {$workflow->team?->name}");
            $this->line("Status: " . strtoupper($workflow->status));
            $this->line("Created: {$workflow->created_at->diffForHumans()}");
            $this->line("Stats:");
            $this->line("  • Total items: {$workflow->overall_items}");
            $this->line("  • Skipped: {$workflow->skipped_items}");
            $this->line("  • Deleted: {$workflow->deleted_items}");

            $indexed = $workflow->overall_items - $workflow->skipped_items;
            $this->line("  • Indexed: {$indexed}");

            if ($workflow->status === 'completed') {
                $this->info("  ✅ Completed");
            } elseif ($workflow->status === 'failed') {
                $this->error("  ❌ Failed");
            } else {
                $this->comment("  ⏳ In progress...");
            }

            $this->newLine();
        }

        // Show document stats
        if ($teamId) {
            $prCount = Document::where('team_id', $teamId)
                ->where('source_type', 'github_pr')
                ->count();

            $this->info("Total PRs indexed for team #{$teamId}: {$prCount}");
        }

        return self::SUCCESS;
    }
}
