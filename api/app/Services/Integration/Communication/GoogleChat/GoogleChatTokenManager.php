<?php

namespace App\Services\Integration\Communication\GoogleChat;

use App\Models\GoogleChatIntegration;
use App\Services\Integration\Google\GoogleChatOAuthService;
use Illuminate\Support\Facades\Log;

class GoogleChatTokenManager
{
    public function __construct(
        private GoogleChatOAuthService $googleChatOAuthService
    ) {}

    public function ensureValidToken(GoogleChatIntegration $integration): bool
    {
        if ($integration->hasValidToken()) {
            return true;
        }

        if (! $integration->refresh_token) {
            Log::warning('Google Chat integration missing refresh token', [
                'integration_id' => $integration->id,
            ]);

            return false;
        }

        return $this->refreshToken($integration);
    }

    public function refreshToken(GoogleChatIntegration $integration): bool
    {
        if (! $integration->refresh_token) {
            Log::error('Cannot refresh token: missing refresh token', [
                'integration_id' => $integration->id,
            ]);

            return false;
        }

        try {
            Log::info('Refreshing Google Chat access token', [
                'integration_id' => $integration->id,
            ]);

            $tokenData = $this->googleChatOAuthService->refreshAccessToken($integration->refresh_token);

            $this->updateTokens($integration, $tokenData);

            Log::info('Google Chat access token refreshed successfully', [
                'integration_id' => $integration->id,
                'expires_at' => $integration->expires_at,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to refresh Google Chat access token', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function refreshExpiredTokens(): int
    {
        $expiredIntegrations = GoogleChatIntegration::where('expires_at', '<=', now())
            ->whereNotNull('refresh_token')
            ->get();

        $refreshedCount = 0;

        foreach ($expiredIntegrations as $integration) {
            if ($this->refreshToken($integration)) {
                $refreshedCount++;
            }
        }

        if ($refreshedCount > 0) {
            Log::info('Batch token refresh completed', [
                'refreshed_count' => $refreshedCount,
                'total_expired' => $expiredIntegrations->count(),
            ]);
        }

        return $refreshedCount;
    }

    public function refreshTokensExpiringSoon(int $minutesBeforeExpiry = 30): int
    {
        $soonToExpireIntegrations = GoogleChatIntegration::where('expires_at', '<=', now()->addMinutes($minutesBeforeExpiry))
            ->where('expires_at', '>', now())
            ->whereNotNull('refresh_token')
            ->get();

        $refreshedCount = 0;

        foreach ($soonToExpireIntegrations as $integration) {
            if ($this->refreshToken($integration)) {
                $refreshedCount++;
            }
        }

        if ($refreshedCount > 0) {
            Log::info('Proactive token refresh completed', [
                'refreshed_count' => $refreshedCount,
                'total_expiring_soon' => $soonToExpireIntegrations->count(),
                'minutes_before_expiry' => $minutesBeforeExpiry,
            ]);
        }

        return $refreshedCount;
    }

    public function retryFailedRefresh(GoogleChatIntegration $integration, int $maxRetries = 3): bool
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            $attempt++;

            try {
                return $this->refreshToken($integration);
            } catch (\Exception $e) {
                $lastException = $e;

                Log::warning('Token refresh retry failed', [
                    'integration_id' => $integration->id,
                    'attempt' => $attempt,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < $maxRetries) {
                    // Wait before retry (exponential backoff)
                    sleep(pow(2, $attempt - 1));
                }
            }
        }

        Log::error('Token refresh failed after all retries', [
            'integration_id' => $integration->id,
            'total_attempts' => $attempt,
            'final_error' => $lastException?->getMessage(),
        ]);

        return false;
    }

    private function updateTokens(GoogleChatIntegration $integration, array $tokenData): void
    {
        $expiresAt = now()->addSeconds($tokenData['expires_in'] ?? 3600);

        $updateData = [
            'access_token' => $tokenData['access_token'],
            'expires_at' => $expiresAt,
        ];

        // Google may not always return a new refresh_token
        // Only update if provided (otherwise keep existing one)
        if (isset($tokenData['refresh_token'])) {
            $updateData['refresh_token'] = $tokenData['refresh_token'];
        }

        // Update scope if provided
        if (isset($tokenData['scope'])) {
            $updateData['scope'] = explode(' ', $tokenData['scope']);
        }

        $integration->update($updateData);
    }

    public function revokeToken(GoogleChatIntegration $integration): bool
    {
        if (! $integration->access_token) {
            Log::warning('No access token to revoke', [
                'integration_id' => $integration->id,
            ]);

            return true; // Already revoked/missing
        }

        try {
            Log::info('Revoking Google Chat access token', [
                'integration_id' => $integration->id,
            ]);

            $this->googleChatOAuthService->revokeToken($integration->access_token);

            $this->clearTokens($integration);

            Log::info('Google Chat access token revoked successfully', [
                'integration_id' => $integration->id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to revoke Google Chat access token', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function cleanupExpiredTokens(int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);

        $expiredIntegrations = GoogleChatIntegration::where('expires_at', '<=', $cutoffDate)
            ->whereNull('refresh_token')
            ->get();

        $cleanedCount = 0;

        foreach ($expiredIntegrations as $integration) {
            try {
                Log::info('Cleaning up expired integration', [
                    'integration_id' => $integration->id,
                    'expired_since' => $integration->expires_at,
                ]);

                $integration->delete();
                $cleanedCount++;
            } catch (\Exception $e) {
                Log::error('Failed to cleanup expired integration', [
                    'integration_id' => $integration->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($cleanedCount > 0) {
            Log::info('Expired token cleanup completed', [
                'cleaned_count' => $cleanedCount,
                'cutoff_date' => $cutoffDate,
                'days_old' => $daysOld,
            ]);
        }

        return $cleanedCount;
    }

    public function cleanupInvalidTokens(): int
    {
        $integrations = GoogleChatIntegration::whereNull('access_token')
            ->orWhere('access_token', '')
            ->get();

        $cleanedCount = 0;

        foreach ($integrations as $integration) {
            try {
                Log::info('Cleaning up invalid integration', [
                    'integration_id' => $integration->id,
                ]);

                $integration->delete();
                $cleanedCount++;
            } catch (\Exception $e) {
                Log::error('Failed to cleanup invalid integration', [
                    'integration_id' => $integration->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($cleanedCount > 0) {
            Log::info('Invalid token cleanup completed', [
                'cleaned_count' => $cleanedCount,
            ]);
        }

        return $cleanedCount;
    }

    private function clearTokens(GoogleChatIntegration $integration): void
    {
        $integration->update([
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
        ]);
    }
}
