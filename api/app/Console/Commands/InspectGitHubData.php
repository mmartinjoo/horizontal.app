<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\DocumentComment;
use Illuminate\Console\Command;

class InspectGitHubData extends Command
{
    protected $signature = 'github:inspect {team_id} {--pr-number=}';
    protected $description = 'Inspect indexed GitHub data';

    public function handle(): int
    {
        $teamId = $this->argument('team_id');
        $prNumber = $this->option('pr-number');

        $query = Document::where('team_id', $teamId)
            ->where('source_type', 'github_pr')
            ->with(['participants', 'comments', 'chunks']);

        if ($prNumber) {
            $query->whereJsonContains('metadata->number', (int)$prNumber);
        }

        $documents = $query->latest('indexed_at')->take(5)->get();

        if ($documents->isEmpty()) {
            $this->warn('No PRs found');
            return self::SUCCESS;
        }

        foreach ($documents as $doc) {
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("PR #{$doc->metadata['number']}: {$doc->title}");
            $this->line("URL: {$doc->source_url}");
            $this->line("Priority: {$doc->priority}");
            $this->line("Indexed: {$doc->indexed_at?->diffForHumans()}");
            $this->newLine();

            $this->line("Participants:");
            foreach ($doc->participants as $participant) {
                $context = $participant->pivot->context;
                $this->line("  • {$participant->name} ({$context})");
            }

            $this->newLine();
            $this->line("Comments: {$doc->comments->count()}");
            if ($doc->comments->count() > 0) {
                $comment = $doc->comments->first();
                $this->line("  • First by: {$comment->author->name}");
                $this->line("  • Preview: " . substr($comment->body, 0, 100) . '...');
            }

            $this->newLine();
            $this->line("Chunks: {$doc->chunks->count()}");
            if ($doc->chunks->count() > 0) {
                $this->line("  • First chunk: " . substr($doc->chunks->first()->body, 0, 100) . '...');
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
