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

        if (config('features.graph_monitoring.active')) {
            Schedule::job(new MonitorGraph($tenant))
                ->everyTenMinutes();
        }        
    
        if (config('features.automatic_indexing.active')) {
            Schedule::job(new StartIndexing($tenant))
                ->dailyAt(config('features.automatic_indexing.scheduling_daily_at.value'));
        }

        if (config('features.llm_rotation.active')) {
            Schedule::job(new RotateLLMProvider($tenant))
                ->cron(sprintf("*/%d * * * *", config('features.llm_rotation.scheduling_frequency_in_minutes.value')));
        }        

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