<?php

namespace App\Services\LLM;

interface Embedder
{
    /**
     * @return array<float>
     */
    public function createEmbedding(string $text): array;
}
