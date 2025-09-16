<?php

use App\Jobs\Indexing\TaskManagement\Jira\RefreshJiraTokensJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule Jira token refresh every 30 minutes
Schedule::job(new RefreshJiraTokensJob)->everyThirtyMinutes();
