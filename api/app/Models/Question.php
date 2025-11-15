<?php

namespace App\Models;

use App\Services\LLM\StreamWriter;
use Illuminate\Database\Eloquent\Model;

class Question extends Model implements StreamWriter
{
    protected $guarded = [];

    protected $casts = [
        'answer' => 'array',
        'relevant_documents' => 'array',
        'relevant_graph_paths' => 'array',
    ];

    public function write(string $content): void
    {
        $this->answer .= $content;
        $this->save();
    }

    public function finished(): void
    {
        $this->answered_at = now();
        if ($this->created_at) {
            $this->time_spent = (int) $this->created_at->diffInSeconds(now());
        }
        $this->save();
    }
}
