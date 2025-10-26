<?php

namespace App\Services\Indexing\Orchestrator\Supervisor;

use App\Enums\Indexing\WorkflowStepItemStatus;
use App\Enums\Indexing\WorkflowStepStatus;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepItem;
use App\Services\Indexing\Orchestrator\DataTransferObject\SupervisorResult;

class WorkflowStepSupervisor
{
    private IndexingWorkflowStep $workflowStep;
    private int $failedCount;

    public function __construct(private int $workflowStepId)
    {
    }

    public function supervise(): SupervisorResult
    {
        $this->workflowStep = IndexingWorkflowStep::findOrFail($this->workflowStepId);

        // not started yet
        if ($this->workflowStep->overall_items === 0) {
            $this->workflowStep->update([
                'status' => WorkflowStepStatus::Starting->value,
            ]);
            return new SupervisorResult(
                status: WorkflowStepStatus::Starting->value,
                isExpectedStatus: true,
                nextAction: 'wait',
            );
        }

        // started
        if ($this->workflowStep->overall_items !== 0 && ($this->workflowStep->overall_items !== $this->workflowStep->processed_items)) {
            $this->workflowStep->update([
                'status' => WorkflowStepStatus::Processing->value,
            ]);
            return new SupervisorResult(
                status: WorkflowStepStatus::Processing->value,
                isExpectedStatus: true,
                nextAction: 'wait',
            );
        }

        // finished
        if ($this->workflowStep->overall_items === $this->workflowStep->processed_items) {
            $this->failedCount = IndexingWorkflowStepItem::query()
                ->where('indexing_workflow_step_id', $this->workflowStep->id)
                ->where('status', WorkflowStepItemStatus::Failed->value)
                ->count();

            // finished with errors
            if ($this->failedCount !== 0) {
                if ($this->failedCount === $this->workflowStep->processed_items) {
                    $this->workflowStep->update([
                        'status' => WorkflowStepStatus::Failed->value,
                    ]);
                    return new SupervisorResult(
                        status: WorkflowStepStatus::Failed->value,
                        isExpectedStatus: true,
                        nextAction: 'terminate',
                    );
                }

                $this->workflowStep->update([
                    'status' => WorkflowStepStatus::CompletedWithErrors->value,
                ]);
                return new SupervisorResult(
                    status: WorkflowStepStatus::CompletedWithErrors->value,
                    isExpectedStatus: true,
                    nextAction: 'terminate',
                );
            } else {
                $this->workflowStep->update([
                    'status' => WorkflowStepStatus::Completed->value,
                ]);
                return new SupervisorResult(
                    status: WorkflowStepStatus::Completed->value,
                    isExpectedStatus: true,
                    nextAction: 'terminate',
                );
            }
        }

        return new SupervisorResult(
            status: WorkflowStepStatus::Unknown->value,
            isExpectedStatus: false,
            nextAction: 'wait',
            timeoutInSecond: 30,
        );
    }

    public function timeout(): void
    {
        $workflowStep = IndexingWorkflowStep::findOrFail($this->workflowStepId);
        if ($workflowStep->status === WorkflowStepStatus::UpToDate->value) {
            $workflowStep->update([
                'status' => WorkflowStepStatus::Completed->value,
            ]);
            return;
        }

        $workflowStep->update([
            'status' => WorkflowStepStatus::Failed->value,
        ]);
    }
}