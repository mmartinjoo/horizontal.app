<?php

namespace App\Http\Controllers;

use App\Http\Requests\GoogleOAuthCallbackRequest;
use App\Models\GoogleChatIntegration;
use App\Services\Integration\Google\GoogleOAuthService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

abstract class GoogleIntegrationController extends Controller
{
    abstract protected function hasExistingIntegration(): bool;

    public function __construct(
        protected GoogleOAuthService $googleOAuthService,
    ) {
    }

    public function authorize(): JsonResponse
    {
        if ($this->hasExistingIntegration()) {
            return response()->json([
                'error' => 'You already have a Google integration. Please disconnect first.',
            ], 409);
        }

        try {
            $authData = $this->googleOAuthService->generateAuthorizationUrl();

            // Store the state temporarily in cache for validation
            Cache::set('google_oauth_state-' . $authData['random_str'], $authData['random_str'], 600); // 10 minutes

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

    public function callback(GoogleOAuthCallbackRequest $request): JsonResponse
    {
        $code = $request->input('code');
        $state = $request->input('state');
        $randomStr = Str::after($state, 'random_str=');

        // Check for OAuth errors
        if ($request->has('error')) {
            return response()->json([
                'error' => 'OAuth authorization failed: ' . $request->input('error_description', $request->input('error')),
            ], 400);
        }

        // Validate OAuth state to prevent CSRF attacks
        $cacheState = Cache::get('google_oauth_state-' . $randomStr);
        if (!$cacheState || !$this->googleOAuthService->validateState($randomStr, $cacheState)) {
            return response()->json([
                'error' => 'Invalid OAuth state. Please restart the authorization process.',
            ], 400);
        }

        try {
            $tokenData = $this->googleOAuthService->exchangeCodeForToken($code);
            
            $userInfo = $this->googleOAuthService->getUserInfo($tokenData['access_token']);

            $expiresAt = now()->addSeconds($tokenData['expires_in'] ?? 86400); // Default 24 hours

            $integration = GoogleChatIntegration::create([
                'user_name' => $userInfo['displayName'] ?? $userInfo['name'] ?? null,
                'user_email' => $userInfo['email'] ?? null,
                'google_user_id' => $userInfo['id'] ?? null,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => $expiresAt,
                'scope' => isset($tokenData['scope']) ? explode(',', $tokenData['scope']) : ['read', 'write'],
            ]);

            Cache::forget('google_oauth_state-' . $randomStr);

            return response()->json([
                'message' => 'Google integration successfully connected',
                'integration' => [
                    'id' => $integration->id,
                    'user_name' => $integration->user_name,
                    'user_email' => $integration->user_email,
                    'expires_at' => $integration->expires_at,
                    'scope' => $integration->scope,
                ],
            ]);
        } catch (Exception $e) {
            // Clear session data on error
            Cache::forget('google_oauth_state-' . $randomStr);

            return response()->json([
                'error' => 'Failed to complete OAuth authorization: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function status(Request $request): JsonResponse
    {
        $integration = GoogleChatIntegration::first();

        if (!$integration) {
            return response()->json([
                'connected' => false,
                'message' => 'No Google integration found',
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

    public function disconnect(Request $request): JsonResponse
    {
        $integration = GoogleChatIntegration::first();

        if (!$integration) {
            return response()->json([
                'error' => 'No Google integration found',
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
                'message' => 'Google integration successfully disconnected',
                'disconnected_integration' => $integrationDetails,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to disconnect Google integration: ' . $e->getMessage(),
            ], 500);
        }
    }
}