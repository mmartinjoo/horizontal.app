<?php

namespace App\Jobs\Indexing\Communication\GoogleChat;

use App\Jobs\Indexing\Communication\IndexMessage;
use App\Models\Document;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use App\Services\Integration\Communication\GoogleChat\GoogleChat;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\LazyCollection;

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
        }
    }

    /**
     * @param LazyCollection<Message> $messages
     * @return LazyCollection<Message> $messages
     */
    private function rejectExistingMessages(LazyCollection $messages): LazyCollection
    {
        return LazyCollection::make(function () use ($messages) {
            /** @var Message $message */
            foreach ($messages as $message) {
                $exists = Document::query()
                    ->where('source_type', 'google_chat')
                    ->where('source_id', $message->externalId)
                    ->exists();

                if (!$exists) {
                    yield $message;
                }
            }
        });
    }
}
