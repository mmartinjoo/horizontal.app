<?php

namespace App\Http\Controllers;

use App\Http\Requests\SlackOAuthCallbackRequest;
use App\Models\SlackChannel;
use App\Models\SlackIntegration;
use App\Services\Integration\Communication\DataTransferObjects\Channel;
use App\Services\Integration\Communication\Slack\Slack;
use App\Services\Integration\Communication\Slack\SlackOAuthService;
use App\Services\Url;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class SlackIntegrationController extends Controller
{
    public function __construct(
        private SlackOAuthService $slackOAuthService
    ) {
    }

    public function authorize(): JsonResponse
    {
        $existingIntegration = SlackIntegration::first();
        if ($existingIntegration) {
            return response()->json([
                'error' => 'You already have a Slack integration. Please disconnect first.',
            ], 409);
        }

        try {
            $authData = $this->slackOAuthService->generateAuthorizationUrl();

            // Store the state temporarily in cache for validation
            Cache::set('slack_oauth_state-' . $authData['random_str'], $authData['random_str'], 600); // 10 minutes

            return response()->json([
                'authorization_url' => $authData['authorization_url'],
                'random_str' => $authData['random_str'],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to generate authorization URL: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function callback(SlackOAuthCallbackRequest $request)
    {
        $errorMessage = '';
        try {
            $code = $request->input('code');
            $state = $request->input('state');
            $randomStr = Url::extractKeyFromState($state, 'random_str');

            // Check for OAuth errors
            if ($request->has('error')) {
                $errorMessage = 'OAuth authorization failed: ' . $request->input('error_description', $request->input('error'));
                throw new Exception($errorMessage); 
            }

            // Validate OAuth state to prevent CSRF attacks
            $cacheState = Cache::get('slack_oauth_state-' . $randomStr);

            if (!$cacheState || !$this->slackOAuthService->validateState($randomStr, $cacheState)) {
                $errorMessage = 'Invalid OAuth state. Please restart the authorization process.';
                throw new Exception($errorMessage);
            }

            // Exchange authorization code for access token
            $tokenData = $this->slackOAuthService->exchangeCodeForToken($code);

            $userId = $tokenData['authed_user']['id'];

            // Get user information
            $userInfo = $this->slackOAuthService->getUserInfo($tokenData['access_token'], $userId);

            // Calculate token expiration time
            $expiresAt = now()->addSeconds($tokenData['expires_in'] ?? 86400); // Default 24 hours

            SlackIntegration::create([
                'user_name' => $userInfo['profile']['real_name'] ?? $userInfo['name'] ?? null,
                'user_email' => Arr::get($userInfo, 'profile.email'),
                'slack_user_id' => $userInfo['id'] ?? null,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => $expiresAt,
                'scope' => isset($tokenData['scope']) ? explode(',', $tokenData['scope']) : ['read', 'write'],
            ]);
        } finally {
            Cache::forget('slack_oauth_state-' . $randomStr);
            return redirect()->away(Url::createOnboardingCallbackFrontendUrl(
                tenant: tenancy()->tenant, 
                provider: 'slack',
                step: 'communication',
            ));
        }
    }

    public function status(): JsonResponse
    {
        $integration = SlackIntegration::first();

        if (!$integration) {
            return response()->json([
                'connected' => false,
                'message' => 'No Slack integration found',
            ]);
        }

        $isTokenValid = $integration->hasValidToken();
        $isExpired = $integration->isTokenExpired();

        return response()->json([
            'connected' => true,
            'integration' => [
                'id' => $integration->id,
                'user_name' => $integration->user_name,
                'user_email' => $integration->user_email,
                'expires_at' => $integration->expires_at,
                'scope' => $integration->scope,
                'is_token_valid' => $isTokenValid,
                'is_expired' => $isExpired,
                'created_at' => $integration->created_at,
                'updated_at' => $integration->updated_at,
            ],
            'status' => $isTokenValid ? 'active' : ($isExpired ? 'expired' : 'invalid'),
        ]);
    }

    public function disconnect(): JsonResponse
    {
        $integration = SlackIntegration::first();
        if (!$integration) {
            return response()->json([
                'error' => 'No Slack integration found',
            ], 404);
        }

        try {
            // Store integration details for response before deletion
            $integrationDetails = [
                'id' => $integration->id,
                'user_name' => $integration->user_name,
                'user_email' => $integration->user_email,
                'created_at' => $integration->created_at,
            ];

            // Delete the integration (tokens will be automatically cleaned up)
            $integration->delete();

            return response()->json([
                'message' => 'Slack integration successfully disconnected',
                'disconnected_integration' => $integrationDetails,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to disconnect Slack integration: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function resources(Slack $slack)
    {
        $resources = $slack->channels()
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

    public function configure(Request $request, Slack $slack)
    {
        $request->validate([
            'selected_resources' => ['required', 'array'],
            'selected_resources.*' => ['required', 'string'],
        ]);

        $selectedResourceIds = $request->get('selected_resources');
        $integration = SlackIntegration::firstOrFail();

        SlackChannel::query()
            ->where('slack_integration_id', $integration->id)
            ->delete();

        $resources = $slack->channels();

        /** @var Channel $resource */
        foreach ($resources as $resource) {
            if (in_array($resource->externalId, $selectedResourceIds)) {
                SlackChannel::create([
                    'name' => $resource->name,
                    'external_id' => $resource->externalId,
                    'slack_integration_id' => $integration->id,
                ]);
            }
        }
    }
}