<?php

use App\Jobs\Indexing\Communication\Slack\UpdateSlackMessageLinks;
use App\Jobs\Indexing\TaskManagement\Jira\RefreshJiraTokensJob;
use App\Jobs\Infra\SuperviseAvailableMemgraphInstances;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new RefreshJiraTokensJob)->everyThirtyMinutes();
Schedule::job(new UpdateSlackMessageLinks)->everyFiveMinutes();
Schedule::job(new SuperviseAvailableMemgraphInstances)->everyMinute();