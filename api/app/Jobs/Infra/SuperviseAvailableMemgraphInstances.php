<?php

namespace App\Jobs\Infra;

use App\Services\ElasticMemgraphService\ElasticMemgraphService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class SuperviseAvailableMemgraphInstances implements ShouldQueue
{
    use Queueable;

    public function handle(ElasticMemgraphService $ems)
    {
        if (App::isLocal()) {
            return;
        }

        $threshold = config('ems.minimum_available_instance_threshold');
        $count = $ems->countAvailableInstances();
        if ($count <= $threshold) {
            Log::channel('slack')->critical('Only ' . $count . ' memgraph instances are available');
            logger()->critical('Only ' . $count . ' memgraph instances are available');
        }
    }
}