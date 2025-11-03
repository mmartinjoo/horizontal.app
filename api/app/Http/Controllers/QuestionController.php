<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Services\GraphDB\GraphDB;
use App\Services\LLM\Embedder;
use App\Services\SearchEngine\SearchEngine;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QuestionController
{
    public function ask(Request $request, SearchEngine $searchEngine, GraphDB $graphDB)
    {
        $count = $graphDB->run("match (n:Community) return count(n) as count;")[0]['count'];
        if ($count === 0) {
            return response('Your data is being indexed... Please try again later.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $question = Question::create([
            'user_id' => $request->user()->id,
            'question' => $request->input('question'),
        ]);

        return $searchEngine->graphRAG($question);
    }
}
