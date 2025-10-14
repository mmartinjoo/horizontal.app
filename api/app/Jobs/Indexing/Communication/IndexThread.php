<?php

namespace App\Jobs\Indexing\Communication;

use App\Exceptions\NoContentToIndexException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Participant;
use App\Services\Indexing\TextChunker;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class IndexThread implements ShouldQueue
{
    use Queueable;

    public function __construct(private Message $thread)
    {
    }

    public function handle(TextChunker $textChunker)
    {
        IndexMessage::dispatchSync($this->thread);
        $document = Document::query()
            ->where('source_type', 'slack')
            ->where('source_id', $this->thread->externalId)
            ->firstOrFail();

        foreach ($this->thread->replies as $reply) {
            // TODO: get usernames
            $p = Participant::updateOrCreate(
                [
                    'slug' => Str::slug($reply->externalUserId),
                    'type' => 'person',
                ],
                [
                    'slug' => Str::slug($reply->externalUserId),
                    'name' => $reply->externalUserId,
                    'type' => 'person',
                ],
            );
            $document->comments()->create([
                'author_id' => $p->id,
                'body' => $reply->message,
                'commented_at' => now(),
                'comment_id' => $reply->externalId,
                'metadata' => $reply,
            ]);
        }
    }
}
