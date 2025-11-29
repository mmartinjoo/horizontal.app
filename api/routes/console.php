<?php

use App\Jobs\Indexing\Communication\GoogleChat\RefreshGoogleChatTokensJob;
use App\Jobs\Indexing\Communication\Slack\RefreshSlackTokensJob;
use App\Jobs\Indexing\Communication\Slack\UpdateSlackMessageLinks;
use App\Jobs\Indexing\MonitorGraph;
use App\Jobs\Indexing\StartIndexing;
use App\Jobs\Indexing\Storage\GoogleDrive\RefreshGoogleDriveTokensJob;
use App\Jobs\Indexing\TaskManagement\Jira\RefreshJiraTokensJob;
use App\Jobs\Indexing\TaskManagement\Linear\RefreshLinearTokensJob;
use App\Jobs\Infra\SuperviseAvailableMemgraphInstances;
use App\Jobs\Infra\SuperviseQueues;
use App\Jobs\LLM\RotateLLMProvider;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SuperviseAvailableMemgraphInstances)->everyMinute();

if (config('features.queue_based_auto_scaling.active')) {
    Schedule::job(new SuperviseQueues)->everyTenSeconds();
}

// tenant-aware jobs
// it needs the database check because `composer dump-autoload` invokes it
// which is executed in the Dockerfile
try {
    DB::connection()->getPdo();

    foreach (Tenant::all() as $tenant) {
        Schedule::job(new RefreshJiraTokensJob($tenant))->everyThirtyMinutes();
        Schedule::job(new RefreshLinearTokensJob($tenant))->everyThirtyMinutes();
        Schedule::job(new RefreshSlackTokensJob($tenant))->everyThirtyMinutes();
        Schedule::job(new RefreshGoogleDriveTokensJob($tenant))->everyThirtyMinutes();
        Schedule::job(new RefreshGoogleChatTokensJob($tenant))->everyThirtyMinutes();    

        Schedule::job(new UpdateSlackMessageLinks($tenant))->everyFiveMinutes();

        // the server is in UTC. when it's 3AM:
        //  - 7PM-11PM (previous day) in the US
        //  - 1AM-4AM in Europe
        Schedule::job(new StartIndexing($tenant))->dailyAt('03:00');
        Schedule::job(new MonitorGraph($tenant))->everyTenMinutes();

        Schedule::job(new RotateLLMProvider($tenant))->everyMinute();

        $integration = [
            'google_drive',
            'github',
            'slack',
            'linear',
            'google_chat',
            'jira',
        ];
        foreach ($integration as $integration) {
            Schedule::command("integrations:check-refresh-tokens --tenant={$tenant->id} --integration={$integration} --show-details")->hourly();
        }
    }
} catch (Exception $e) {
}