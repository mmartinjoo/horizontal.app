<?php

namespace App\Services\SearchEngine;

use App\Models\DocumentChunk;
use App\Models\DocumentComment;
use App\Models\Question;
use App\Services\GraphDB\GraphDB;
use App\Services\GraphDB\GraphDBFactory;
use App\Services\LLM\Embedder;
use App\Services\LLM\LLMFactory;
use Bolt\protocol\v5\structures\Node;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class SearchEngine
{
    private GraphDB $graphDB;

    public function __construct(
        private Embedder $embedder,
        private GraphDBFactory $graphDBFactory,
        private string $cosineSimilarityThreshold,
    ) {
        $this->graphDB = $this->graphDBFactory->create();
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
            $chunks = $this->getRelevantChunkNodes($pivotCommunity);
            $chunkContext = [...$chunkContext, ...$chunks];
        }

        $chunkContext = collect($chunkContext)
            ->unique('id')
            ->map(function (Node $node) {
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
        $relevantDocuments = collect();
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
            

            if ($relevantDocuments->contains('id', $document->id)) {
                continue;
            }

            $relevantDocuments->push($document);
        }

        $question->update([
            'relevant_documents' => $relevantDocuments->map(function (Model $doc) {
                return [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'source_url' => $doc->source_url,
                    'source' => $doc->source,
                    'preview' => $doc->preview ? $doc->preview : $doc->body,
                ];
            }),
        ]);

        $llm = LLMFactory::create(tenancy()->tenant);
        $llm->stream("
            You are Horizontal's search engine, designed for engineering teams who need fast, accurate answers from scattered information.

            ## Context Provided

            You have two types of context:

            1. **Document Context**: The actual content from relevant sources
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
    }

    /**
     * @return array<Node>
     */
    private function getRelevantChunkNodes(array $node, int $hops = 2, int $limit = 30): array
    {
        return $this->graphDB->queryMany("
            match path=(n {id: {$node['node']->properties['id']}})-[r*..{$hops}]-(m)
            with [node in nodes(path) where 'Chunk' in labels(node)] as chunks
            unwind chunks as chunk
            return distinct chunk
            limit {$limit};
        ", ['chunk']);
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
}
