<?php

namespace App\Http\Controllers;

use App\Enums\Indexing\WorkflowStatus;
use App\Models\DocumentChunk;
use App\Models\DocumentComment;
use App\Models\IndexingWorkflowStepBucket;
use App\Models\IndexingWorkflowStepItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class WorkflowController
{
    public function createBucket(Request $request)
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

    public function addItems(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['numeric'],
            'type' => ['required', 'in:document_chunks,document_comments'],
            'bucket_id' => ['required', 'exists:indexing_workflow_step_buckets,id'],
        ]);

        $bucket = IndexingWorkflowStepBucket::findOrFail($request->get('bucket_id'));
        $itemIds = [];
        foreach ($request->get('ids') as $id) {
            if ($request->get('type') === 'document_chunks') {
                $item = IndexingWorkflowStepItem::create([
                    'indexing_workflow_step_bucket_id' => $bucket->id,
                    'status' => WorkflowStatus::Starting->value,
                    'entity_type' => DocumentChunk::class,
                    'entity_id' => $id,
                    'job_id' => $request->get('job_id'),
                ]);
                $itemIds[] = $item->id;
            }
            if ($request->get('type') === 'document_comments') {
                $item = IndexingWorkflowStepItem::create([
                    'indexing_workflow_step_bucket_id' => $bucket->id,
                    'status' => WorkflowStatus::Starting->value,
                    'entity_type' => DocumentComment::class,
                    'entity_id' => $id,
                    'job_id' => $request->get('job_id'),
                ]);
                $itemIds[] = $item->id;
            }
        }

        return response([
            'item_ids' => $itemIds,
        ], Response::HTTP_CREATED);
    }

    public function markItemsAsProcessing(Request $request)
    {
        $request->validate([
            'bucket_item_ids' => ['required'],
            'bucket_item_ids.*' => ['exists:indexing_workflow_step_items,id'],
            'job_id' => ['required', 'string'],
        ]);

        DB::table('indexing_workflow_step_items')
            ->whereIn('id', $request->get('bucket_item_ids'))
            ->update([
                'status' => WorkflowStatus::Processing->value,
                'job_id' => $request->get('job_id'),
            ]);

        return response('', Response::HTTP_NO_CONTENT);
    }

    public function markItemsAsCompleted(Request $request)
    {
        $request->validate([
            'bucket_item_ids' => ['required'],
            'bucket_item_ids.*' => ['exists:indexing_workflow_step_items,id'],
        ]);

        DB::table('indexing_workflow_step_items')
            ->whereIn('id', $request->get('bucket_item_ids'))
            ->update([
                'status' => WorkflowStatus::Completed->value,
            ]);

        return response('', Response::HTTP_NO_CONTENT);
    }
}