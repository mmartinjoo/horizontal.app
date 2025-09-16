<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentWorklog extends Model implements Embeddable
{
    use HasEmbedding;

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'embedding' => 'array',
    ];

    public function getEmbeddableContent(): string
    {
        return $this->description;
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Participant::class, 'author_id');
    }
}
