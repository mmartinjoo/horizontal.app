<?php

namespace App\Services\Indexing\Orchestrator\Supervisor;

use App\Enums\Indexing\WorkflowStepStatus;
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
                'status' => WorkflowStepStatus::Starting->value],
            );

            return new SupervisorResult(
                status: WorkflowStepStatus::Starting->value,
                isExpectedStatus: true,
                finiteState: false,
                nextAction: 'wait',
            );
        }

        $allFinite = $childResults->every('finiteState', true);
        if ($allFinite) {
            $nextAction = 'terminate';
            $entity->update([
                'finished_at' => now(),
            ]);

            $allFailed = $childResults->every('status', WorkflowStepStatus::Failed->value);
            if ($allFailed) {
                $entity->update([
                    'status' => WorkflowStepStatus::Failed->value],
                );

                return new SupervisorResult(
                    status: WorkflowStepStatus::Failed->value,
                    isExpectedStatus: true,
                    finiteState: true,
                    nextAction: $nextAction,
                );
            }

            $allCompleted = $childResults->every('status', WorkflowStepStatus::Completed->value);
            if ($allCompleted) {
                $entity->update([
                    'status' => WorkflowStepStatus::Completed->value],
                );

                return new SupervisorResult(
                    status: WorkflowStepStatus::Completed->value,
                    isExpectedStatus: true,
                    finiteState: true,
                    nextAction: $nextAction,
                );
            }

            $entity->update([
                'status' => WorkflowStepStatus::CompletedWithErrors->value,
            ]);

            return new SupervisorResult(
                status: WorkflowStepStatus::CompletedWithErrors->value,
                isExpectedStatus: true,
                finiteState: true,
                nextAction: $nextAction,
            );
        }

        if (! $allFinite) {
            $nextAction = 'wait';
            $hasProcessing = $childResults->some('status', WorkflowStepStatus::Processing->value);

            if ($hasProcessing) {
                $entity->update([
                    'status' => WorkflowStepStatus::Processing->value,
                ]);

                return new SupervisorResult(
                    status: WorkflowStepStatus::Processing->value,
                    isExpectedStatus: true,
                    finiteState: false,
                    nextAction: $nextAction,
                );
            }

            if (! $hasProcessing) {
                $entity->update([
                    'status' => WorkflowStepStatus::Starting->value,
                ]);

                return new SupervisorResult(
                    status: WorkflowStepStatus::Starting->value,
                    isExpectedStatus: true,
                    finiteState: false,
                    nextAction: $nextAction,
                );
            }
        }

        return new SupervisorResult(
            status: WorkflowStepStatus::Unknown->value,
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
        if ($bucket->overall_items === 0 && $bucket->status !== WorkflowStepStatus::Completed->value) {
            $bucket->update([
                'status' => WorkflowStepStatus::Starting->value,
            ]);

            return new SupervisorResult(
                status: WorkflowStepStatus::Starting->value,
                isExpectedStatus: true,
                finiteState: false,
                nextAction: 'wait',
            );
        }

        // started
        if ($bucket->overall_items !== 0 && ($bucket->overall_items !== $bucket->processed_items)) {
            $bucket->update([
                'status' => WorkflowStepStatus::Processing->value,
                'started_at' => now(),
            ]);

            return new SupervisorResult(
                status: WorkflowStepStatus::Processing->value,
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
                ->where('status', WorkflowStepStatus::Failed->value)
                ->count();

            // completely failed
            if ($failedCount !== 0) {
                if ($failedCount === $bucket->processed_items) {
                    $bucket->update([
                        'status' => WorkflowStepStatus::Failed->value,
                    ]);

                    return new SupervisorResult(
                        status: WorkflowStepStatus::Failed->value,
                        isExpectedStatus: true,
                        finiteState: true,
                        nextAction: 'terminate',
                    );
                }

                // finished with errors
                $bucket->update([
                    'status' => WorkflowStepStatus::CompletedWithErrors->value,
                ]);

                return new SupervisorResult(
                    status: WorkflowStepStatus::CompletedWithErrors->value,
                    isExpectedStatus: true,
                    finiteState: true,
                    nextAction: 'terminate',
                );
            } else {
                // perfect
                $bucket->update([
                    'status' => WorkflowStepStatus::Completed->value,
                ]);

                return new SupervisorResult(
                    status: WorkflowStepStatus::Completed->value,
                    isExpectedStatus: true,
                    finiteState: true,
                    nextAction: 'terminate',
                );
            }
        }

        return new SupervisorResult(
            status: WorkflowStepStatus::Unknown->value,
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
            'status' => WorkflowStepStatus::Timeout->value,
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
}