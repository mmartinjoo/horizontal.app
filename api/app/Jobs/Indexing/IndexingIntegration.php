<?php

namespace App\Jobs\Indexing;

use Illuminate\Support\LazyCollection;

interface IndexingIntegration
{   
    public function getAuthorizedResources(): LazyCollection;
    public function getIndexableResources(LazyCollection $resources): LazyCollection;
}