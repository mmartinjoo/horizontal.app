<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndexingWorkflowStep extends Model
{
    protected $table = 'indexing_workflow_steps';

    protected $guarded = [];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(IndexingWorkflow::class, 'indexing_workflow_id');
    }

    public function buckets(): HasMany
    {
        return $this->hasMany(IndexingWorkflowStepBucket::class);
    }
}
