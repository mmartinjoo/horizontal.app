<?php

namespace App\Jobs\Indexing\KnowledgeGraph;

use App\Services\KnowledgeGraph\GraphBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildKnowledgeGraph implements ShouldQueue
{
    use Queueable;

    public function handle(GraphBuilder $graphBuilder)
    {
        $graphBuilder->buildKG();
    }
}
