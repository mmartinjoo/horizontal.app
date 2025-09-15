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
                        toNodeID: $chunkNode->id,
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
}
