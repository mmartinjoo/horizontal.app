<?php

namespace App\Jobs\Indexing\Communication\Slack;

use App\Jobs\Indexing\Communication\IndexChannel;
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
            $job = new IndexChannel(
                channel: $channel,
                slack: $slack,
                indexingWorkflowStepId: $indexingWorkflowStep->id,
            );
            dispatch($job);
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
