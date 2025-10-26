<?php

namespace App\Http\Controllers;

use App\Jobs\Indexing\CodeRepository\GitHub\IndexGitHub;
use App\Jobs\Indexing\Communication\GoogleChat\IndexGoogleChat;
use App\Jobs\Indexing\Communication\Slack\IndexSlack;
use App\Jobs\Indexing\KnowledgeGraph\BuildCommunities;
use App\Jobs\Indexing\KnowledgeGraph\BuildKnowledgeGraph;
use App\Jobs\Indexing\KnowledgeGraph\BuildRelatedNodes;
use App\Jobs\Indexing\Storage\GoogleDrive\IndexGoogleDrive;
use App\Jobs\Indexing\TaskManagement\Linear\IndexLinear;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowStep;
use App\Services\Indexing\Orchestrator\Orchestrator;
use App\Services\Integration\CodeRepository\GitHub\GitHub;
use App\Services\Integration\TaskManagement\Linear\Linear;

class TestController extends Controller
{
    public function index(Linear $linear)
    {
        foreach ($linear->projects() as $project) {
            dump($project);
            foreach ($linear->issues($project) as $issue) {
                dump($issue);
                foreach ($linear->comments($issue) as $comment)
                    dump($comment);
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
