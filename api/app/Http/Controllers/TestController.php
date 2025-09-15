<?php

namespace App\Http\Controllers;

use App\Integrations\Communication\Jira\JiraTokenManager;
use App\Jobs\IndexGoogleDrive;
use App\Jobs\IndexJira;
use App\Jobs\LinkRelatedTopics;
use App\Models\DocumentChunk;
use App\Models\DocumentComment;
use App\Models\JiraProject;
use App\Models\Team;
use App\Models\Topic;
use App\Services\GraphBuilder;
use App\Services\KnowledgeGraph\BuildLouvainCommunities;
use Memgraph as MemgraphClient;
use App\Services\LLM\Embedder;
use App\Services\Search\SearchEngine;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index(BuildLouvainCommunities $communities, GraphBuilder $graphBuilder)
    {
        $graphBuilder->buildComments();
//        $communities->build();
//        $communities->index();

//        $team = Team::where('name', 'Test Company')->firstOrFail();
        /** @var Topic $topic */
//        LinkRelatedTopics::dispatch();
//        IndexJira::dispatch($team);
//        IndexGoogleDrive::dispatch($team);

//        return response('indexing...');
    }

    public function token()
    {
        $team = \App\Models\Team::where(['name' => 'Test Company'])->firstOrFail();

        $user = \App\Models\User::updateOrCreate(
            [
                'email' => 'jira1@example.com',
            ],
            [
                'name' => 'Test User',
                'email' => 'jira1@example.com',
                'password' => bcrypt('password'),
                'team_id' => $team->id
            ],
        );

        $token = $user->createToken('test-token')->plainTextToken;
        dd($token);
    }
}
