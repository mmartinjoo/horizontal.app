<?php

namespace App\Jobs\Indexing\Communication;

use App\Models\Document;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\Communication\DataTransferObjects\Channel;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use App\Services\Integration\Communication\Slack\Slack;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class IndexChannel implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Channel $channel,
        private Slack $slack,
        private int $indexingWorkflowStepBucketId,
    ) {}

    public function handle()
    {
        $indexingWorkflowStepBucket = IndexingWorkflowStepBucket::findOrFail($this->indexingWorkflowStepBucketId);

        $messages = $this->slack->messages($this->channel);
        $threads = $this->slack->threads($this->channel);

        $indexingWorkflowStepBucket->increment(
            'overall_items', 
            count($messages)+count($threads)
        );

        foreach ($messages as $message) {
            if (!$this->messageNeedsIndexing($message)) {
                $indexingWorkflowStepBucket->increment('processed_items', 1);
                $indexingWorkflowStepBucket->increment('skipped_items', 1);
                continue;
            }

            $job = new IndexMessage($message, 'slack');
            $job->setIndexingWorkflowStepBucketId($this->indexingWorkflowStepBucketId);
            dispatch($job);
        }
        
        foreach ($threads as $thread) {
            if (!$this->messageNeedsIndexing($thread)) {
                $indexingWorkflowStepBucket->increment('processed_items', 1);
                $indexingWorkflowStepBucket->increment('skipped_items', 1);
                continue;
            }    
            
            $job = new IndexThread($thread);
            $job->setIndexingWorkflowStepBucketId($this->indexingWorkflowStepBucketId);
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