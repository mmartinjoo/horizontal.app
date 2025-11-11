<?php 

namespace App\Jobs\Indexing;

use App\Services\Indexing\Orchestrator\Orchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StartIndexing implements ShouldQueue
{
    use Queueable;

    public function handle(Orchestrator $orchestrator)
    {
        $orchestrator->schedule();
    }
}