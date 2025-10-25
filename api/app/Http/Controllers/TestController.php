<?php

namespace App\Http\Controllers;

use App\Jobs\Indexing\CodeRepository\GitHub\IndexGitHub;
use App\Jobs\Indexing\Communication\GoogleChat\IndexGoogleChat;
use App\Jobs\Indexing\Communication\Slack\IndexSlack;
use App\Jobs\Indexing\KnowledgeGraph\BuildCommunities;
use App\Jobs\Indexing\KnowledgeGraph\BuildKnowledgeGraph;
use App\Jobs\Indexing\KnowledgeGraph\BuildRelatedNodes;
use App\Jobs\Indexing\TaskManagement\Linear\IndexLinear;
use App\Services\Integration\CodeRepository\GitHub\GitHub;

class TestController extends Controller
{
    public function index(GitHub $github)
    {
        foreach ($github->repositories() as $repository) {
            $prs = $github->pullRequests($repository);
            foreach ($prs as $pr) {
                dump($pr);
                // $comments = $github->pullRequestComments($pr);
                // foreach ($comments as $comment) {
                //     dump($comment);
                // }
            }
        }
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
