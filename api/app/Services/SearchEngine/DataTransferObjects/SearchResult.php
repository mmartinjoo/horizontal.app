<?php

namespace App\Services\SearchEngine\DataTransferObjects;

use App\Models\DocumentChunk;

class SearchResult
{
    public float $weightedScore;
    public float $peopleBonus;

    public function __construct(
        public DocumentChunk $documentChunk,
        public ?float $semanticScore,
        public ?float $keywordScore,
    ) {
    }

    public static function from()
    {

    }
}
