<?php

use App\Jobs\Indexing\Communication\Slack\UpdateSlackMessageLinks;
use App\Jobs\Indexing\StartIndexing;
use App\Jobs\Indexing\TaskManagement\Jira\RefreshJiraTokensJob;
use App\Jobs\Infra\SuperviseAvailableMemgraphInstances;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new RefreshJiraTokensJob)->everyThirtyMinutes();
Schedule::job(new UpdateSlackMessageLinks)->everyFiveMinutes();

// TODO: this should be part of the central app (not the tenant app)
Schedule::job(new SuperviseAvailableMemgraphInstances)->everyMinute();

// the server is in UTC. when it's 3AM:
//  - 7PM-11PM (previous day) in the US
//  - 1AM-4AM in Europe
Schedule::job(new StartIndexing)->dailyAt('03:00');