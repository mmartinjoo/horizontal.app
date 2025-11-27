<?php

namespace App\Jobs\Indexing\Storage\GoogleDrive;

use App\Models\Tenant;
use App\Services\Integration\Storage\GoogleDrive\GoogleDriveTokenManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RefreshGoogleDriveTokensJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private Tenant $tenant)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(GoogleDriveTokenManager $tokenManager): void
    {
        tenancy()->initialize($this->tenant);

        Log::info('Starting scheduled Google Drive token refresh job');

        try {
            // Refresh tokens expiring in the next 30 minutes
            $refreshedCount = $tokenManager->refreshTokensExpiringSoon(30);

            Log::info('Scheduled Google Drive token refresh completed', [
                'refreshed_count' => $refreshedCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Scheduled Google Drive token refresh failed', [
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to mark job as failed
        }
    }
}
