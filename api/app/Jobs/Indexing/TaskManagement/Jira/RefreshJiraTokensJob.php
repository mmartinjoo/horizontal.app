<?php

namespace App\Jobs\Indexing\TaskManagement\Jira;

use App\Models\Tenant;
use App\Services\Integration\TaskManagement\Jira\JiraTokenManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RefreshJiraTokensJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private Tenant $tenant)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(JiraTokenManager $tokenManager): void
    {
        tenancy()->initialize($this->tenant);

        Log::info('Starting scheduled Jira token refresh job');

        try {
            // Refresh tokens expiring in the next 30 minutes
            $refreshedCount = $tokenManager->refreshTokensExpiringSoon(30);

            Log::info('Scheduled Jira token refresh completed', [
                'refreshed_count' => $refreshedCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Scheduled Jira token refresh failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to mark job as failed
        }
    }
}
