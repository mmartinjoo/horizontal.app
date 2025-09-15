<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\GraphDB\GraphDB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class BuildRelatedGraphNodes implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
    }

    public function handle(GraphDB $graphDB)
    {

    }

    private function addCommentNodes(GraphDB $graphDB)
    {

    }
}
