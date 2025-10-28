<?php

namespace App\Http\Controllers;

use App\Enums\Indexing\WorkflowStatus;
use App\Models\IndexingWorkflowStepBucket;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkflowController
{
    public function addBucket(Request $request)
    {
        $request->validate([
            'title' => ['required'],
            'overall_items' => ['required', 'numeric'],
            'workflow_step_id' => ['required', 'exists:indexing_workflow_steps,id'],
        ]);

        $bucket = IndexingWorkflowStepBucket::create([
            'indexing_workflow_step_id' => $request->get('workflow_step_id'),
            'title' => $request->get('title'),
            'overall_items' => $request->get('overall_items'),
            'status' => WorkflowStatus::Processing->value,
            'started_at' => now(),
        ]);

        return response([
            'bucket' => $bucket,
        ], Response::HTTP_CREATED);
    }
}