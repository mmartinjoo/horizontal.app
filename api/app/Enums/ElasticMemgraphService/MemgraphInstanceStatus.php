<?php

namespace App\Enums\ElasticMemgraphService;

enum MemgraphInstanceStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
}