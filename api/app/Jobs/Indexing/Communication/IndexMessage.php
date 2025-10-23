<?php

namespace App\Jobs\Indexing\Communication;

use App\Exceptions\NoContentToIndexException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Participant;
use App\Services\Indexing\TextChunker;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use App\Services\Integration\Communication\DataTransferObjects\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Message $message,
        private string $sourceType,
    ) {

    }

    public function handle(TextChunker $textChunker)
    {
        $chunks = $textChunker->chunk($this->message->message);
        if (count($chunks) === 0) {
            throw new NoContentToIndexException('Chunk is empty: ' . json_encode($this->message));
        }
        if (count($chunks) === 1 && strlen(trim($chunks->first())) === 0) {
            throw new NoContentToIndexException('Chunk contains one empty item: ' . json_encode($this->message));
        }

        $document = Document::create([
            'source_type' => $this->sourceType,
            'source_id' => $this->message->externalId,
            'source_url' => $this->message->url,
            'title' => "{$this->message->author->realName}'s message in #{$this->message->channel->name}",
            'preview' => $chunks->first(),
            'priority' => 'high',
            'metadata' => $this->message,
        ]);
        if ($this->message->author) {
            $author = Participant::getOrCreate($this->message->author?->realName);
            $document->participants()->attach($author->id, [
                'context' => 'author',
            ]);
        }

        /** @var User $mentionedUser */
        foreach ($this->message->mentions as $mentionedUser) {
            $p = Participant::getOrCreate($mentionedUser->realName);
            $document->participants()->attach($p->id, [
                'context' => 'mentioned',
            ]);
        }

        foreach ($chunks as $i => $chunk) {
            DocumentChunk::create([
                'document_id' => $document->id,
                'body' => $chunk,
                'position' => $i+1,
            ]);
        }
    }
}
