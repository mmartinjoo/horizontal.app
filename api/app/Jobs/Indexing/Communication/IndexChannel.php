<?php

namespace App\Jobs\Indexing\Communication;

use App\Models\Document;
use App\Models\IndexingWorkflowStep;
use App\Services\Integration\Communication\DataTransferObjects\Channel;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use App\Services\Integration\Communication\Slack\Slack;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

class IndexChannel implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Channel $channel,
        private Slack $slack,
        private int $indexingWorkflowStepId,
    ) {}

    public function handle()
    {
        return DB::transaction(function () {
            $indexingWorkflowStep = IndexingWorkflowStep::findOrFail($this->indexingWorkflowStepId);

            $messages = $this->slack->messages($this->channel);
            $indexingWorkflowStep->increment('overall_items', count($messages));
            foreach ($messages as $message) {
                if (!$this->messageNeedsIndexing($message)) {
                    $indexingWorkflowStep->increment('processed_items', 1);
                    $indexingWorkflowStep->increment('skipped_items', 1);
                    continue;
                }

                $job = new IndexMessage($message, 'slack');
                $job->setIndexingWorkflowStepId($this->indexingWorkflowStepId);

                // we delay the jobs so multiple `IndexChannel` can be started parallel
                // which is important to calculate `overall_items` in advance
                //
                // otherwise, after processing one channel the state would be:
                //   - overall_items: 5 (number of messages in one channel)
                //   - processed_items: 5 (the channel is fully processed)
                //
                // then supervisor thinks the step is completed but
                // there are other channels waiting to be processed
                dispatch($job)
                    ->delay(5)
                    ->afterCommit();
            }

            $threads = $this->slack->threads($this->channel);
            $indexingWorkflowStep->increment('overall_items', count($threads));
            foreach ($threads as $thread) { 
                if (!$this->messageNeedsIndexing($thread)) {
                    $indexingWorkflowStep->increment('processed_items', 1);
                    $indexingWorkflowStep->increment('skipped_items', 1);
                    continue;
                }    
                
                $job = new IndexThread($thread);
                $job->setIndexingWorkflowStepId($this->indexingWorkflowStepId);

                // we delay the jobs so multiple `IndexChannels` can be started parallel
                // see above
                dispatch($job)
                    ->delay(5)
                    ->afterCommit();
            }
        });        
    }

    private function messageNeedsIndexing(Message $message): bool
    {
        return !Document::query()
            ->where('source_type', 'slack')
            ->where('source_id', $message->externalId)
            ->exists();
    }
}