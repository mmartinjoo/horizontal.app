<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DocumentComment extends Model implements Embeddable
{
    use HasEmbedding;
    use HasParticipants;

    protected $guarded = [];

    protected $hidden = [
        'embedding',
        'search_vector',
    ];

    protected $casts = [
        'embedding' => 'array',
        'metadata' => 'array',
        'commented_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'author_id');
    }

    public function getEmbeddableContent(): string
    {
        return $this->author->name . ' commented: ' . $this->body;
    }
}
