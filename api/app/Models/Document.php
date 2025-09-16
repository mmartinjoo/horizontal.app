<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;
    use HasParticipants;

    protected $guarded = [];

    protected $hidden = [
        'search_vector',
    ];

    protected $casts = [
        'metadata' => 'array',
        'indexed_at' => 'datetime',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(DocumentComment::class);
    }

    public function worklogs(): HasMany
    {
        return $this->hasMany(DocumentWorklog::class);
    }

    protected static function booted()
    {
        static::deleting(function (Document $document) {
            $chunks = DocumentChunk::where('document_id', $document->id)->get();
            foreach ($chunks as $chunk) {
                DocumentParticipant::query()
                    ->where('entity_id', $chunk->id)
                    ->where('entity_type', get_class($chunk))
                    ->delete();
            }
            $comments = DocumentComment::where('document_id', $document->id)->get();
            foreach ($comments as $comment) {
                DocumentParticipant::query()
                    ->where('entity_id', $comment->id)
                    ->where('entity_type', get_class($comment))
                    ->delete();
            }
        });
    }
}
