<?php

namespace App\Services\Indexing\Orchestrator\Supervisor;

use App\Enums\Indexing\WorkflowStepItemStatus;
use App\Enums\Indexing\WorkflowStepStatus;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepBucket;
use App\Models\IndexingWorkflowStepItem;
use App\Services\Indexing\Orchestrator\DataTransferObject\SupervisorResult;
use Illuminate\Support\Collection;

class WorkflowStepBucketSupervisor
{
    public function __construct(private int $workflowStepId)
    {
    }

    public function supervise(): SupervisorResult
    {
        $workflowStep = IndexingWorkflowStep::findOrFail($this->workflowStepId);

        /** @var Collection<SupervisorResult>  */
        $bucketResults = collect();
        foreach ($workflowStep->buckets as $bucket) {
            $bucketResults[] = $this->superviseBucket($bucket);
        }

        if ($bucketResults->isEmpty()) {
            $workflowStep->update([
                'status' => WorkflowStepStatus::Starting->value,
            ]);
            return new SupervisorResult(
                status: WorkflowStepStatus::Starting->value,
                isExpectedStatus: true,
                finiteState: false,
                nextAction: 'wait',
            );  
        }

        $allFinite = $bucketResults->every('finiteState', true);
        if ($allFinite) {
            $nextAction = 'terminate';

            $workflowStep->update([
                'finished_at' => now(),
            ]);

            $allFailed = $bucketResults->every('status', WorkflowStepStatus::Failed->value);
            if ($allFailed) {
                $workflowStep->update([
                    'status' => WorkflowStepStatus::Failed->value,
                ]);
                $this->superviseWorkflow($workflowStep->workflow);
                return new SupervisorResult(
                    status: WorkflowStepStatus::Failed->value,
                    isExpectedStatus: true,
                    finiteState: true,
                    nextAction: $nextAction,
                );            
            }

            $allCompleted = $bucketResults->every('status', WorkflowStepStatus::Completed->value);
            if ($allCompleted) {
                $workflowStep->update([
                    'status' => WorkflowStepStatus::Completed->value,
                ]);
                $this->superviseWorkflow($workflowStep->workflow);
                return new SupervisorResult(
                    status: WorkflowStepStatus::Completed->value,
                    isExpectedStatus: true,
                    finiteState: true,
                    nextAction: $nextAction,
                );            
            }

            $workflowStep->update([
                'status' => WorkflowStepStatus::CompletedWithErrors->value,
            ]);
            $this->superviseWorkflow($workflowStep->workflow);
            return new SupervisorResult(
                status: WorkflowStepStatus::CompletedWithErrors->value,
                isExpectedStatus: true,
                finiteState: true,
                nextAction: $nextAction,
            );  
        }

        if (!$allFinite) {
            $nextAction = 'wait';
            $hasProcessing = $bucketResults->some('status', WorkflowStepStatus::Processing->value);
            if ($hasProcessing) {
                $workflowStep->update([
                    'status' => WorkflowStepStatus::Processing->value,
                ]);
                $this->superviseWorkflow($workflowStep->workflow);
                return new SupervisorResult(
                    status: WorkflowStepStatus::Processing->value,
                    isExpectedStatus: true,
                    finiteState: false,
                    nextAction: $nextAction,
                );  
            }

            if (!$hasProcessing) {
                $workflowStep->update([
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

        $this->superviseWorkflow($workflowStep->workflow);
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
            if (
                $bucket->status === WorkflowStepStatus::Starting->value
                && $bucket->skipped_items !== 0
            ) {
                // there were no documents to index. everything was skipped            
                if ($bucket->overall_items === $bucket->skipped_items) {
                    $bucket->update([
                        'status' => WorkflowStepStatus::Completed->value,
                        'finished_at' => now(),
                    ]);            
                    return new SupervisorResult(
                        status: WorkflowStepStatus::Completed->value,
                        isExpectedStatus: true,
                        finiteState: true,
                        nextAction: 'terminate',
                    );
                }
            }

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
                ->where('status', WorkflowStepItemStatus::Failed->value)
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

    private function superviseWorkflow(IndexingWorkflow $workflow): void
    {
        /** @var Collection<string> $stepStatuses */
        $stepStatuses = collect();

        /** @var IndexingWorkflowStep $step */
        foreach ($workflow->steps as $step) {
            $stepStatuses[] = $step->status;
        }

        $finiteStatuses = [
            WorkflowStepStatus::Completed->value,
            WorkflowStepStatus::CompletedWithErrors->value,
            WorkflowStepStatus::Failed->value,
        ];

        $finiteSteps = $stepStatuses->filter(function (string $status) use ($finiteStatuses) {
            return in_array($status, $finiteStatuses);
        });
        $allFinite = count($finiteSteps) === count($stepStatuses);
        if ($allFinite) {
            $allFailed = $stepStatuses->every(fn (string $status) => $status === WorkflowStepStatus::Failed->value);  
            $allCompleted = $stepStatuses->every(fn (string $status) => $status === WorkflowStepStatus::Completed->value);  

            if ($allFailed) {
                $workflow->update([
                    'status' => WorkflowStepStatus::Failed->value,
                    'finished_at' => now(),
                ]);
                return;
            }
            if ($allCompleted) {
                $workflow->update([
                    'status' => WorkflowStepStatus::Completed->value,
                    'finished_at' => now(),
                ]);
                return;
            }
            $workflow->update([
                'status' => WorkflowStepStatus::CompletedWithErrors->value,
                'finished_at' => now(),
            ]);
            return;
        }

        if (!$allFinite) {
            $hasProcessing = $stepStatuses->some(fn (string $status) => $status === WorkflowStepStatus::Processing->value);
            if ($hasProcessing) {
                $workflow->update([
                    'status' => WorkflowStepStatus::Processing->value
                ]);
                return;
            }
            if (!$hasProcessing) {
                $workflow->update([
                    'status' => WorkflowStepStatus::Starting->value
                ]);
                return;
            }
        }

        $workflow->update([
            'status' => WorkflowStepStatus::Unknown->value,
            'finished_at' => now(),
        ]);
        return;
    }

    public function timeout(): void
    {
        $workflowStep = IndexingWorkflowStep::findOrFail($this->workflowStepId);
        $workflowStep->update([
            'status' => WorkflowStepStatus::Failed->value,
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