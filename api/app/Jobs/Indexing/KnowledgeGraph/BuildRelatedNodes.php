<?php

namespace App\Jobs\Indexing\KnowledgeGraph;

use App\Services\KnowledgeGraph\GraphBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildRelatedNodes implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->onQueue('indexing');
    }

    public function handle(GraphBuilder $graphBuilder)
    {
        $graphBuilder->buildRelatedNodes();
    }
}
