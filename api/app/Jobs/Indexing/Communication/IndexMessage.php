<?php

namespace App\Jobs\Indexing\Communication;

use App\Exceptions\NoContentToIndexException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\Indexing\TextChunker;
use App\Services\Integration\Communication\DataTransferObjects\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(private Message $message)
    {
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
            'source_type' => 'slack',
            'source_id' => $this->message->externalId,
            'title' => 'Message in #' . $this->message->channel->name,
            'preview' => $chunks->first(),
            'priority' => 'high',
            'metadata' => $this->message,
        ]);

        foreach ($chunks as $i => $chunk) {
            DocumentChunk::create([
                'document_id' => $document->id,
                'body' => $chunk,
                'position' => $i+1,
            ]);
        }
    }
}
