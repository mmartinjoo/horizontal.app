<?php

namespace App\Jobs\Indexing\CodeRepository;

use App\Models\Document;
use App\Models\IndexingWorkflow;
use App\Services\Integration\CodeRepository\CodeRepository;
use App\Services\Integration\CodeRepository\DataTransferObjects\Issue;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexIssues implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Repository $repository,
        private CodeRepository $connector,
        private int $indexingWorkflowId,
    ) {
    }

    public function handle()
    {
        $indexingWorkflow = IndexingWorkflow::findOrFail($this->indexingWorkflowId);
        $issues = $this->connector->issues($this->repository);

        $indexingWorkflow->increment('overall_items', count($issues));

        /** @var Issue $issue */
        foreach ($issues as $issue) {
            if (!$this->issueNeedsIndexing($issue)) {
                $indexingWorkflow->increment('skipped_items', 1);                
                continue;
            }

            // Delete existing document if it exists
            $count = Document::query()
                ->where('source_type', 'github_issue')
                ->where('source_id', $issue->externalId)
                ->delete();

            $indexingWorkflow->increment('deleted_items', $count);
            IndexIssue::dispatch($issue, $indexingWorkflow->id);
        }
    }

    /**
     * No need to keep issues updated. We just index the title and body.
     */
    private function issueNeedsIndexing(Issue $issue): bool
    {
        return !Document::query()
            ->where('source_id', $issue->externalId)
            ->where('source_type', 'github_issue')
            ->exists();
    }
}