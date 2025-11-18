<?php

namespace App\Services\LLM;

use Throwable;

/**
 * we rotate LLM providers on a scheduled basis to avoid rate limits
 */
class LLMRotation
{
    public function getRandomProvider(): string
    {
        try {
            $providers = config('llm.connections');
            $idx = rand(0, count($providers)-1);
            $provider = array_keys($providers)[$idx];

            return config("llm.connections.{$provider}");
        } catch (Throwable $ex) {
            logger()->error('unable to get random LLM provider: ' . $ex->getMessage());
            return config('llm.default');
        }        
    }

    public function rotate(string $currentProvider): string
    {        
        $providers = config('llm.connections');
        $otherProviders = collect(array_keys($providers))
            ->reject(fn (string $provider) => $provider === $currentProvider)
            ->shuffle();

        if (empty($otherProviders)) {
            logger()->warning('unable to rotate LLM provider from ' . $currentProvider);
            return $currentProvider;
        }

        return $otherProviders[0];
    }
}