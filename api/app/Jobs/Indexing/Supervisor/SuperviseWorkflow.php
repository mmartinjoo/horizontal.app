<?php

namespace App\Jobs\Indexing\Supervisor;

use App\Services\Indexing\Orchestrator\Supervisor\WorkflowSupervisor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Exception;
use Throwable;

class SuperviseWorkflow implements ShouldQueue
{
    use Queueable;

    private int $timeoutInSecond = 3600;
    private int $timeSpentInSecond = 0;
    private int $intervalInSecond = 5;

    public function __construct(
        private int $workflowId,
        private WorkflowSupervisor $supervisor,
        int $timeSpentInSecond = 0,
    ) {
        $this->timeSpentInSecond = $timeSpentInSecond;
        $this->timeoutInSecond = config('supervisor.timeout');
        $this->intervalInSecond = config('supervisor.interval');
    }

    public function handle()
    {
        try {
            logger()->info("supervising workflow #{$this->workflowId}");
            if ($this->timeSpentInSecond >= $this->timeoutInSecond) {   
                logger()->warning("timeout");             
                $this->supervisor->timeout($this->workflowId);
                return;
            }

            $result = $this->supervisor->superviseWorkflow($this->workflowId);
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

                $this->nextTick();
                throw new Exception('supervisor: unknwon state: ' . json_encode($result));
            }

            if ($result->nextAction === 'terminate') {
                logger()->info('supervisor: terminating with result: ' . json_encode($result));
                return;
            }

            $this->nextTick();
        } catch (Throwable $ex) {
            logger()->error('Supervise workflow ERROR: ' . $ex->getMessage());
            $this->nextTick();
        }
    }

    private function nextTick()
    {
        logger()->info("waiting {$this->intervalInSecond}s...");

        dispatch(new SuperviseWorkflow(
            workflowId: $this->workflowId,
            supervisor: $this->supervisor,
            timeSpentInSecond: $this->timeSpentInSecond += $this->intervalInSecond,
        ))
            ->delay($this->intervalInSecond);
    }
}