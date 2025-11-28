<?php 

namespace App\Jobs\Indexing;

use App\Enums\Indexing\WorkflowStatus;
use App\Models\Document;
use App\Models\IndexingWorkflow;
use App\Models\Tenant;
use App\Services\GraphDB\GraphDBFactory;
use App\Services\Indexing\Orchestrator\Orchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * since memgraph is an in-memory database it can lose its content between startups
 * to avoid that we run this "emergency" indexing job every X minutes
 */
class StartEmergencyIndexing implements ShouldQueue
{
    use Queueable;

    public function __construct(private Tenant $tenant)
    {
    }

    public function handle(Orchestrator $orchestrator)
    {
        tenancy()->initialize($this->tenant);
        if (Document::count() === 0) {
            return false;
        }

        if (IndexingWorkflow::count() === 0) {
            return false;
        }

        // at this point, the tenant has started at least one indexing workflow and has document
        
        $inProgressWorkflowExists = IndexingWorkflow::where('status', WorkflowStatus::Processing)->exists();
        if ($inProgressWorkflowExists) {
            return false;
        }

        // at this point, there are documents and completed workflowas
        // the graph should be populated
        $graphDBFactory = app(GraphDBFactory::class);
        $graphDB = $graphDBFactory->create();
        $count = $graphDB->run("match (n:Community) return count(n) as count;")[0]['count'];
        if ($count === 0) {
            logger()->warning('emergency indexing started');
            Log::channel('slack')->warning('emergency indexing started');
            $orchestrator->schedule();
        }
    }
}