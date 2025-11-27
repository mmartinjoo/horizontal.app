<?php

namespace App\Jobs\Indexing\Communication\Slack;

use App\Models\Tenant;
use App\Services\Integration\Communication\Slack\SlackTokenManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RefreshSlackTokensJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private Tenant $tenant)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(SlackTokenManager $tokenManager): void
    {
        tenancy()->initialize($this->tenant);
        
        Log::info('Starting scheduled Slack token refresh job');

        try {
            // Refresh tokens expiring in the next 30 minutes
            $refreshedCount = $tokenManager->refreshTokensExpiringSoon(30);

            Log::info('Scheduled Slack token refresh completed', [
                'refreshed_count' => $refreshedCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Scheduled Slack token refresh failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to mark job as failed
        }
    }
}
