<?php

namespace App\Jobs;

use App\Exceptions\EmbeddingException;
use App\Models\DocumentWorklog;
use App\Services\LLM\Embedder;
use App\Services\VectorStore\VectorStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class IndexIssueWorklog implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private DocumentWorklog $worklog,
    ) {
    }

    public function handle(
        Embedder $embedder,
        VectorStore $vectorStore,
    ): void {
        try {
            $embedding = $embedder->createEmbedding($this->worklog->getEmbeddableContent());
            $vectorStore->upsert($this->worklog, $embedding);
        } catch (Throwable $e) {
            throw EmbeddingException::wrap($e);
        }
    }
}
