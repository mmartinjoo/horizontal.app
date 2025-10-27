<?php

namespace App\Jobs\Indexing\Supervisor;

use App\Services\Indexing\Orchestrator\Supervisor\WorkflowStepBucketSupervisor;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SuperviseWorkflowStep implements ShouldQueue
{
    use Queueable;

    public $timeout = 1800;

    // 30 minutes
    private int $timeoutInSecond = 90;
    private int $timeSpentInSecond = 0;
    private int $intervalInSecond = 5;

    public function __construct(
        private int $workflowStepId,
        private WorkflowStepBucketSupervisor $supervisor,
        int $timeSpentInSecond = 0,
    ) {
        $this->timeSpentInSecond = $timeSpentInSecond;
    }

    public function handle()
    {
        logger()->info("supervising workflow step #{$this->workflowStepId}");
        if ($this->timeSpentInSecond >= $this->timeoutInSecond) {   
            logger()->warning("timeout");             
            $this->supervisor->timeout();
            return;
        }

        $result = $this->supervisor->supervise();
        logger()->info("supervisor: result: " . json_encode($result));

        if ($result->status === 'unknown') {
            logger()->warning('supervisor: unknown status in supervisor: ' . json_encode($result));

            if ($result->nextAction !== 'wait') {
                logger()->warning('supervisor: terminating supervisor job');
                return;
            }

            if ($result->nextAction === 'wait') {
                $this->timeoutInSecond = $result->timeoutInSecond;
                logger()->warning("supervisor: new timeout is {$this->timeoutInSecond}s");
                $this->nextTick();
                return;
            }

            throw new Exception('supervisor: unknwon state: ' . json_encode($result));
        }

        if ($result->nextAction === 'terminate') {
            logger()->info('supervisor: terminating with result: ' . json_encode($result));
            return;
        }

        $this->nextTick();
    }

    private function nextTick()
    {
        logger()->info("waiting {$this->intervalInSecond}s...");

        dispatch(new SuperviseWorkflowStep(
            workflowStepId: $this->workflowStepId,
            supervisor: $this->supervisor,
            timeSpentInSecond: $this->timeSpentInSecond += $this->intervalInSecond,
        ))->delay($this->intervalInSecond);
    }
}