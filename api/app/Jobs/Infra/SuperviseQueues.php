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

        $size = $defaultSize + $indexingSize + $questionSize;
        Redis::del('api-queue-size');
        for ($i = 0; $i <= $size; $i++) {
            $item = "item_{$i}";
            Redis::rpush('api-queue-size', $item);
        }

        $graphBuilderQueueSize = $graphBuilderApi->getQueueSize();     
        Redis::del('graphbuilder-queue-size');
        for ($i = 0; $i <= $graphBuilderQueueSize; $i++) {
            $item = "item_{$i}";
            Redis::rpush('graphbuilder-queue-size', $item);
        }
    }
}