<?php

namespace App\Enums\Indexing;

enum WorkflowStatus: string
{
    case Starting = 'starting';
    case Processing = 'processing';
}