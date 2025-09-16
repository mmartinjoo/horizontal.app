<?php

namespace App\Jobs\Indexing;

use App\Jobs\Indexing\Storage\GoogleDrive\IndexGoogleDrive;
use App\Jobs\Indexing\TaskManagement\Jira\IndexJira;
use App\Models\Team;
use App\Services\KnowledgeGraph\GraphBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexKnowledgeBase implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
    }

    public function handle(GraphBuilder $graphBuilder)
    {
        $team = Team::where('name', 'Test Company')->firstOrFail();

        IndexGoogleDrive::dispatch($team);
        IndexJira::dispatch($team);

        dispatch(function() use ($graphBuilder) {
             $graphBuilder->buildKG();
        })->delay(now()->addMinutes(5));

        dispatch(function() use ($graphBuilder) {
            $graphBuilder->buildRelatedNodes();
        })->delay(now()->addMinutes(12));

        dispatch(function() use ($graphBuilder) {
            $graphBuilder->buildCommunities();
        })->delay(now()->addMinutes(15));
    }
}
