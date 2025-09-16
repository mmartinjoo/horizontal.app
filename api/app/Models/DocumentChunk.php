<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentChunk extends Model implements Embeddable
{
    use HasEmbedding;
    use HasParticipants;

    protected $guarded = [];

    protected $casts = [
        'embedding' => 'array',
    ];

    protected $hidden = [
        'embedding',
        'search_vector',
    ];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function getEmbeddableContent(): string
    {
        return $this->body;
    }
}
