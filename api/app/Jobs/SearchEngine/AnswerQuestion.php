<?php

namespace App\Jobs\SearchEngine;

use App\Models\Question;
use App\Services\SearchEngine\SearchEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnswerQuestion implements ShouldQueue
{
    use Queueable;

    public function __construct(private int $questionId)
    {
        $this->onQueue('question');
    }

    public function handle(SearchEngine $searchEngine)
    {
        $question = Question::findOrFail($this->questionId);
        $searchEngine->graphRAG($question);
    }
}