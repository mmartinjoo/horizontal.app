<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentComment extends Model
{
    use HasParticipants;

    protected $guarded = [];

    protected $hidden = [
        'search_vector',
    ];

    protected $casts = [
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

    protected static function booted()
    {
        static::deleting(function (DocumentComment $comment) {
            DocumentParticipant::query()
                ->where('entity_id', $comment->id)
                ->where('entity_type', get_class($comment))
                ->delete();
        });
    }
}
