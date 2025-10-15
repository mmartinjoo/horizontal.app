<?php

namespace App\Jobs\Indexing;

use App\Jobs\Indexing\Communication\Slack\IndexSlack;
use App\Jobs\Indexing\KnowledgeGraph\BuildCommunities;
use App\Jobs\Indexing\KnowledgeGraph\BuildKnowledgeGraph;
use App\Jobs\Indexing\KnowledgeGraph\BuildRelatedNodes;
use App\Jobs\Indexing\Storage\GoogleDrive\IndexGoogleDrive;
use App\Services\GraphDB\GraphDB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexKnowledgeBase implements ShouldQueue
{
    use Queueable;

    public function handle(GraphDB $graphDB)
    {
        $graphDB->run('MATCH (n) DETACH DELETE n');

        // IndexGoogleDrive::dispatch();
        // IndexJira::dispatch();
        IndexSlack::dispatch();

        BuildKnowledgeGraph::dispatch()
            ->delay(now()->addMinutes(1));

        BuildRelatedNodes::dispatch()
            ->delay(now()->addMinutes(25));

        BuildCommunities::dispatch()
            ->delay(now()->addMinutes(30));
    }
}
