<?php

namespace App\Models;

use App\Exceptions\NoEmbeddingsException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model implements Embeddable
{
    use HasFactory;
    use HasEmbedding;
    use HasParticipants;

    protected $guarded = [];

    protected $hidden = [
        'embedding',
        'search_vector',
    ];

    protected $casts = [
        'metadata' => 'array',
        'embedding' => 'array',
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

    public function interactions(): HasMany
    {
        return $this->hasMany(DocumentInteraction::class);
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

                DocumentTopic::query()
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

                DocumentTopic::query()
                    ->where('entity_id', $comment->id)
                    ->where('entity_type', get_class($comment))
                    ->delete();
            }
        });
    }

    public function getEmbeddableContent(): string
    {
        if (!$this->body && !$this->preview) {
            throw new NoEmbeddingsException("No content to embed: " . json_encode($this->attributes));
        }
        if (!$this->body) {
            return $this->preview;
        }
        return $this->body;
    }
}
