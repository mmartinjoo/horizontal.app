<?php

namespace App\Jobs\Indexing;

use App\Jobs\Indexing\KnowledgeGraph\BuildCommunities;
use App\Jobs\Indexing\KnowledgeGraph\BuildKnowledgeGraph;
use App\Jobs\Indexing\KnowledgeGraph\BuildRelatedNodes;
use App\Jobs\Indexing\Storage\GoogleDrive\IndexGoogleDrive;
use App\Models\Team;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexKnowledgeBase implements ShouldQueue
{
    use Queueable;

    public function handle()
    {
        $team = Team::where('name', 'Test Company')->firstOrFail();

        IndexGoogleDrive::dispatch($team);
//        IndexJira::dispatch($team);

        BuildKnowledgeGraph::dispatch()
            ->delay(now()->addMinutes(5));

        BuildRelatedNodes::dispatch()
            ->delay(now()->addMinutes(12));

        BuildCommunities::dispatch()
            ->delay(now()->addMinutes(15));
    }
}
