<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndexingWorkflow extends Model
{
    protected $guarded = [];

    public function steps(): HasMany
    {
        return $this->hasMany(IndexingWorkflowStep::class);
    }
}
