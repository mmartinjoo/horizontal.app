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
        if (empty($this->answer)) {
            $this->first_token_received_at = now();
            $this->time_to_first_token = now()->timestamp - $this->created_at->timestamp;
        }
        $this->answer .= $content;
        $this->save();
    }

    public function recordTokenUsage(int $inputTokens, int $outputTokens): void
    {
        $this->input_tokens = $inputTokens;
        $this->output_tokens = $outputTokens;
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
