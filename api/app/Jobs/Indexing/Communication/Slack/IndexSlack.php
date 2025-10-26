<?php

namespace App\Jobs\Indexing\Communication\Slack;

use App\Jobs\Indexing\Communication\IndexChannel;
use App\Jobs\Indexing\IndexingStepJob;
use App\Models\Document;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use App\Services\Integration\Communication\DataTransferObjects\Channel;
use App\Services\Integration\Communication\Slack\Slack;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

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
        /** @var Channel $channel */
        foreach ($channels as $channel) {
            $messages = $slack->messages($channel);
            $bucket = IndexingWorkflowStepBucket::create([
                'indexing_workflow_step_id' => $indexingWorkflowStep->id,
                'title' => "channel_" . Str::lower($channel->name),
                'overall_items' => count($messages),
                'status' => 'starting',
            ]);
            $indexingWorkflowStep->increment('overall_items');
            
            $job = new IndexChannel(
                channel: $channel,
                slack: $slack,
                indexingWorkflowStepBucketId: $bucket->id,
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
