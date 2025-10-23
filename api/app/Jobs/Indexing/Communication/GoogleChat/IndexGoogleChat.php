<?php

namespace App\Jobs\Indexing\Communication\GoogleChat;

use App\Jobs\Indexing\Communication\IndexMessage;
use App\Models\Document;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use App\Services\Integration\Communication\GoogleChat\GoogleChat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class IndexGoogleChat implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
    }

    public function handle(GoogleChat $googleChat)
    {
        $channels = $googleChat->channels();
        foreach ($channels as $channel) {
            $messages = $googleChat->messages($channel);
            $newMessages = $this->rejectExistingMessages($messages);
            foreach ($newMessages as $message) {
                IndexMessage::dispatch($message, 'google_chat');
            }

            // $threads = $googleChat->threads($channel);
            // $newThreads = $this->rejectExistingMessages($threads);
            // foreach ($newThreads as $thread) {
            //     IndexThread::dispatch($thread);
            // }
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
                ->where('source_type', 'google_chat')
                ->where('source_id', $message->externalId)
                ->exists();

            if (!$exists) {
                $newMessages[] = $message;
            }
        }
        return $newMessages;
    }
}
