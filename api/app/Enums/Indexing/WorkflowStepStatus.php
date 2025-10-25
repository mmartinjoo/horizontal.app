<?php

namespace App\Enums\Indexing;

enum WorkflowStepStatus: string
{
    case Starting = 'starting';
    case Processing = 'processing';
}