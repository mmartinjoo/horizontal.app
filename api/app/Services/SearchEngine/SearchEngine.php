<?php

namespace App\Services\SearchEngine;

use App\Models\DocumentChunk;
use App\Models\DocumentComment;
use App\Models\Question;
use App\Services\GraphDB\GraphDB;
use App\Services\LLM\Embedder;
use App\Services\LLM\LLM;
use App\Services\SearchEngine\DataTransferObjects\Path;
use App\Services\SearchEngine\DataTransferObjects\SearchResult;
use Bolt\protocol\v1\structures\Path as BoltPath;
use Bolt\protocol\v5\structures\Node;
use Exception;
use Illuminate\Support\Arr;
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

    public function graphRAG(Question $question): array
    {
        $embedding = $this->embedder->createEmbedding($question->question);
        $results = $this->graphDB->vectorSearch('vector_index_communities', $embedding, 10);
        $chunkContext = [];
        $pivotCommunities = [];
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
            ->map(callback: function (Node $node) {
                $idCol = $node->properties['document_type'] === 'document'
                    ? 'document_chunk_id'
                    : 'comment_id';

                return [
                    'id' => $node->properties[$idCol],
                    'title' => Arr::get($node->properties, 'title'),
                    'text' => $node->properties['text'],
                    'type' => $node->properties['document_type'],
                    'status' => $this->getIssueStatus($node),
                ];
            })
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

            In the document context you are given a title and a text for each document.
            When you use a text chunk from a document, keep track of the document title, and use in the response.
            DO NOT include the document's ID in your response.

            ALWAYS INCLUDE a listacle in your anwser when it fits the content.
            Organize your response into paragprahs and subtitle when it makes sense.

            You MUST respond with a JSON object with the following keys:
            - answer: the answer to the question as string
            - relevant_documents: an array of document titles that are relevant to the question with the following keys:
                - id: the document id
                - title: the document title
                - type: the document type

            There's a `status` field for documents that come from task management systems such as Jira or Linear.
            If the status indiciated that the task is not started yet DO NOT TREAT the content as a \"fact\".
            At this point, it's only a plan for the future.
            So DO NOT treat those as facts.
            Those are only plans for the future.
            You can include them in your response but make it CLEAR that they are only future plans.
            Typical statuses that indicates that the task is not done yet are:
                - Backlog
                - To Do
                - Todo
                - Ready
                - Ready for Dev
                - Prioritized
                - Planned
                - Duplicate
                - Won't Do
                - Won't Fix
                - Canceled
                - Cancelled
                - Deferred

            This MUST be your answer:
            ```
            {
                \"answer\": \"your textual answer to the questions including paragprahs, listicles\",
                \"relevant_documents\": {
                    \"id\": 123,
                    \"title\": \"document title\",
                    \"type\": \"document_chunk\"
                }
            }
            ```

            ALWAYS respond with this structure.
            ...ALWAYS
        ");

        $answerData = json_decode($answer, true);        
        if (!$answerData) {
            throw new Exception('Unable to answer your question. Answer: ' . $answerData);
        }

        $documents = collect();
        foreach ($answerData['relevant_documents'] as $relevantDocument) {
            if ($relevantDocument['type'] === 'document') {
                $document = DocumentChunk::with('document')
                    ->find($relevantDocument['id'])
                    ->document;
            } else {
                $document = DocumentComment::with('document')
                    ->find($relevantDocument['id'])
                    ->document;
            }
            

            if ($documents->contains('id', $document->id)) {
                continue;
            }

            $documents->push($document);
        }

        return [
            'answer' => $answerData['answer'],
            'relevant_documents' => $documents,
        ];
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

    private function getIssueStatus(Node $node): ?string
    {
        if ($node->properties['document_type'] !== 'document') {
            return null;
        }

        $id = Arr::get($node->properties, 'document_chunk_id');
        if (!$id) {
            return null;
        }

        $documentChunk = DocumentChunk::find($id);
        if (!$documentChunk) {
            return null;
        }

        $document = $documentChunk->document;
        if (!$document) {
            return null;
        }

        if ($document->source_type !== 'issue') {
            return null;
        }

        $status = Arr::get($document->metadata, 'status');
        if (!$status || $status === '') {
            return null;
        }

        return $status;
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
