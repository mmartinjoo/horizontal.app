<?php

namespace App\Jobs\Indexing\Communication\Slack;

use App\Jobs\Indexing\Communication\IndexMessage;
use App\Jobs\Indexing\Communication\IndexThread;
use App\Models\Document;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use App\Services\Integration\Communication\Slack\Slack;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class IndexSlack implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
    }

    public function handle(Slack $slack)
    {
        $channels = $slack->channels();
        foreach ($channels as $channel) {
            $messages = $slack->messages($channel);
            $newMessages = $this->rejectExistingMessages($messages);
            foreach ($newMessages as $message) {
                IndexMessage::dispatch($message);
            }

            $threads = $slack->threads($channel);
            $newThreads = $this->rejectExistingMessages($threads);
            foreach ($newThreads as $thread) {
                IndexThread::dispatch($thread);
            }
        }
    }

    /**
     * @param Collection<Message> $messages
     * @return Collection<Message> $messages
     */
    private function rejectExistingMessages(Collection $messages): Collection
    {
        $newMessages = collect();
        
        /** @var Message $message */
        foreach ($messages as $message) {
            $exists = Document::query()
                ->where('source_type', 'slack')
                ->where('source_id', $message->externalId)
                ->exists();

            if (!$exists) {
                $newMessages[] = $message;
            }
        }
        return $newMessages;
    }
}
