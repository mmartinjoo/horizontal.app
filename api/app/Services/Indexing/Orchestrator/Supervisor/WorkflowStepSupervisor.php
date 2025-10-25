<?php

namespace App\Services\Indexing\Orchestrator\Supervisor;

use App\Enums\Indexing\WorkflowStepItemStatus;
use App\Enums\Indexing\WorkflowStepStatus;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepItem;
use App\Services\Indexing\Orchestrator\DataTransferObject\SupervisorResult;

class WorkflowStepSupervisor
{
    private int $failedCount;
    private int $processingCount;
    private int $overallCount;

    public function __construct(private int $workflowStepId)
    {
    }

    public function supervise(): SupervisorResult
    {
        $workflowStep = IndexingWorkflowStep::findOrFail($this->workflowStepId);

        $this->failedCount = IndexingWorkflowStepItem::query()
            ->where('indexing_workflow_step_id', $workflowStep->id)
            ->where('status', WorkflowStepItemStatus::Failed->value)
            ->count();

        $this->processingCount = IndexingWorkflowStepItem::query()
            ->where('indexing_workflow_step_id', $workflowStep->id)
            ->where('status', WorkflowStepItemStatus::Processing->value)
            ->count();

        $this->overallCount = IndexingWorkflowStepItem::query()
            ->where('indexing_workflow_step_id', $workflowStep->id)
            ->count();

        // not started yet
        if ($this->overallCount === 0) {
            return new SupervisorResult(
                status: WorkflowStepStatus::Starting->value,
                isExpectedStatus: true,
                nextAction: 'wait',
            );
        }

        if ($workflowStep->skipped_items === $this->overallCount) {
            $workflowStep->update([
                'status' => WorkflowStepStatus::Completed->value,
            ]);
            return new SupervisorResult(
                status: WorkflowStepStatus::Completed->value,
                isExpectedStatus: true,
                nextAction: 'terminate',
            );
        }

        if ($this->processingCount !== 0) {
            $workflowStep->update([
                'status' => WorkflowStepStatus::Processing->value,
            ]);
            return new SupervisorResult(
                status: WorkflowStepStatus::Processing->value,
                isExpectedStatus: true,
                nextAction: 'wait',
            );
        }

        if ($this->hasAllItemFailed()) {
            $workflowStep->update([
                'status' => WorkflowStepStatus::Failed->value,
            ]);
            return new SupervisorResult(
                status: WorkflowStepStatus::Failed->value,
                isExpectedStatus: true,
                nextAction: 'terminate',
            );
        }

        if ($this->hasCompletedWithFailures()) {
            $workflowStep->update([
                'status' => WorkflowStepStatus::CompletedWithErrors->value,
            ]);
            return new SupervisorResult(
                status: WorkflowStepStatus::CompletedWithErrors->value,
                isExpectedStatus: true,
                nextAction: 'terminate',
            );
        }

        if ($this->hasCompletedSuccesfully()) {
            $workflowStep->update([
                'status' => WorkflowStepStatus::Completed->value,
            ]);
            return new SupervisorResult(
                status: WorkflowStepStatus::Completed->value,
                isExpectedStatus: true,
                nextAction: 'terminate',
            );
        }
        return new SupervisorResult(
            status: 'unknown',
            isExpectedStatus: false,
            nextAction: 'wait',
            timeoutInSecond: 30,
        );
    }

    public function markAsFailed(): void
    {
        $workflowStep = IndexingWorkflowStep::findOrFail($this->workflowStepId);
        $workflowStep->update([
            'status' => WorkflowStepStatus::Failed->value,
        ]);
    }

    private function hasAllItemFailed(): bool
    {
        return $this->processingCount === 0 
            && $this->failedCount === $this->overallCount;
    }

    private function hasCompletedWithFailures(): bool
    {
        return $this->processingCount === 0 
            && $this->failedCount !== 0;
    }

    private function hasCompletedSuccesfully(): bool
    {
        return $this->processingCount === 0 
            && $this->failedCount === 0;
    }
}