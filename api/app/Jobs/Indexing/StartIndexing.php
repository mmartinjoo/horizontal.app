<?php 

namespace App\Jobs\Indexing;

use App\Models\Tenant;
use App\Services\Indexing\Orchestrator\Orchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class StartIndexing implements ShouldQueue
{
    use Queueable;

    public function __construct(private Tenant $tenant)
    {
    }

    public function handle(Orchestrator $orchestrator)
    {
        tenancy()->initialize($this->tenant);
        $orchestrator->schedule();
    }
}