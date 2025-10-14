<?php

namespace App\Http\Controllers;

use App\Jobs\Indexing\Communication\Slack\IndexSlack;
use App\Jobs\Indexing\IndexKnowledgeBase;

class TestController extends Controller
{
    public function index()
    {
        IndexSlack::dispatch();
//        IndexKnowledgeBase::dispatch();

        return response('indexing...');
    }

    public function token()
    {
        $user = \App\Models\User::updateOrCreate(
            [
                'email' => 'jira1@example.com',
            ],
            [
                'name' => 'Test User',
                'email' => 'jira1@example.com',
                'password' => bcrypt('password'),
            ],
        );

        $token = $user->createToken('test-token')->plainTextToken;
        dd($token);
    }
}
