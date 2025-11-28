<?php

namespace App\Jobs\Infra;

use App\Services\KnowledgeGraph\GraphBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class SuperviseQueues implements ShouldQueue
{
    use Queueable;

    public function handle(GraphBuilder $graphBuilder)
    {
        $defaultSize = Queue::size('default');
        $indexingSize = Queue::size('indexing');
        $questionSize = Queue::size('question');
        Redis::set('api-queue-size', $defaultSize + $indexingSize + $questionSize);

        $graphBuilderQueueSize = $graphBuilder->getQueueSize();        
        Redis::set('graphbuilder-queue-size', $graphBuilderQueueSize);
    }
}