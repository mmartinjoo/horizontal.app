<?php

namespace App\Services\LLM;

use Stancl\Tenancy\Contracts\TenantWithDatabase;

class LLMFactory
{
    public static function create(TenantWithDatabase $tenant): LLM&Embedder
    {
        return match ($tenant->llm_provider) {
            'fireworks' => new Fireworks(
                apiKey: config('llm.connections.fireworks.api_key'), 
                chatModel: config('llm.connections.fireworks.chat_model'),
                embeddingModel: config('llm.connections.fireworks.embedding_model'),
            ),
            'together' => new Together(
                apiKey: config('llm.connections.together.api_key'),
                chatModel: config('llm.connections.together.chat_model'),
                embeddingModel: config('llm.connections.together.embedding_model'),
            )
        };
    }

    /**
     * embeddings need to be the same every time so we use only one model
     */
    public static function createEmbedder(): Embedder
    {
        return new Together(
            apiKey: config('llm.connections.together.api_key'),
            chatModel: config('llm.connections.together.chat_model'),
            embeddingModel: config('llm.connections.together.embedding_model'),
        );
    }
}
}
