<?php

namespace App\Http\Controllers;

use App\Jobs\Indexing\CodeRepository\GitHub\IndexGitHub;
use App\Jobs\Indexing\Communication\GoogleChat\IndexGoogleChat;
use App\Jobs\Indexing\Communication\Slack\IndexSlack;
use App\Jobs\Indexing\KnowledgeGraph\BuildCommunities;
use App\Jobs\Indexing\KnowledgeGraph\BuildKnowledgeGraph;
use App\Jobs\Indexing\KnowledgeGraph\BuildRelatedNodes;
use App\Jobs\Indexing\Storage\GoogleDrive\IndexGoogleDrive;
use App\Jobs\Indexing\Supervisor\SuperviseStuckBuckets;
use App\Jobs\Indexing\TaskManagement\Linear\IndexLinear;
use App\Jobs\LLM\RotateLLMProvider;
use App\Models\IndexingWorkflow;
use App\Models\IndexingWorkflowStep;
use App\Models\IndexingWorkflowStepItem;
use App\Services\GraphDB\GraphDB;
use App\Services\Indexing\Orchestrator\Orchestrator;
use App\Services\Indexing\Orchestrator\Supervisor\StuckBucketSupervisor;
use App\Services\Integration\CodeRepository\GitHub\GitHub;
use App\Services\Integration\Communication\Slack\Slack;
use App\Services\Integration\Storage\GoogleDrive\GoogleDrive;
use App\Services\Integration\TaskManagement\Jira\Jira;
use App\Services\Integration\TaskManagement\Linear\Linear;
use App\Services\LLM\Fireworks;
use App\Services\LLM\LLM;
use App\Services\LLM\LLMRotation;
use App\Services\Url;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index()
    {
        RotateLLMProvider::dispatch();
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
