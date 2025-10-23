<?php

namespace App\Http\Controllers;

use App\Jobs\Indexing\Communication\GoogleChat\IndexGoogleChat;
use App\Jobs\Indexing\Communication\Slack\IndexSlack;
use App\Jobs\Indexing\KnowledgeGraph\BuildCommunities;
use App\Jobs\Indexing\KnowledgeGraph\BuildKnowledgeGraph;
use App\Jobs\Indexing\KnowledgeGraph\BuildRelatedNodes;
use App\Jobs\Indexing\TaskManagement\Linear\IndexLinear;

class TestController extends Controller
{
    public function index()
    {
        IndexGoogleChat::dispatch();
        // IndexSlack::dispatch();
        // IndexKnowledgeBase::dispatch();

        // BuildKnowledgeGraph::dispatch();

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
