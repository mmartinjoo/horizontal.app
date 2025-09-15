<?php

namespace App\Services\LLM;

class LLMFactory
{
    public static function create(): LLM
    {
        return match (config('llm.provider')) {
            'fireworks' => new Fireworks(config('services.fireworks.api_key'), config('llm.model')),
            'openai' => new OpenAI(config('services.openai.api_key'), config('llm.model')),
            default => new Anthropic(config('services.anthropic.api_key'), config('llm.model')),
        };
    }

    public static function createEmbedder(): Embedder
    {
        return new Fireworks(config('services.fireworks.api_key'), config('embedder.model'));
    }
}
