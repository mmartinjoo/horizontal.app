<?php

namespace App\Jobs\Indexing\TaskManagement\Linear;

use App\Services\Integration\TaskManagement\Linear\LinearTokenManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RefreshLinearTokensJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(LinearTokenManager $tokenManager): void
    {
        Log::info('Starting scheduled Linear token refresh job');

        try {
            // Refresh tokens expiring in the next 30 minutes
            $refreshedCount = $tokenManager->refreshTokensExpiringSoon(30);

            Log::info('Scheduled Linear token refresh completed', [
                'refreshed_count' => $refreshedCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Scheduled Linear token refresh failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to mark job as failed
        }
    }
}
