<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentParticipant extends Model
{
    protected $table = 'documents_participants';

    protected $guarded = [];

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
