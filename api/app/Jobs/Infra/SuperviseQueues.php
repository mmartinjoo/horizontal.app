<?php

namespace App\Jobs\Infra;

use App\Services\GraphBuilderApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class SuperviseQueues implements ShouldQueue
{
    use Queueable;

    public function handle(GraphBuilderApi $graphBuilderApi)
    {
        $defaultSize = Queue::size('default');
        $indexingSize = Queue::size('indexing');
        $questionSize = Queue::size('question');
        Redis::set('api-queue-size', $defaultSize + $indexingSize + $questionSize);

        $graphBuilderQueueSize = $graphBuilderApi->getQueueSize();        
        Redis::set('graphbuilder-queue-size', $graphBuilderQueueSize);
    }
}