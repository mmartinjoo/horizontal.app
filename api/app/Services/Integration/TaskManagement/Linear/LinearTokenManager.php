<?php

namespace App\Services\Integration\TaskManagement\Linear;

use App\Models\LinearIntegration;
use Illuminate\Support\Facades\Log;

class LinearTokenManager
{
    public function __construct(
        private LinearOAuthService $linearOAuthService
    ) {}

    public function ensureValidToken(LinearIntegration $integration): bool
    {
        if ($integration->hasValidToken()) {
            return true;
        }

        if (!$integration->refresh_token) {
            Log::warning('Linear integration missing refresh token', [
                'integration_id' => $integration->id,
            ]);
            return false;
        }

        return $this->refreshToken($integration);
    }

    public function refreshToken(LinearIntegration $integration): bool
    {
        if (!$integration->refresh_token) {
            Log::error('Cannot refresh token: missing refresh token', [
                'integration_id' => $integration->id,
            ]);
            return false;
        }

        try {
            Log::info('Refreshing Linear access token', [
                'integration_id' => $integration->id,
            ]);

            $tokenData = $this->linearOAuthService->refreshAccessToken($integration->refresh_token);

            $this->updateTokens($integration, $tokenData);

            Log::info('Linear access token refreshed successfully', [
                'integration_id' => $integration->id,
                'expires_at' => $integration->expires_at,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to refresh Linear access token', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function refreshExpiredTokens(): int
    {
        $expiredIntegrations = LinearIntegration::where('expires_at', '<=', now())
            ->whereNotNull('refresh_token')
            ->get();

        $refreshedCount = 0;

        foreach ($expiredIntegrations as $integration) {
            if ($this->refreshToken($integration)) {
                $refreshedCount++;
            }
        }

        if ($refreshedCount > 0) {
            Log::info('Batch Linear token refresh completed', [
                'refreshed_count' => $refreshedCount,
                'total_expired' => $expiredIntegrations->count(),
            ]);
        }

        return $refreshedCount;
    }

    public function refreshTokensExpiringSoon(int $minutesBeforeExpiry = 30): int
    {
        $soonToExpireIntegrations = LinearIntegration::where('expires_at', '<=', now()->addMinutes($minutesBeforeExpiry))
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
            Log::info('Proactive Linear token refresh completed', [
                'refreshed_count' => $refreshedCount,
                'total_expiring_soon' => $soonToExpireIntegrations->count(),
                'minutes_before_expiry' => $minutesBeforeExpiry,
            ]);
        }

        return $refreshedCount;
    }

    public function revokeToken(LinearIntegration $integration): bool
    {
        if (!$integration->access_token) {
            Log::warning('No Linear access token to revoke', [
                'integration_id' => $integration->id,
            ]);
            return true;
        }

        try {
            Log::info('Revoking Linear access token', [
                'integration_id' => $integration->id,
            ]);

            $this->clearTokens($integration);

            Log::info('Linear access token revoked successfully', [
                'integration_id' => $integration->id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to revoke Linear access token', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function updateTokens(LinearIntegration $integration, array $tokenData): void
    {
        $expiresAt = now()->addSeconds($tokenData['expires_in'] ?? 86400); // Default 24 hours

        $updateData = [
            'access_token' => $tokenData['access_token'],
            'expires_at' => $expiresAt,
        ];

        // Update refresh token if a new one is provided (token rotation)
        if (isset($tokenData['refresh_token'])) {
            $updateData['refresh_token'] = $tokenData['refresh_token'];
        }

        // Update scope if provided
        if (isset($tokenData['scope'])) {
            $updateData['scope'] = explode(',', $tokenData['scope']);
        }

        $integration->update($updateData);
    }

    private function clearTokens(LinearIntegration $integration): void
    {
        $integration->update([
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
        ]);
    }
}