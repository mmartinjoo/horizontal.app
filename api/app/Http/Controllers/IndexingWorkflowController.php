<?php

namespace App\Http\Controllers;

use App\Enums\Indexing\WorkflowStatus;
use App\Models\IndexingWorkflow;
use Illuminate\Support\Facades\DB;

class IndexingWorkflowController extends Controller
{
    public function status()
    {
        $workflow = IndexingWorkflow::query()
            ->latest('id')
            ->first();

        if (!$workflow) {
            return response()->json([
                'message' => 'No indexing workflow found',
            ], 404);
        }

        $steps = DB::table('indexing_workflow_steps')
            ->leftJoin('indexing_workflow_step_buckets', 'indexing_workflow_steps.id', '=', 'indexing_workflow_step_buckets.indexing_workflow_step_id')
            ->where('indexing_workflow_steps.indexing_workflow_id', $workflow->id)
            ->select(
                'indexing_workflow_steps.name',
                'indexing_workflow_steps.status',
                DB::raw('COALESCE(SUM(indexing_workflow_step_buckets.overall_items), 0) as overall_items'),
                DB::raw('COALESCE(SUM(indexing_workflow_step_buckets.processed_items), 0) as processed_items')
            )
            ->groupBy('indexing_workflow_steps.id', 'indexing_workflow_steps.name', 'indexing_workflow_steps.status')
            ->orderBy('indexing_workflow_steps.id')
            ->get()
            ->map(function ($step) {
                return [
                    'name' => $step->name,
                    'display_name' => $this->getDisplayName($step->name),
                    'status' => $step->status,
                    'overall_items' => (int) $step->overall_items,
                    'processed_items' => (int) $step->processed_items,
                ];
            });

        return response()->json([
            'started_at' => $workflow->started_at->format('Y-m-d H:i:s'),
            'status' => $workflow->status,
            'steps' => $steps,
        ]);
    }

    public function completed()
    {
        $workflow = IndexingWorkflow::query()
            ->latest('id')
            ->first();

        if (!$workflow) {
            return response()->json([
                'result' => false,
            ], 200);
        }

        if (in_array($workflow->status, WorkflowStatus::finiteStates())) {
            return response()->json([
                'result' => true,
            ], 200);
        }

        return response()->json([
            'result' => false,
        ], 200);
    }

    private function getDisplayName(string $name): string
    {
        return match ($name) {
            'index_slack' => 'Indexing Slack',
            'index_github' => 'Indexing GitHub',
            'build_graph' => 'Building knowledge graph',
            'build_related_nodes' => 'Enhancing knowledge graph',
            'build_communities' => 'Connecting related information',
            default => ucwords(str_replace('_', ' ', $name)),
        };
    }
}
