<?php

namespace App\Jobs\Indexing\Supervisor;

use App\Services\Indexing\Orchestrator\Supervisor\WorkflowStepSupervisor;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SuperviseWorkflowStep implements ShouldQueue
{
    use Queueable;

    public $timeout = 1800;

    // 30 minutes
    private int $timeoutInSecond = 1795;
    private int $timeSpentInSecond = 0;
    private int $intervalInSecond = 5;

    public function __construct(
        private int $workflowStepId,
        private WorkflowStepSupervisor $supervisor,
    ) {}

    public function handle()
    {
        logger()->info("supervising workflow step #{$this->workflowStepId}");
        while (true) {
            if ($this->timeSpentInSecond >= $this->timeoutInSecond) {
                $this->supervisor->markAsFailed();
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
                    continue;
                }

                throw new Exception('supervisor: unknwon state: ' . json_encode($result));
            }

            if ($result->nextAction === 'terminate') {
                logger()->info('supervisor: terminating with result: ' . json_encode($result));
                return;
            }

            $this->nextTick();
        }
    }

    private function nextTick()
    {
        logger()->info("waiting {$this->intervalInSecond}s...");
        sleep($this->intervalInSecond);
        $this->timeSpentInSecond += $this->intervalInSecond;
    }
}