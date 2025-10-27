<?php

namespace App\Jobs\Indexing\Communication\GoogleChat;

use App\Jobs\Indexing\Communication\IndexChannel;
use App\Jobs\Indexing\IndexingStepJob;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\Communication\DataTransferObjects\Channel;
use App\Services\Integration\Communication\GoogleChat\GoogleChat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class IndexGoogleChat extends IndexingStepJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
    }

    public function handle(GoogleChat $googleChat)
    {
        /** @var IndexingWorkflowStep $indexingWorkflowStep */
        $indexingWorkflowStep = IndexingWorkflowStep::findOrFail($this->indexingWorkflowStepId);
        $indexingWorkflowStep->update([
            'started_at' => now(),
            'job_id' => $this->job->payload()['uuid'],
        ]);
        
        $channels = $googleChat->channels();

        /** @var Channel $channel */
        foreach ($channels as $channel) {
            $bucket = IndexingWorkflowStepBucket::create([
                'indexing_workflow_step_id' => $indexingWorkflowStep->id,
                'title' => "channel_" . Str::lower($channel->name),
                'status' => 'starting',
            ]);

            $job = new IndexChannel(
                channel: $channel,
                vendor: 'google_chat',
                indexingWorkflowStepBucketId: $bucket->id,
            );
            dispatch($job);
        }
    }
}
