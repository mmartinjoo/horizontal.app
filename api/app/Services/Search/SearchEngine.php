<?php

namespace App\Services\Search;

use App\Models\DocumentChunk;
use App\Models\Question;
use App\Services\GraphDB\GraphDB;
use App\Services\Indexing\EntityExtractor;
use App\Services\LLM\Embedder;
use App\Services\LLM\LLM;
use App\Services\Search\DataTransferObjects\Path;
use App\Services\Search\DataTransferObjects\SearchResult;
use Bolt\protocol\v1\structures\Path as BoltPath;
use Bolt\protocol\v5\structures\Node;
use Illuminate\Support\Collection;

class SearchEngine
{
    public function __construct(
        private Embedder $embedder,
        private EntityExtractor $entityExtractor,
        private GraphDB $graphDB,
        private LLM $llm,
        private string $cosineSimilarityThreshold,
    ) {
    }

    public function search(string $question)
    {
        $questionEntities = $this->entityExtractor->extract($question);
        $semanticResults = $this->semanticSearch($question);
        $keywordResults = $this->keywordSearch($questionEntities['keywords']);
        /** @var Collection<SearchResult> $combined */
        $combined = $this->combineResults($semanticResults, $keywordResults);

        return [
            'combined' => $this->rankResults($combined, $questionEntities),
            'semantic' => $semanticResults,
            'keyword' => $keywordResults,
            'entities' => $questionEntities,
        ];
    }

    /**
     * @param Collection<SearchResult> $results
     * @return Collection<SearchResult>
     */
    private function rankResults(Collection $results, array $questionEntities)
    {
        foreach ($results as $result) {
            $score = 0;
            if ($result->semanticScore) {
                $score += $result->semanticScore * 0.75;
            }
            if ($result->keywordScore) {
                $score += $result->keywordScore * 0.5;
            }
            $result->weightedScore = min($score, 1);

            $people = $result->documentChunk
                ->entities
                ->people;

            foreach ($people as $person) {
                if (in_array($person, $questionEntities['people'])) {
                    $result->weightedScore = min($result->weightedScore * 1.25, 1);
                    $result->peopleBonus = 1.25;
                    break;
                }
            }
        }
        return $results->sortByDesc('score');
    }

    public function graphSearch(Question $question): array
    {
        $embedding = $this->embedder->createEmbedding($question->question);
        $results = $this->graphDB->vectorSearch('vector_index_filechunk', $embedding, 3);
        $pivotNodes = [];
        /** @var Node $node */
        foreach ($results as $node) {
            if ($node['similarity'] >= 0.5) {
                $pivotNodes[] = $node;
            }
        }

//        foreach ($pivotNodes as &$pivotNode) {
//            $neighbours = $this->graphDB->queryMany("
//                MATCH (n)-[*1..2]-(m)
//                WHERE id(n) = {$pivotNode['node']->id}
//                AND (m:File OR m:FileChunk OR m:Issue OR m:IssueComment OR m:IssueWorklog)
//                RETURN m;
//            ", 'm');
//            $pivotNode['neighbours'] = $neighbours;
//        }
//        return $pivotNodes;

        //  1. Related topics
        foreach ($pivotNodes as &$pivotNode) {
            $relatedTopics = $this->graphDB->queryMany("
                match (fc:FileChunk)<-[r:MENTIONED_IN]-(t:Topic)
                where id(fc) = {$pivotNode['node']->id}
                return *
            ", ['t']);
            $pivotNode['relatedTopics'] = $relatedTopics;
        }

        // 2. Find documents related to topic
        foreach ($pivotNodes as &$pivotNode) {
            foreach ($pivotNode['relatedTopics'] as $relatedTopic) {
                $relatedDocuments = $this->graphDB->queryMany("
                    match (n)<-[r:MENTIONED_IN]-(t:Topic)
                    where id(t) = {$relatedTopic->id}
                    and id(n) <> {$pivotNode['node']->id}
                    return *
                ", ['n']);
                if (empty($relatedDocuments)) {
                    continue;
                }
                if (!isset($pivotNode['relatedDocuments'][$relatedTopic->id])) {
                    $pivotNode['relatedDocuments'][$relatedTopic->id] = [];
                }
                $pivotNode['relatedDocuments'][$relatedTopic->id][] = $relatedDocuments;
            }
        }

        return $pivotNodes;

        //  Ez legyen több query -ben:
        //  1. Related topics
        //  2. Find FileChunks, IssueChunks, Comments, etc based on topics
        //  3. Find files, issues based on those
        //  4. Find participants


        // Another idea:
        // 1. Find related topics:
        //  match p = (fc:FileChunk)<-[r:MENTIONED_IN*..1]-(t:Topic)
        //  where id(fc) = 7038
        //  return p
        // Find related entities based on those topics
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
