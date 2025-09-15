<?php

namespace App\Services;

use App\Models\Document;
use App\Services\GraphDB\GraphDB;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class GraphBuilder
{
    public function __construct(
        private string $baseUrl,
        private GraphDB $graphDB,
    ) {}

    public function buildKG(): bool
    {
        $response = Http::post($this->baseUrl . '/api/build')
            ->throw();

        return $response->status() === Response::HTTP_ACCEPTED;
    }

    public function buildComments()
    {
        $documents = Document::with('comments.author')->get();
        foreach ($documents as $document) {
            $chunkNodes = $this->graphDB->queryMany("
                match (n:Chunk)
                where n.source_document_id={$document->id}
                return n
            ");

            foreach ($document->comments as $comment) {
                $this->graphDB->createNode(
                    label: 'Comment',
                    attributes: [
                        'id' => $comment->id,
                        'embedding' => $comment->embedding,
                        'body' => $comment->body,
                    ],
                );
                foreach ($chunkNodes as $chunkNode) {
                    $this->graphDB->addRelation(
                        fromNodeLabel: 'Comment',
                        fromNodeID: $comment->id,
                        relation: 'COMMENT_FOR',
                        toNodeLabel: 'Chunk',
                        toNodeID: $chunkNode->properties['id'],
                        relationAttributes: [
                            'commented_at' => $comment->commented_at,
                        ],
                    );
                }
                $this->graphDB->createNodeWithRelation(
                    newNodeLabel: 'Participant',
                    newNodeAttributes: [
                        'id' => $comment->author->id,
                        'name' => $comment->author->name,
                        'embedding' => $comment->author->embedding,
                    ],
                    relation: 'AUTHOR_OF',
                    relatedNodeLabel: 'Comment',
                    relatedNodeID: $comment->id,
                );
            }
        }
    }

    public function buildParticipants()
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
                        'embedding' => $participant->embedding,
                    ],
                );

                foreach ($chunkNodes as $chunkNode) {
                    $relation = match ($participant->pivot->context) {
                        'owner' => 'OWNER_OF',
                        'revision author' => 'CO_AUTHOR_OF',
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
}
