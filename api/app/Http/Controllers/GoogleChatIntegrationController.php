<?php

namespace App\Http\Controllers;

use App\Models\GoogleChatIntegration;
use App\Services\Integration\Google\GoogleOAuthService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class GoogleChatIntegrationController extends GoogleIntegrationController
{
    public function __construct(
        protected GoogleOAuthService $googleOAuthService,
    ) {
    }

    protected function hasExistingIntegration(): bool
    {
        return GoogleChatIntegration::count() !== 0;
    }

    protected function getStateCacheKey(): string
    {
        return 'google_chat_oauth_state-';
    }

    protected function createIntegration(array $userInfo, array $tokenData, Carbon $expiresAt): Model
    {
        return GoogleChatIntegration::create([
            'user_name' => $userInfo['displayName'] ?? $userInfo['name'] ?? null,
            'user_email' => $userInfo['email'] ?? null,
            'google_user_id' => $userInfo['id'] ?? null,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_at' => $expiresAt,
            'scope' => isset($tokenData['scope']) ? explode(',', $tokenData['scope']) : ['read', 'write'],
        ]);
    }
}