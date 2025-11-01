<?php

namespace App\Services\Indexing\Orchestrator\Supervisor;

use App\Enums\Indexing\WorkflowStatus;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepBucket;
use App\Models\IndexingWorkflowStepItem;
use App\Services\Indexing\Orchestrator\DataTransferObject\SupervisorResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class WorkflowSupervisor
{
    public function superviseWorkflow(int $workflowId): SupervisorResult
    {
        $workflow = IndexingWorkflow::findOrFail($workflowId);

        /** @var Collection<SupervisorResult> $stepResults */
        $stepResults = $workflow
            ->steps
            ->map(function (IndexingWorkflowStep $step) {
                return $this->superviseStep($step); 
            });

        return $this->superviseEntity(
            entity: $workflow,
            childResults: $stepResults,
        );
    }

    private function superviseStep(IndexingWorkflowStep $workflowStep): SupervisorResult
    {
        /** @var Collection<SupervisorResult> $bucketResults */
        $bucketResults = $workflowStep
            ->buckets
            ->map(function (IndexingWorkflowStepBucket $bucket) {
                return $this->superviseBucket($bucket);
            });

        return $this->superviseEntity(
            entity: $workflowStep,
            childResults: $bucketResults,
        );
    }

    /**
     * @param Collection<SupervisorResult> $childResults
     */
    private function superviseEntity(Model $entity, Collection $childResults): SupervisorResult
    {
        if ($childResults->isEmpty()) {
            $entity->update([
                'status' => WorkflowStatus::Starting->value],
            );

            return new SupervisorResult(
                status: WorkflowStatus::Starting->value,
                isExpectedStatus: true,
                finiteState: false,
                nextAction: 'wait',
            );
        }

        $allFinite = $childResults->every('finiteState', true);
        if ($allFinite) {
            $nextAction = 'terminate';            

            $allFailed = $childResults->every('status', WorkflowStatus::Failed->value);
            if ($allFailed) {                
                $entity->update([
                    'status' => WorkflowStatus::Failed->value,
                    'finished_at' => now(),
                ]);

                return new SupervisorResult(
                    status: WorkflowStatus::Failed->value,
                    isExpectedStatus: true,
                    finiteState: true,
                    nextAction: $nextAction,
                );
            }

            $allCompleted = $childResults->every('status', WorkflowStatus::Completed->value);
            if ($allCompleted) {
                if ($entity instanceof IndexingWorkflow) {
                    $finishedAt = null;
                    if ($this->isWorkflowCompleted($entity)) {
                        $nextStatus = WorkflowStatus::Completed;
                        $finishedAt = now();
                    } else {
                        $nextStatus = WorkflowStatus::ReadForNextStep;
                        $finishedAt = null;
                    }

                    $entity->update([
                        'status' => $nextStatus->value,
                        'finished_at' => $finishedAt,
                    ],);

                    return new SupervisorResult(
                        status: $nextStatus->value,
                        isExpectedStatus: true,
                        finiteState: false,
                        nextAction: 'terminate',    // terminate because each step starts a new supervisor job
                    );    
                }

                $entity->update([
                    'status' => WorkflowStatus::Completed->value,
                    'finished_at' => now(),
                ]);

                return new SupervisorResult(
                    status: WorkflowStatus::Completed->value,
                    isExpectedStatus: true,
                    finiteState: true,
                    nextAction: $nextAction,
                );
            }

            $nextStatus = WorkflowStatus::CompletedWithErrors;
            $finishedAt = now();            

            if ($entity instanceof IndexingWorkflow) {
                if ($this->isWorkflowCompleted($entity)) {
                    $nextStatus = WorkflowStatus::CompletedWithErrors;
                    $finishedAt = now();
                } else {
                    $nextStatus = WorkflowStatus::ReadForNextStep;
                    $finishedAt = null;
                }
            }

            $entity->update([
                'status' => $nextStatus->value,
                'finished_at' => $finishedAt,
            ]);

            return new SupervisorResult(
                status: $nextStatus->value,
                isExpectedStatus: true,
                finiteState: true,
                nextAction: $nextAction,
            );
        }

        if (! $allFinite) {
            $nextAction = 'wait';
            $hasProcessing = $childResults->some('status', WorkflowStatus::Processing->value);

            if ($hasProcessing) {
                $entity->update([
                    'status' => WorkflowStatus::Processing->value,
                ]);

                return new SupervisorResult(
                    status: WorkflowStatus::Processing->value,
                    isExpectedStatus: true,
                    finiteState: false,
                    nextAction: $nextAction,
                );
            }

            if (!$hasProcessing) {
                // Only valid for a workflow
                // Every step finished, but the next one is not scheduled yet
                if ($entity->status === WorkflowStatus::ReadForNextStep->value) {
                    return new SupervisorResult(
                        status: WorkflowStatus::ReadForNextStep->value,
                        isExpectedStatus: true,
                        finiteState: false,
                        nextAction: $nextAction,
                    );
                }


                $entity->update([
                    'status' => WorkflowStatus::Starting->value,
                ]);

                return new SupervisorResult(
                    status: WorkflowStatus::Starting->value,
                    isExpectedStatus: true,
                    finiteState: false,
                    nextAction: $nextAction,
                );
            }
        }

        return new SupervisorResult(
            status: WorkflowStatus::Unknown->value,
            isExpectedStatus: false,
            finiteState: false,
            nextAction: 'wait',
            timeoutInSecond: 30,
        );
    }

    private function superviseBucket(IndexingWorkflowStepBucket $bucket): SupervisorResult
    {
        $this->updateStats($bucket);
        $bucket->fresh();

        // not started yet
        if ($bucket->overall_items === 0 && $bucket->status !== WorkflowStatus::Completed->value) {
            $bucket->update([
                'status' => WorkflowStatus::Starting->value,
            ]);

            return new SupervisorResult(
                status: WorkflowStatus::Starting->value,
                isExpectedStatus: true,
                finiteState: false,
                nextAction: 'wait',
            );
        }

        // started
        if ($bucket->overall_items !== 0 && ($bucket->overall_items !== $bucket->processed_items)) {
            // for the graph building buckets, items are created in advance.
            // so by only looking at `overall_items` and `processed_items`
            // it looks like the bucket is processing but in fact it's
            // not being processed yet. we also need to check items
            $itemsStarting = $bucket->items->every(fn (IndexingWorkflowStepItem $item) => $item->status === WorkflowStatus::Starting->value);
            if ($itemsStarting) {
                $bucket->update([
                    'status' => WorkflowStatus::Starting->value,
                ]);
                return new SupervisorResult(
                    status: WorkflowStatus::Starting->value,
                    isExpectedStatus: true,
                    finiteState: false,
                    nextAction: 'wait',
                );
            }

            $bucket->update([
                'status' => WorkflowStatus::Processing->value,
                'started_at' => now(),
            ]);

            return new SupervisorResult(
                status: WorkflowStatus::Processing->value,
                isExpectedStatus: true,
                finiteState: false,
                nextAction: 'wait',
            );
        }

        // finished
        if ($bucket->overall_items === $bucket->processed_items) {
            $bucket->update([
                'finished_at' => now(),
            ]);

            $failedCount = IndexingWorkflowStepItem::query()
                ->where('indexing_workflow_step_bucket_id', $bucket->id)
                ->where('status', WorkflowStatus::Failed->value)
                ->count();

            // completely failed
            if ($failedCount !== 0) {
                if ($failedCount === $bucket->processed_items) {
                    $bucket->update([
                        'status' => WorkflowStatus::Failed->value,
                    ]);

                    return new SupervisorResult(
                        status: WorkflowStatus::Failed->value,
                        isExpectedStatus: true,
                        finiteState: true,
                        nextAction: 'terminate',
                    );
                }

                // finished with errors
                $bucket->update([
                    'status' => WorkflowStatus::CompletedWithErrors->value,
                ]);

                return new SupervisorResult(
                    status: WorkflowStatus::CompletedWithErrors->value,
                    isExpectedStatus: true,
                    finiteState: true,
                    nextAction: 'terminate',
                );
            } else {
                // perfect
                $bucket->update([
                    'status' => WorkflowStatus::Completed->value,
                ]);

                return new SupervisorResult(
                    status: WorkflowStatus::Completed->value,
                    isExpectedStatus: true,
                    finiteState: true,
                    nextAction: 'terminate',
                );
            }
        }

        return new SupervisorResult(
            status: WorkflowStatus::Unknown->value,
            isExpectedStatus: false,
            finiteState: false,
            nextAction: 'wait',
            timeoutInSecond: 30,
        );
    }

    public function timeout(int $workflowId): void
    {
        $workflow = IndexingWorkflow::findOrFail($workflowId);
        $workflow->update([
            'status' => WorkflowStatus::Timeout->value,
            'finished_at' => now(),
        ]);
    }

    public function finished(int $workflowId)
    {
        $workflow = IndexingWorkflow::findOrFail($workflowId);
        $workflow->update([
            'finished_at' => now(),
        ]);
    }

    private function updateStats(IndexingWorkflowStepBucket $bucket)
    {
        $processedCount = IndexingWorkflowStepItem::query()
            ->where('indexing_workflow_step_bucket_id', $bucket->id)
            ->whereIn('status', ['completed', 'failed'])
            ->count();

        $bucket->update([
            'processed_items' => $processedCount,
        ]);
    }

    /**
     * If `build_communities` is the last step the workflow is in a final state
     */
    private function isWorkflowCompleted(IndexingWorkflow $workflow): bool
    {
        $lastStep = $workflow->steps()->orderBy('id', 'desc')->first();
        if ($lastStep->name === 'build_communities') {
            return true;
        }
        return false;
    }
}