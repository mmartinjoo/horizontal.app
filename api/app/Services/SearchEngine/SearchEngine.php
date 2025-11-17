<?php

namespace App\Services\SearchEngine;

use App\Models\Document;
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
use Illuminate\Database\Eloquent\Model;
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

    public function graphRAG(Question $question)
    {
        $this->graphDB->run('STORAGE MODE IN_MEMORY_ANALYTICAL');

        $embedding = $this->embedder->createEmbedding($question->question);
        $results = $this->graphDB->vectorSearch('vector_index_communities', $embedding, 10);

        $this->graphDB->run('STORAGE MODE IN_MEMORY_TRANSACTIONAL');

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
        $question->update([
            'relevant_graph_paths' => $pathJSON,
        ]);

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
        $potentiallyRelevantDocuments = collect();
        foreach ($chunkContext as $chunk) {
            if ($chunk['type'] === 'document') {
                $document = DocumentChunk::with('document')
                    ->find($chunk['id'])
                    ->document;
            } else {
                $document = DocumentComment::with('document')
                    ->find($chunk['id'])
                    ->document;
            }
            

            if ($potentiallyRelevantDocuments->contains('id', $document->id)) {
                continue;
            }

            $potentiallyRelevantDocuments->push($document);
        }

        $question->update([
            'relevant_documents' => $potentiallyRelevantDocuments->map(function (Model $doc) {
                return [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'source_url' => $doc->source_url,
                    'source' => $doc->source,
                    'preview' => $doc->preview ? $doc->preview : $doc->body,
                ];
            }),
        ]);

        $this->llm->stream("
            You are Horizontal's search engine, designed for engineering teams who need fast, accurate answers from scattered information.

            ## Context Provided

            You have two types of context:

            1. **Graph Context**: Shows how information is connected (communities → documents)
            {$pathJSON}

            2. **Document Context**: The actual content from relevant sources
            {$chunkJSON}

            ## Your Task

            Answer this question: {$question->question}

            ## Response Guidelines

            ### Structure
            - Start with a direct answer (2-3 sentences max)
            - Use clear markdown headings (##, ###) to organize information
            - The first header should be \"Executive summary\"
            - In the first header, you should use multiple, shorter paragraphs, instead of one that is too lenghty. Include som breathing room for the reader.
            - Use bullet points for lists, not numbered lists unless ranking/sequencing matters
            - Keep paragraphs short (2-4 sentences)
            - NO emojis

            ### Source Attribution
            - ALWAYS cite sources using document titles in square brackets [Document Title]
            - When multiple sources confirm the same info, cite all: [Doc1] [Doc2]
            - Group related information by source when it makes sense
            - Include timestamps if the information is time-sensitive
            - DO NOT include document IDs

            ### Handling Different Types of Information

            **Completed Work** (Done, Closed, Merged, Deployed, Released):
            - Present as facts: \"The team implemented X [PR #123]\"

            **In-Progress Work** (In Progress, In Review, In Development):
            - Use present continuous: \"The team is currently working on X [JIRA-456]\"

            **Planned Work** (Backlog, To Do, Todo, Ready, Ready for Dev, Prioritized, Planned):
            - Be explicit: \"**Planned:** The team plans to implement X [LINEAR-789]\"
            - Use future tense: \"This will be addressed in...\"

            !DO NOT treat information from in-progress or planned issues as facts!
            This is not good for customers!
            When the status of an issue signals that it's not completed yet, MAKE IT CLEAR in your answer.

            **Rejected/Cancelled** (Won't Do, Won't Fix, Canceled, Cancelled, Duplicate, Deferred):
            - State clearly: \"**Not pursued:** This was considered but rejected because...\"

            ### Engineering-Specific Features

            **When discussing bugs/issues:**
            - Highlight root cause if mentioned
            - Include who fixed it and when
            - Link to related PRs/commits

            **When discussing decisions:**
            - Explain the \"why\" behind technical choices
            - Cite discussions from Slack, GitHub, or docs
            - Include trade-offs mentioned

            **When discussing features:**
            - Distinguish between shipped vs planned
            - Include responsible team members if mentioned
            - Link PRs, tickets, and design docs together

            ### Connected Insights

            When the graph context shows relationships:
            - Explicitly mention how pieces connect
            - Example: \"This feature [PR #123] was requested in [Slack thread], documented in [Design Doc], and shipped in [JIRA-456]\"
            - Highlight who was involved: \"Led by Alice, reviewed by Bob, requested by Mike from sales\"

            ### Handling Uncertainty

            If information is:
            - **Incomplete**: Say \"Based on available context...\" and explain what's missing
            - **Conflicting**: Present both sides and note the conflict
            - **Outdated**: Include the date and suggest it might be stale
            - **Not found**: Say clearly \"I couldn't find information about X in your connected tools\"

            ### Format Examples

            **Good response structure:**

            ## Executive summary
            The MySQL error was caused by exceeding `max_allowed_packet` during bulk inserts [GitHub PR #247].

            ## What Happened
            On April 15, the bulk create API was hitting... [Slack #engineering]

            ## How It Was Fixed
            Ben created the issue [LINEAR DEV-238], Tom contributed the fix [GitHub PR #247], and Peter merged it [GitHub PR #247].

            **Related:**
            - Similar issue from 2023: [JIRA-123]
            - Current max_allowed_packet value: [Docs/MySQL Config]

            ### Response Length
            - Simple factual questions: 3-5 sentences
            - Complex questions requiring context: 2-3 sections with clear headers
            - \"Why\" questions: Include the decision chain with sources

            ### Quality Checks Before Responding
            ✓ Did I cite every claim with source documents?
            ✓ Did I distinguish between done/in-progress/planned?
            ✓ Is the answer scannable (headings, bullets, short paragraphs)?
            ✓ Did I use the graph context to show connections?
            ✓ Is it clear where the user can find more details?

            Remember: Engineering teams value **precision, speed, and traceability**. Be direct, cite everything, and make it easy to dive deeper.
        ", $question);

        $question->update([
            'relevant_documents' => $potentiallyRelevantDocuments->map(function (Document $doc) {
                return [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'source_url' => $doc->source_url,
                    'source' => $doc->source,
                    'preview' => $doc->preview ? $doc->preview : $doc->body,
                ];
            }),
        ]);
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
