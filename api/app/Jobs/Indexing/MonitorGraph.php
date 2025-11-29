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
 * this job monitors its health
 */
class MonitorGraph implements ShouldQueue
{
    use Queueable;

    public function __construct(private Tenant $tenant)
    {
    }

    public function handle()
    {
        tenancy()->initialize($this->tenant);
        if (Document::count() === 0) {
            return false;
        }

        if (IndexingWorkflow::count() === 0) {
            return false;
        }

        // at this point, the tenant has started at least one indexing workflow and has document
        
        $inProgressWorkflowExists = IndexingWorkflow::whereIn('status', [WorkflowStatus::Processing->value, WorkflowStatus::ReadForNextStep])->exists();
        if ($inProgressWorkflowExists) {
            return false;
        }

        // at this point, there are documents and completed workflows
        // the graph should be populated
        $graphDBFactory = app(GraphDBFactory::class);
        $graphDB = $graphDBFactory->create();
        $count = $graphDB->run("match (n:Community) return count(n) as count;")[0]['count'];
        if ($count === 0) {
            if (config('features.graph_monitoring.emergency_action.slack_warning.active')) {
                Log::channel('slack')->log(
                    level: config('features.graph_monitoring.emergency_action.slack_warning.log_level'),
                    message: 'GRAPH IS IN UNHEALTHY STATE',
                    context: [
                        'tenant_id' => $this->tenant->id,
                        'company' => $this->tenant->company,
                    ],
                );
            }

            if (config('features.graph_monitoring.emergency_action.graph_building.active')) {
                logger()->warning('emergency indexing started');
                $orchestrator = app(Orchestrator::class);
                $orchestrator->schedule();
            }
        }
    }
}