<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Participant extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function document_chunks(): MorphToMany
    {
        return $this->morphToMany(DocumentChunk::class, 'entity', 'documents_participants');
    }

    public function document_comments(): MorphToMany
    {
        return $this->morphToMany(DocumentComment::class, 'entity', 'documents_participants');
    }

    public function entity(): MorphToMany
    {
        return $this->morphToMany(Model::class, 'entity', 'documents_participants');
    }

    public static function getOrCreate(string $name): self
    {
        return Participant::updateOrCreate(
            [
                'slug' => Str::slug($name),
                'type' => 'person',
            ],
            [
                'slug' => Str::slug($name),
                'name' => $name,
                'type' => 'person',
            ],
        );
    }
}
