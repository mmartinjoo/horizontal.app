<?php

namespace App\Services\SearchEngine;

use App\Models\DocumentChunk;
use App\Models\Question;
use App\Services\GraphDB\GraphDB;
use App\Services\LLM\Embedder;
use App\Services\LLM\LLM;
use App\Services\SearchEngine\DataTransferObjects\Path;
use App\Services\SearchEngine\DataTransferObjects\SearchResult;
use Bolt\protocol\v1\structures\Path as BoltPath;
use Bolt\protocol\v5\structures\Node;
use Illuminate\Support\Collection;

class SearchEngine
{
    public function __construct(
        private Embedder $embedder,
        private GraphDB $graphDB,
        private LLM $llm,
        private string $cosineSimilarityThreshold,
    ) {
    }

    public function graphRAG(Question $question): string
    {
        $embedding = $this->embedder->createEmbedding($question->question);
        $results = $this->graphDB->vectorSearch('vector_index_community', $embedding, 10);
        $chunkContext = [];
        $pivotCommunities = [];
        /** @var Node $node */
        foreach ($results as $node) {
            if ($node['similarity'] >= $this->cosineSimilarityThreshold) {
                $pivotCommunities[] = $node;
            }
        }
        foreach ($pivotCommunities as &$pivotCommunity) {
            $chunks = [];
            $paths = $this->getRelevantPaths($pivotCommunity);
            foreach ($paths as $path) {
                foreach ($path->path->nodes as $node) {
                    if (!in_array('Chunk', $node->labels)) {
                        continue;
                    }
                    foreach ($chunks as $chunk) {
                        if ($chunk->id === $node->id) {
                            continue 2;
                        }
                    }
                    $chunks[] = $node;
                    $chunkContext[] = $node;
                }
            }
            $pivotCommunity['paths'] = $paths;
            $pivotCommunity['chunks'] = $chunks;
        }
        $pathStrings = [];
        foreach ($pivotCommunities as $pivotCommunity) {
            /** @var Path $path */
            foreach ($pivotCommunity['paths'] as $path) {
                $pathStrings[] = $path->pathString;
            }
        }
        $pathJSON = json_encode($pathStrings);
        $chunkContext = collect($chunkContext)
            ->unique('id')
            ->pluck('properties.text')
            ->values()
            ->toArray();
        $chunkJSON = json_encode($chunkContext);

        $answer = $this->llm->completion("
            You are a search engine.

            There's a graph database with communities and documents. It contains information from documents and issues.

            The following context is retrieved from a knowledge graph using semantic pivot search and relevance expansion.

            Each line represents a path from a community to a document.
            Graph context:
            {$pathJSON}

            The following is the content of related documents.
            Document context:
            {$chunkJSON}

            Based on the context, answer the question:
            {$question->question}
        ");
        return $answer;
    }

    /**
     * @return array<Path>
     */
    private function getRelevantPaths(array $node, int $hops = 2): array
    {
        /** @var array<BoltPath> $paths */
        $paths = $this->graphDB->queryMany("
            match path=(n { id: {$node['node']->properties['id']} })-[r*..{$hops}]-(m)
            return path
            limit 500
        ", ['path']);

        return Path::fromArray($paths);
    }

    /**
     * THE FOLLOWING IS NOT USED AT THE MOMENT
     * might be useful for different question intents such as "who?" type pf questions (a person usually is not part of a community)
     */

    /**
     * @return Collection<SearchResult>
     */
    private function semanticSearch(string $question): Collection
    {
        if (empty($question)) {
            return collect();
        }

        $embedding = $this->embedder->createEmbedding($question);
        $embeddingStr = '[' . implode(',', $embedding) . ']';

        // <=> returns cosine distance
        // (1 - cosine_distance) returns cosine similarity
        $chunks = DocumentChunk::query()
            ->selectRaw('*, 1 - (embedding <=> ?) as semantic_score', [$embeddingStr])
            ->whereRaw('embedding IS NOT NULL')
            ->whereRaw('1 - (embedding <=> ?) > 0.5', [$embeddingStr])
            ->orderByDesc('semantic_score')
            ->limit(10)
            ->get();

        return $chunks->map(fn (DocumentChunk $chunk) => new SearchResult(
            documentChunk: $chunk,
            semanticScore: $chunk->semantic_score,
            keywordScore: null,
        ));
    }

    /**
     * @return Collection<SearchResult>
     */
    private function keywordSearch(array $keywords): Collection
    {
        if (empty($keywords)) {
            return collect();
        }

        $query = implode(' | ', $keywords);
        $chunks = DocumentChunk::query()
            ->selectRaw("*, ts_rank(search_vector, plainto_tsquery('english', ?)) as keyword_score", [$query])
            ->whereRaw("search_vector @@ plainto_tsquery('english', ?)", [$query])
            ->orderByDesc('keyword_score')
            ->limit(10)
            ->get();

        return $chunks->map(fn (DocumentChunk $chunk) => new SearchResult(
            documentChunk: $chunk,
            semanticScore: null,
            keywordScore: $chunk->keyword_score,
        ));
    }

    /**
     * @param Collection<SearchResult> $semanticResults
     * @param Collection<SearchResult> $keywordResults
     * @return Collection<SearchResult>
     */
    private function combineResults(Collection $semanticResults, Collection $keywordResults): Collection
    {
        $results = collect();
        foreach ($semanticResults as $result) {
            $results->push(new SearchResult(
                documentChunk: $result->documentChunk,
                semanticScore: $result->semanticScore,
                keywordScore: null,
            ));
        }
        foreach ($keywordResults as $result) {
            /** @var SearchResult $existingItem */
            $existingItem = $results
                ->where(fn (SearchResult $searchRes) =>
                    $searchRes->documentChunk->id === $result->documentChunk->id
                )
                ->first();

            if ($existingItem) {
                $existingItem->keywordScore = $result->keywordScore;
            } else {
                $results->push(new SearchResult(
                    documentChunk: $result->documentChunk,
                    semanticScore: null,
                    keywordScore: $result->keywordScore,
                ));
            }
        }
        return $results;
    }
}
