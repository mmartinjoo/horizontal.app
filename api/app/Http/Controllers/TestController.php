<?php

namespace App\Http\Controllers;

use App\Jobs\IndexGoogleDrive;
use App\Models\Team;
use App\Services\GraphBuilder;

class TestController extends Controller
{
    public function index(GraphBuilder $graphBuilder)
    {
//        $graphBuilder->buildCommunities();
//        $graphBuilder->buildKG();
//        $graphBuilder->buildComments();
//        $graphBuilder->buildParticipants();
//        $graphBuilder->buildWorklogs();
//        $communities->build();
//        $communities->index();

        $team = Team::where('name', 'Test Company')->firstOrFail();
//        IndexJira::dispatch($team);
        IndexGoogleDrive::dispatch($team);

        return response('indexing...');
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
