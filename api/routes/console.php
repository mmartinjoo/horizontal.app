<?php

use App\Console\Commands\CheckRefreshTokensCommand;
use App\Jobs\Indexing\Communication\GoogleChat\RefreshGoogleChatTokensJob;
use App\Jobs\Indexing\Communication\Slack\RefreshSlackTokensJob;
use App\Jobs\Indexing\Communication\Slack\UpdateSlackMessageLinks;
use App\Jobs\Indexing\StartIndexing;
use App\Jobs\Indexing\Storage\GoogleDrive\RefreshGoogleDriveTokensJob;
use App\Jobs\Indexing\TaskManagement\Jira\RefreshJiraTokensJob;
use App\Jobs\Indexing\TaskManagement\Linear\RefreshLinearTokensJob;
use App\Jobs\Infra\SuperviseAvailableMemgraphInstances;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new RefreshJiraTokensJob)->everyThirtyMinutes();
Schedule::job(new RefreshLinearTokensJob)->everyThirtyMinutes();
Schedule::job(new RefreshSlackTokensJob)->everyThirtyMinutes();
Schedule::job(new RefreshGoogleDriveTokensJob)->everyThirtyMinutes();
Schedule::job(new RefreshGoogleChatTokensJob)->everyThirtyMinutes();
Schedule::command(new CheckRefreshTokensCommand)->hourly();

Schedule::job(new UpdateSlackMessageLinks)->everyFiveMinutes();

// TODO: this should be part of the central app (not the tenant app)
Schedule::job(new SuperviseAvailableMemgraphInstances)->everyMinute();

// the server is in UTC. when it's 3AM:
//  - 7PM-11PM (previous day) in the US
//  - 1AM-4AM in Europe
Schedule::job(new StartIndexing)->dailyAt('03:00');
