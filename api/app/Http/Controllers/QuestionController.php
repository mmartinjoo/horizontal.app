<?php

namespace App\Http\Controllers;

use App\Jobs\SearchEngine\AnswerQuestion;
use App\Models\Question;
use App\Services\GraphDB\GraphDB;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QuestionController
{
    public function ask(Request $request, GraphDB $graphDB)
    {
        $count = $graphDB->run("match (n:Community) return count(n) as count;")[0]['count'];
        if ($count === 0) {
            return response('Your data is being indexed... Please try again later.', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $llmProvider = tenancy()->tenant->llm_provider;
        $question = Question::create([
            'user_id' => $request->user()->id,
            'question' => $request->input('question'),
            'llm_model' => config("llm.connections.{$llmProvider}.model"),
        ]);

        AnswerQuestion::dispatch($question->id)
            ->onQueue('question');

        return response()->json([
            'question' => $question,
        ], Response::HTTP_ACCEPTED);
    }

    public function show(Question $question)
    {
        return [
            'id' => $question->id,
            'question' => $question->question,
            'answer' => $question->answer,
            'relevant_documents' => $question->relevant_documents,
            'answered_at' => $question->answered_at,
        ];
    }
}
