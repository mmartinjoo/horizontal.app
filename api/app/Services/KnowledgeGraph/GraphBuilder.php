<?php

namespace App\Services\KnowledgeGraph;

use App\Jobs\Indexing\IndexGraphCommunity;
use App\Models\Document;
use App\Models\DocumentComment;
use App\Services\GraphDB\GraphDB;
use App\Services\LLM\Embedder;
use Bolt\protocol\v5\structures\Node;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class GraphBuilder
{
    public function __construct(
        private string $baseUrl,
        private GraphDB $graphDB,
        private Embedder $embedder,
    ) {}

    public function buildKG(): bool
    {
        $response = Http::post($this->baseUrl . '/api/build')
            ->throw();

        return $response->status() === Response::HTTP_ACCEPTED;
    }

    public function buildRelatedNodes()
    {
        $this->connectCommentsToDocuments();
        $this->buildParticipants();
        $this->buildWorklogs();
    }

    public function buildCommunities()
    {
        $this->graphDB->run("
            match p=(n)-[r]-(m)
            where (not n:Chunk) and (not m:Chunk)
            with project(p) as subgraph
            call community_detection.get(subgraph)
            yield node, community_id
            merge (c:Community {id: community_id, name: community_id})
            merge (node)-[:BELONGS_TO]->(c);
        ");

        $communities = $this->graphDB->queryMany("
            match (c:Community)
            return c
        ", ['c']);

        foreach ($communities as $community) {
            $summary = "";
            $nodes = $this->graphDB->queryMany("
                match (n:__Entity__)-[r:BELONGS_TO]->(c:Community)
                where c.id = {$community->properties['id']}
                return n
            ", ['n']);

            foreach ($nodes as $node) {
                $summary .= "{$node->properties['name']}; ";
            }

            $nodeIds = [];
            foreach ($nodes as $node) {
                $nodeIds[] = $node->id;
            }
            $nodeIdsStr = json_encode($nodeIds);

            /** @var Node $chunk */
            $chunks = $this->graphDB->queryMany("
                match (n:__Entity__)<-[r:MENTIONS]-(c:Chunk)
                where id(n) IN {$nodeIdsStr}
                return c
            ", ['c']);

            $context = "";
            foreach ($chunks as $chunk) {
                $context .= $chunk ? $chunk->properties['text'] : "";
                $context .= " ";
                $relatedNodes = $this->graphDB->queryMany("
                    match (n)-[r]->(c:Chunk)
                    where id(c) = {$chunk->id} and not (n:__Entity__)
                    return n
                ", ['n']);

                foreach ($relatedNodes as $relatedNode) {
                    $context .= $relatedNode->properties['body'] ?? $relatedNode->properties['name'] ?? $relatedNode->properties['text'] ?? $relatedNode->properties['description'];
                    $context .= " ";
                }
                $context .= " ";
            }

            IndexGraphCommunity::dispatch(
                $community->properties['id'],
                $summary,
                $context,
            );
        }
    }

    private function connectCommentsToDocuments()
    {
        /**
         * There are :Chunk nodes for each `DocumentChunk`
         * These have a `source_document_id` that refers to a `Document`
         * Comments belong to a `Document`
         * Comment nodes have a `parent_document_id` that refers to the `Document`
         * This function connects:
         *  - Comments with a specific `parent_document_id`
         *  - To ALL `DocumentChunk` :Chunk nodes with the same `source_document_id`
         *
         * Which is not perfect but a good start.
         */

        $documentNodes = $this->graphDB->queryMany("
            match (n:Chunk)
            where n.document_type = \"document\"
            return n
        ");

        foreach ($documentNodes as $documentNode) {
            $commentNodes = $this->graphDB->queryMany("
                match (comment:Chunk { document_type: \"comment\", parent_document_id: {$documentNode->properties['source_document_id']} }),
                    (doc:Chunk { source_document_id: {$documentNode->properties['source_document_id']} })
                merge (comment)-[:COMMENT_FOR]->(doc)
                return comment
            ", ['comment']);

            foreach ($commentNodes as $commentNode) {
                $comment = DocumentComment::find($commentNode->properties['comment_id']);
                $participantNode = $this->graphDB->createNode(
                    label: 'Participant',
                    attributes: [
                        'id' => $comment->author->id,
                        'name' => $comment->author->name,
                        'embedding' => $this->embedder->createEmbedding($comment->author->name),
                    ],
                );
                $this->graphDB->run("
                    match (p:Participant { id: \"{$participantNode->properties['id']}\" }), (c:Chunk { comment_id: {$commentNode->properties['comment_id']} })
                    merge (p)-[:AUTHOR_OF]->(c)
                ");
            }
        }
    }

    private function buildParticipants()
    {
        $documents = Document::with('participants')->get();
        foreach ($documents as $document) {
            $chunkNodes = $this->graphDB->queryMany("
                match (n:Chunk)
                where n.source_document_id={$document->id}
                return n
            ");

            foreach ($document->participants as $participant) {
                // Already processed in `buildComments`
                if ($participant->pivot->context === 'commented') {
                    continue;
                }

                $this->graphDB->createNode(
                    label: 'Participant',
                    attributes: [
                        'id' => $participant->id,
                        'name' => $participant->name,
                        'embedding' => $this->embedder->createEmbedding($participant->name),
                    ],
                );

                foreach ($chunkNodes as $chunkNode) {
                    $relation = match ($participant->pivot->context) {
                        'owner' => 'OWNER_OF',
                        'revision author' => 'CO_AUTHOR_OF',
                        'watcher' => 'WATCHER_OF',
                        'voter' => 'VOTED_FOR',
                        'sharing user' => 'SHARED_BY',
                        default => 'MENTIONED_IN',
                    };

                    $this->graphDB->addRelation(
                        fromNodeLabel: 'Participant',
                        fromNodeID: $participant->id,
                        relation: $relation,
                        toNodeLabel: 'Chunk',
                        toNodeID: $chunkNode->properties['id'],
                    );
                }
            }
        }
    }

    private function buildWorklogs()
    {
        $documents = Document::with('worklogs.author')->get();
        foreach ($documents as $document) {
            $chunkNodes = $this->graphDB->queryMany("
                match (n:Chunk)
                where n.source_document_id={$document->id}
                return n
            ");

            foreach ($document->worklogs as $worklog) {
                $this->graphDB->createNode(
                    label: 'Worklog',
                    attributes: [
                        'id' => $worklog->id,
                        'embedding' => $worklog->description ? $this->embedder->createEmbedding($worklog->description) : [],
                        'description' => $worklog->description,
                    ],
                );
                foreach ($chunkNodes as $chunkNode) {
                    $this->graphDB->addRelation(
                        fromNodeLabel: 'Worklog',
                        fromNodeID: $worklog->id,
                        relation: 'WORKLOG_FOR',
                        toNodeLabel: 'Chunk',
                        toNodeID: $chunkNode->properties['id'],
                        relationAttributes: [
                            'logged_at' => $worklog->logged_at,
                        ],
                    );
                }
                $this->graphDB->createNodeWithRelation(
                    newNodeLabel: 'Participant',
                    newNodeAttributes: [
                        'id' => $worklog->author->id,
                        'name' => $worklog->author->name,
                        'embedding' => $this->embedder->createEmbedding($worklog->author->name),
                    ],
                    relation: 'AUTHOR_OF',
                    relatedNodeLabel: 'Worklog',
                    relatedNodeID: $worklog->id,
                );
            }
        }
    }
}
