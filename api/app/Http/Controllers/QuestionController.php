<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Services\GraphDB\GraphDB;
use App\Services\LLM\Embedder;
use App\Services\SearchEngine\SearchEngine;
use Illuminate\Http\Request;

class QuestionController
{
    public function ask(Request $request, SearchEngine $searchEngine, Embedder $embedder, GraphDB $graphDB)
    {
        $question = Question::create([
            'user_id' => $request->user()->id,
            'question' => $request->input('question'),
        ]);

        dd($searchEngine->graphRAG($question));
    }
}
