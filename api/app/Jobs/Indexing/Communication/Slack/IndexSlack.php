<?php

namespace App\Jobs\Indexing\Communication\Slack;

use App\Jobs\Indexing\Communication\IndexChannel;
use App\Jobs\Indexing\IndexingIntegration;
use App\Jobs\Indexing\IndexingStepJob;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepBucket;
use App\Models\SlackChannel;
use App\Services\Integration\Communication\DataTransferObjects\Channel;
use App\Services\Integration\Communication\Slack\Slack;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class IndexSlack extends IndexingStepJob implements ShouldQueue, IndexingIntegration
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('indexing');
    }

    public function handle(Slack $slack)
    {
        /** @var IndexingWorkflowStep $indexingWorkflowStep */
        $indexingWorkflowStep = IndexingWorkflowStep::findOrFail($this->indexingWorkflowStepId);
        $indexingWorkflowStep->update([
            'started_at' => now(),
            'job_id' => $this->job->payload()['uuid'],
        ]);

        $allChannels = $slack->channels();
        $channels = $this->getIndexableResources($allChannels);
        
        /** @var Channel $channel */
        foreach ($channels as $channel) {
            $bucket = IndexingWorkflowStepBucket::create([
                'indexing_workflow_step_id' => $indexingWorkflowStep->id,
                'title' => "channel_" . Str::lower($channel->name),
                'status' => 'starting',
            ]);
            
            $job = new IndexChannel(
                channel: $channel,
                vendor: 'slack',
                indexingWorkflowStepBucketId: $bucket->id,
            );
            dispatch($job);
        }
    }

    /**
     * @return LazyCollection<SlackChannel>
     */
    public function getAuthorizedResources(): LazyCollection
    {
        return SlackChannel::all()->lazy();
    }

    /**
     * @param LazyCollection<Channel> $resources
     * @return LazyCollection<Channel>
     */
    public function getIndexableResources(LazyCollection $resources): LazyCollection
    {
        $authorizedResources = $this->getAuthorizedResources();
        return LazyCollection::make(function () use ($resources, $authorizedResources) {
            /** @var Channel $resource */
            foreach ($resources as $resource) {
                if ($authorizedResources->contains('external_id', $resource->externalId)) {
                    yield $resource;
                }
            }
        });
    }
}
