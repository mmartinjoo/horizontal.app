<?php

namespace App\Services\LLM;

use Exception;

trait HasEmbeddingCache
{
    /** @var array<string, array<float>> */
    private array $cache = [];

    /**
     * @return array<float>
     * @throws Exception
     */
    abstract protected function createEmbeddingWithoutCache(string $text): array;

    /**
     * @return array<float>
     * @throws Exception
     */
    public function createEmbedding(string $text): array
    {
        if (empty($text)) {
            return [];
        }
        if (isset($this->cache[$text])) {
            return $this->cache[$text];
        }
        $embedding = $this->createEmbeddingWithoutCache($text);
        $this->cache[$text] = $embedding;
        return $embedding;
    }
}
