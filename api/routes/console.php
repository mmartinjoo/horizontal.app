<?php

use App\Jobs\Indexing\Communication\Slack\UpdateSlackMessageLinks;
use App\Jobs\Indexing\TaskManagement\Jira\RefreshJiraTokensJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new RefreshJiraTokensJob)->everyThirtyMinutes();
Schedule::job(new UpdateSlackMessageLinks)->everyFiveMinutes();