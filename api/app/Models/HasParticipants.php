<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasParticipants
{
    public function participants(): MorphToMany
    {
        return $this
            ->morphToMany(Participant::class, 'entity', 'documents_participants')
            ->withPivot('context')
            ->withTimestamps();
    }
}
