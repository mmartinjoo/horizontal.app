<?php

namespace App\Services\Indexing\Orchestrator\DataTransferObject;

class SupervisorResult
{
    public function __construct(
        public string $status,
        public bool $isExpectedStatus,
        public bool $finiteState,
        public string $nextAction,  
        public int $timeoutInSecond = 0,      
    ) {}
}