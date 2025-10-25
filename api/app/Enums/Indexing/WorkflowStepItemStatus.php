<?php

namespace App\Enums\Indexing;

enum WorkflowStepItemStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}