<?php

namespace App\Http\Controllers;

use App\Jobs\Indexing\IndexKnowledgeBase;
use App\Services\Integration\CodeRepository\GitHub\GitHub;

class TestController extends Controller
{
    public function index(GitHub $github)
    {
        $repos = $github->repositories();

        dd($repos->toArray());
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
