<?php

namespace App\Services\LLM;

use Stancl\Tenancy\Contracts\TenantWithDatabase;

class LLMFactory
{
    public static function create(TenantWithDatabase $tenant): LLM
    {
        return match ($tenant->llm_provider) {
            'fireworks' => new Fireworks(
                apiKey: config('llm.connections.fireworks.api_key'), 
                model: config('llm.connections.fireworks.model'),
            ),
        };
    }

    public static function createEmbedder(): Embedder
    {
        return new Fireworks(config('services.fireworks.api_key'), config('embedder.model'));
    }
}
