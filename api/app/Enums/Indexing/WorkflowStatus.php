<?php

namespace App\Enums\Indexing;

enum WorkflowStatus: string
{
    case Starting = 'starting';
    case Processing = 'processing';
    case Failed = 'failed';
    case CompletedWithErrors = 'completed_with_errors';
    case Completed = 'completed';
    case Unknown = 'unknown';
    case Timeout = 'timeout';
    case ReadForNextStep = 'ready_for_next_step';
}