<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class IndexingWorkflow extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(IndexingWorkflowStep::class);
    }

    public function buckets(): HasManyThrough
    {
        return $this->hasManyThrough(
            related: IndexingWorkflowStepBucket::class, 
            through: IndexingWorkflowStep::class,
        );
    }
}
