<?php

namespace App\Services\SearchEngine;

use App\Models\DocumentChunk;
use App\Models\Question;
use App\Services\GraphDB\GraphDB;
use App\Services\LLM\Embedder;
use App\Services\LLM\LLM;
use App\Services\Memory\GraphitiService;
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
        private ?GraphitiService $graphitiService = null,
    ) {
        $this->graphitiService = $graphitiService ?? app(GraphitiService::class);
    }

    public function graphRAG(Question $question): string
    {
        $embedding = $this->embedder->createEmbedding($question->question);
        $results = $this->graphDB->vectorSearch('vector_index_communities', $embedding, 10);
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

    /**
     * Enhanced search with Graphiti memory integration
     *
     * @return Collection<SearchResult>
     */
    public function searchWithMemory(
        string $question,
        int $teamId,
        ?int $userId = null,
        array $clickedResults = []
    ): Collection {
        // Track search pattern in Graphiti
        if ($userId) {
            $this->graphitiService->trackSearchPattern(
                userId: $userId,
                searchQuery: $question,
                teamId: $teamId,
                clickedResults: $clickedResults,
                searchContext: 'hybrid_search'
            );
        }

        // Get user's historical patterns to personalize search
        $userPatterns = $userId ?
            $this->graphitiService->getUserPatterns($userId, $teamId, 5) : [];

        // Search Graphiti memory for relevant context
        $memoryResults = $this->graphitiService->searchMemory($question, $teamId, $userId, 5);

        // Enhance question with memory context
        $enhancedQuestion = $this->enhanceQueryWithMemory($question, $memoryResults, $userPatterns);

        // Perform hybrid search with enhanced query
        $semanticResults = $this->semanticSearch($enhancedQuestion);
        $keywordResults = $this->keywordSearch(explode(' ', $enhancedQuestion));
        $results = $this->combineResults($semanticResults, $keywordResults);

        // Add memory to Graphiti for future searches
        $searchContext = [
            'user_id' => $userId,
            'results_count' => $results->count(),
            'search_type' => 'hybrid_with_memory'
        ];

        $this->graphitiService->addMemory(
            text: "Search performed: '{$question}' returned {$results->count()} results",
            teamId: $teamId,
            context: $searchContext
        );

        return $results;
    }

    /**
     * Enhance search query with memory context
     */
    private function enhanceQueryWithMemory(
        string $originalQuery,
        array $memoryResults,
        array $userPatterns
    ): string {
        if (empty($memoryResults) && empty($userPatterns)) {
            return $originalQuery;
        }

        $enhancedTerms = [$originalQuery];

        // Add terms from relevant memory
        foreach ($memoryResults as $memory) {
            if (isset($memory['content']) && $memory['score'] > 0.7) {
                // Extract keywords from high-scoring memory content
                $keywords = $this->extractKeywords($memory['content']);
                $enhancedTerms = array_merge($enhancedTerms, $keywords);
            }
        }

        // Add terms from user patterns
        foreach ($userPatterns as $pattern) {
            if (isset($pattern['content'])) {
                $keywords = $this->extractKeywords($pattern['content']);
                $enhancedTerms = array_merge($enhancedTerms, array_slice($keywords, 0, 2));
            }
        }

        return implode(' ', array_unique($enhancedTerms));
    }

    /**
     * Extract meaningful keywords from text
     */
    private function extractKeywords(string $text): array
    {
        // Simple keyword extraction - in production, use more sophisticated NLP
        $words = str_word_count(strtolower($text), 1);
        $stopWords = ['the', 'is', 'at', 'which', 'on', 'and', 'a', 'to', 'are', 'as', 'was', 'with', 'for'];

        $keywords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords);
        });

        return array_slice(array_unique($keywords), 0, 5);
    }

    /**
     * Track successful search result clicks for learning
     */
    public function trackResultClick(
        string $documentId,
        string $searchQuery,
        int $userId,
        int $teamId
    ): void {
        $this->graphitiService->addMemory(
            text: "User clicked on document {$documentId} for search '{$searchQuery}'",
            teamId: $teamId,
            context: [
                'user_id' => $userId,
                'document_id' => $documentId,
                'search_query' => $searchQuery,
                'type' => 'result_click'
            ]
        );
    }
}
