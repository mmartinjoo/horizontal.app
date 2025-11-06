<?php

namespace App\Http\Controllers;

use App\Models\GoogleDriveIntegration;
use App\Services\Integration\Google\GoogleOAuthService;
use App\Services\Url;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class GoogleDriveIntegrationController extends GoogleIntegrationController
{
    public function __construct(
        protected GoogleOAuthService $googleOAuthService,
    ) {
    }

    protected function hasExistingIntegration(): bool
    {
        return GoogleDriveIntegration::count() !== 0;
    }

    protected function getStateCacheKey(): string
    {
        return 'google_drive_oauth_state-';
    }

    protected function createIntegration(array $userInfo, array $tokenData, Carbon $expiresAt): Model
    {
        return GoogleDriveIntegration::create([
            'user_name' => $userInfo['displayName'] ?? $userInfo['name'] ?? null,
            'user_email' => $userInfo['email'] ?? null,
            'google_user_id' => $userInfo['id'] ?? null,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_at' => $expiresAt,
            'scope' => isset($tokenData['scope']) ? explode(',', $tokenData['scope']) : ['read', 'write'],
        ]);
    }

    protected function createRedirectUrlToOnboarding(string $errorMessage): string
    {
        return Url::createOnboardingFrontendUrl(
            tenant: tenancy()->tenant, 
            step: 'storage', 
            provider: 'google_drive', 
            errorMessage: $errorMessage,
        );
    }
}