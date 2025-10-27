<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class IndexingWorkflowStepItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
    ];

    public function indexing_workflow_step()
    {
        return $this->belongsTo(IndexingWorkflowStep::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): HasManyThrough
    {
        return $this->hasManyThrough(User::class, IndexingWorkflowStep::class);
    }
}
