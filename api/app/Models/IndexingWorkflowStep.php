<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndexingWorkflowStep extends Model
{
    protected $table = 'indexing_workflow_steps';

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(IndexingWorkflowStepItem::class);
    }
}
