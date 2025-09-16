<?php

namespace App\Jobs;

use App\Exceptions\EmbeddingException;
use App\Models\DocumentComment;
use App\Services\LLM\Embedder;
use App\Services\VectorStore\VectorStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class IndexIssueComment implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private DocumentComment $comment,
    ) {
    }

    public function handle(
        Embedder $embedder,
        VectorStore $vectorStore,
    ): void {
        try {
            $embedding = $embedder->createEmbedding($this->comment->getEmbeddableContent());
            $vectorStore->upsert($this->comment, $embedding);
        } catch (Throwable $e) {
            throw EmbeddingException::wrap($e);
        }
    }
}
