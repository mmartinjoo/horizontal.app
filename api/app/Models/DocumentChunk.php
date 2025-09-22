<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentChunk extends Model
{
    use HasParticipants;

    protected $guarded = [];

    protected $hidden = [
        'search_vector',
        'embedding',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }
}
