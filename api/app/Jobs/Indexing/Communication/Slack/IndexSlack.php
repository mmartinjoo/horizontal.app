<?php

namespace App\Jobs\Indexing\Communication\Slack;

use App\Jobs\Indexing\Communication\IndexMessage;
use App\Jobs\Indexing\Communication\IndexThread;
use App\Jobs\Indexing\IndexingStepJob;
use App\Models\Document;
use App\Models\IndexingWorkflowStep;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use App\Services\Integration\Communication\Slack\Slack;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexSlack extends IndexingStepJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
    }

    public function handle(Slack $slack)
    {
        /** @var IndexingWorkflowStep $indexingWorkflowStep */
        $indexingWorkflowStep = IndexingWorkflowStep::findOrFail($this->indexingWorkflowStepId);
        $indexingWorkflowStep->update([
            'started_at' => now(),
            'job_id' => $this->job->payload()['uuid'],
        ]);

        $channels = $slack->channels();
        foreach ($channels as $channel) {
            $messages = $slack->messages($channel);
            $indexingWorkflowStep->increment('overall_items', count($messages));
            foreach ($messages as $message) {
                if (!$this->messageNeedsIndexing($message)) {
                    $indexingWorkflowStep->increment('processed_items', 1);
                    $indexingWorkflowStep->increment('skipped_items', 1);
                    continue;
                }

                $job = new IndexMessage($message, 'slack');
                $job->setIndexingWorkflowStepId($this->indexingWorkflowStepId);
                dispatch($job);
            }

            // $threads = $slack->threads($channel);
            // $indexingWorkflowStep->increment('overall_items', count($threads));
            // foreach ($threads as $thread) { 
            //     if (!$this->messageNeedsIndexing($message)) {
            //         $indexingWorkflowStep->increment('processed_items', 1);
            //         $indexingWorkflowStep->increment('skipped_items', 1);
            //         continue;
            //     }    

            //     $job = new IndexThread($thread);
            //     $job->setIndexingWorkflowStepId($this->indexingWorkflowStepId);
            //     dispatch($job);
            // }
        }
    }

    private function messageNeedsIndexing(Message $message): bool
    {
        return !Document::query()
            ->where('source_type', 'slack')
            ->where('source_id', $message->externalId)
            ->exists();
    }
}
