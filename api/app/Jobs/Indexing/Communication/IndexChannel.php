<?php

namespace App\Jobs\Indexing\Communication;

use App\Enums\Indexing\WorkflowStatus;
use App\Models\Document;
use App\Models\IndexingWorkflowStepBucket;
use App\Services\Integration\Communication\DataTransferObjects\Channel;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use App\Services\Integration\Factory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class IndexChannel implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Channel $channel,
        private string $vendor,
        private int $indexingWorkflowStepBucketId,
    ) {
        $this->onQueue('indexing');
    }

    public function handle(Factory $factory)
    {
        $bucket = IndexingWorkflowStepBucket::findOrFail($this->indexingWorkflowStepBucketId);

        $integration = $factory->createCommunication($this->vendor);
        $messages = $integration->messages($this->channel);
        $threads = $integration->threads($this->channel);
        $jobs = [];

        foreach ($messages as $message) {
            if (!$this->messageNeedsIndexing($message)) {
                continue;
            }

            $bucket->increment('overall_items');

            $job = new IndexMessage($message, $this->vendor);
            $job->setIndexingWorkflowStepBucketId($this->indexingWorkflowStepBucketId);            
            $jobs[] = $job;
        }
        
        foreach ($threads as $thread) {
            if (!$this->messageNeedsIndexing($thread)) {
                continue;
            }    

            $bucket->increment('overall_items');
            
            $job = new IndexThread($thread);
            $job->setIndexingWorkflowStepBucketId($this->indexingWorkflowStepBucketId);
            $jobs[] = $job;
        }

        if (empty($jobs)) {
            $bucket->update([
                'status' => WorkflowStatus::Completed->value,
                'finished_at' => now(),
            ]);
        }

        foreach ($jobs as $job) {
            dispatch($job);
        }
    }

    private function messageNeedsIndexing(Message $message): bool
    {
        return !Document::query()
            ->where('source_type', $this->vendor)
            ->where('source_id', $message->externalId)
            ->exists();
    }
}