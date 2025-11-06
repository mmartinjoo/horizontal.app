<?php

namespace App\Http\Controllers;

use App\Models\GoogleChatChannel;
use App\Models\GoogleChatIntegration;
use App\Services\Integration\Communication\DataTransferObjects\Channel;
use App\Services\Integration\Communication\GoogleChat\GoogleChat;
use App\Services\Integration\Google\GoogleOAuthService;
use App\Services\Url;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class GoogleChatIntegrationController extends GoogleIntegrationController
{
    public function __construct(
        protected GoogleOAuthService $googleOAuthService,
    ) {
    }

    public function resources()
    {
        $googleChat = app(GoogleChat::class);
        $resources = $googleChat->channels()
            ->map(function (Channel $channel) {
                return [
                    'id' => $channel->externalId,
                    'title' => $channel->name,
                    'description' => '',
                ];
            });

        return response()->json([
            'resources' => $resources,
        ]);
    }

    public function configure(Request $request)
    {
        $googleChat = app(GoogleChat::class);

        $request->validate([
            'selected_resources' => ['required', 'array'],
            'selected_resources.*' => ['required', 'string'],
        ]);

        $selectedResourceIds = $request->get('selected_resources');
        $integration = GoogleChatIntegration::firstOrFail();

        GoogleChatChannel::query()
            ->where('google_chat_integration_id', $integration->id)
            ->delete();

        $resources = $googleChat->channels();

        /** @var Channel $resource */
        foreach ($resources as $resource) {
            if (in_array($resource->externalId, $selectedResourceIds)) {
                GoogleChatChannel::create([
                    'name' => $resource->name,
                    'external_id' => $resource->externalId,
                    'google_chat_integration_id' => $integration->id,
                ]);
            }
        }
    }

    protected function hasExistingIntegration(): bool
    {
        return GoogleChatIntegration::count() !== 0;
    }

    protected function getIntegration(): Model
    {
        return GoogleChatIntegration::first();
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

    protected function createRedirectUrlToOnboarding(string $errorMessage): string
    {
        return Url::createOnboardingCallbackFrontendUrl(
            tenant: tenancy()->tenant, 
            provider: 'google_chat',
            step: 'communication',
        );
    }
}