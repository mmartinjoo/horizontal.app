<?php

namespace App\Jobs;

use App\Integrations\Storage\File;
use App\Integrations\Storage\GoogleDrive;
use App\Models\Document;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowItem;
use App\Models\Participant;
use App\Models\Team;
use App\Services\GraphDB\GraphDB;
use App\Services\Indexing\EntityExtractor;
use App\Services\Indexing\FilePrioritizer;
use App\Services\LLM\Embedder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class IndexGoogleDrive implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Team $team,
    ) {
    }

    public function handle(
        GoogleDrive $drive,
        FilePrioritizer $prioritizer,
        GraphDB $graphDB,
        Embedder $embedder,
        EntityExtractor $entityExtractor,
    ): void {
        /** @var IndexingWorkflow $indexing */
        $indexing = IndexingWorkflow::create([
            'integration' => 'google_drive',
            'status' => 'downloading',
            'team_id' => $this->team->id,
            'job_id' => $this->job->payload()['uuid'],
        ]);
        $files = $drive->listDirectoryContents('horizontal.app');
        $contents = $prioritizer->prioritize($files);
        $indexing->update([
            'status' => 'downloaded',
            'overall_items' => count($contents['high']) + count($contents['medium']) + count($contents['low']),
        ]);

        foreach (['high', 'medium', 'low'] as $prio) {
            /** @var File $file */
            foreach ($contents[$prio] as $i => $file) {
                if (!$this->fileNeedsIndexing($file)) {
                    $indexing->increment('skipped_items', 1);
                    if ($i === count($contents[$prio]) - 1) {
                        $indexing->update([
                            'status' => 'completed',
                        ]);
                    }
                    continue;
                }
                $count = Document::query()
                    ->where('team_id', $this->team->id)
                    ->where('source_type', 'google_drive')
                    ->where('source_id', $file->extraMetadata()['id'])
                    ->delete();

                // TODO: delete related graph nodes

                $indexing->increment('deleted_items', $count);
                $embedding = $embedder->createEmbedding($file->path());
                $document = Document::create([
                    'team_id' => $this->team->id,
                    'source_type' => 'google_drive',
                    'source_id' => $file->extraMetadata()['id'],
                    'title' => $file->path(),
                    'metadata' => $file,
                    'priority' => $prio,
                    'embedding' => $embedding,
                ]);
//                $graphDB->createNode('File', [
//                    'id' => $document->id,
//                    'name' => $document->title,
//                    'embedding' => $embedding,
//                ]);
                $indexingItem = IndexingWorkflowItem::create([
                    'indexing_workflow_id' => $indexing->id,
                    'data' => $file,
                    'status' => 'queued',
                    'document_id' => $document->id,
                ]);
                IndexFile::dispatch($indexingItem->id, $file);

                $revisionAuthors = $drive->getRevisionAuthors($file);
                foreach ($revisionAuthors as $author) {
                    $embedding = $embedder->createEmbedding($author);
                    $p = Participant::updateOrCreate(
                        [
                            'slug' => Str::slug($author),
                            'type' => 'person',
                        ],
                        [
                            'slug' => Str::slug($author),
                            'name' => $author,
                            'type' => 'person',
                            'embedding' => $embedding,
                        ],
                    );
                    $document->participants()->attach($p->id, [
                        'context' => 'revision author',
                        'embedding' => json_encode($embedding),
                    ]);
//                    $graphDB->createNodeWithRelation(
//                        newNodeLabel: 'Participant',
//                        newNodeAttributes: [
//                            'id' => $p->id,
//                            'name' => $p->name,
//                            'embedding' => $embedding,
//                        ],
//                        relation: 'REVISION_AUTHOR_OF',
//                        relatedNodeLabel: 'File',
//                        relatedNodeID: $document->id,
//                    );
                }

                $sharingUser = $file->getSharingUser();
                if ($sharingUser) {
                    $embedding = $embedder->createEmbedding($sharingUser);
                    $p = Participant::updateOrCreate(
                        [
                            'slug' => Str::slug($sharingUser),
                            'type' => 'person',
                        ],
                        [
                            'slug' => Str::slug($sharingUser),
                            'name' => $sharingUser,
                            'type' => 'person',
                            'embedding' => $embedding,
                        ],
                    );
                    $document->participants()->attach($p->id, [
                        'context' => 'sharing user',
                        'embedding' => json_encode($embedding),
                    ]);
//                    $graphDB->createNodeWithRelation(
//                        newNodeLabel: 'Participant',
//                        newNodeAttributes: [
//                            'id' => $p->id,
//                            'name' => $p->name,
//                            'embedding' => $embedding,
//                        ],
//                        relation: 'SHARING_USER_OF',
//                        relatedNodeLabel: 'File',
//                        relatedNodeID: $document->id,
//                    );
                }

                foreach ($file->getOwners() as $owner) {
                    $embedding = $embedder->createEmbedding($owner);
                    $p = Participant::updateOrCreate(
                        [
                            'slug' => Str::slug($owner),
                            'type' => 'person',
                        ],
                        [
                            'slug' => Str::slug($owner),
                            'name' => $owner,
                            'type' => 'person',
                            'embedding' => $embedding,
                        ],
                    );
                    $document->participants()->attach($p->id, [
                        'context' => 'owner',
                        'embedding' => json_encode($embedding),
                    ]);
//                    $graphDB->createNodeWithRelation(
//                        newNodeLabel: 'Participant',
//                        newNodeAttributes: [
//                            'id' => $p->id,
//                            'name' => $p->name,
//                            'embedding' => $embedding,
//                        ],
//                        relation: 'OWNER_OF',
//                        relatedNodeLabel: 'File',
//                        relatedNodeID: $document->id,
//                    );
                }

                $comments = $drive->getComments($file);
                foreach ($comments as $comment) {
                    $authorEmbedding = $embedder->createEmbedding($comment['author']);
                    $p = Participant::updateOrCreate(
                        [
                            'slug' => Str::slug($comment['author']),
                            'type' => 'person',
                        ],
                        [
                            'slug' => Str::slug($comment['author']),
                            'name' => $comment['author'],
                            'type' => 'person',
                            'embedding' => $authorEmbedding,
                        ],
                    );
                    $document->participants()->attach($p->id, [
                        'context' => 'commented',
                        'embedding' => json_encode($authorEmbedding),
                    ]);
//                    $graphDB->createNodeWithRelation(
//                        newNodeLabel: 'Participant',
//                        newNodeAttributes: [
//                            'id' => $p->id,
//                            'name' => $p->name,
//                            'embedding' => $authorEmbedding,
//                        ],
//                        relation: 'COMMENTED_ON',
//                        relatedNodeLabel: 'File',
//                        relatedNodeID: $document->id,
//                    );
                    $embedding = $embedder->createEmbedding($comment['content']);
                    $documentComment = $document->comments()->create([
                        'author_id' => $p->id,
                        'body' => $comment['content'],
                        'commented_at' => $comment['created_at'],
                        'comment_id' => $comment['id'],
                        'metadata' => $comment,
                        'embedding' => $embedding,
                    ]);
//                    $graphDB->createNodeWithRelation(
//                        newNodeLabel: 'Comment',
//                        newNodeAttributes: [
//                            'id' => $documentComment->id,
//                            'embedding' => $embedding,
//                        ],
//                        relation: 'COMMENT_OF',
//                        relatedNodeLabel: 'File',
//                        relatedNodeID: $document->id,
//                    );
//                    $graphDB->addRelation(
//                        fromNodeLabel: 'Participant',
//                        fromNodeID: $p->id,
//                        relation: 'AUTHOR_OF',
//                        toNodeLabel: 'FileComment',
//                        toNodeID: $documentComment->id,
//                    );
//                    $topics = $entityExtractor->extractTopics($comment['content']);
//                    $documentComment->createTopics($topics['topics']);
//                    foreach ($documentComment->topics as $topic) {
//                        $graphDB->createNodeWithRelation(
//                            newNodeLabel: 'Topic',
//                            newNodeAttributes: [
//                                'id' => $topic->id,
//                                'name' => $topic->name,
//                                'embedding' => $topic->embedding,
//                            ],
//                            relation: 'MENTIONED_IN',
//                            relatedNodeLabel: 'FileComment',
//                            relatedNodeID: $documentComment->id,
//                            relationAttributes: [
//                                'context' => $topic->pivot->context,
//                                'embedding' => $topic->pivot->embedding,
//                            ],
//                        );
//                    }
                }
            }
        }
    }

    private function fileNeedsIndexing(File $file): bool
    {
        $existingContent = Document::query()
            ->where('source_id', $file->extraMetadata()['id'])
            ->first();

        if ($existingContent === null) {
            return true;
        }

        return $file->getUpdatedAt()->gt($existingContent->indexed_at ?? '1900-01-01 00:00:00');
    }
}
