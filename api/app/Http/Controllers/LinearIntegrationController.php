<?php

namespace App\Http\Controllers;

use App\Http\Requests\LinearOAuthCallbackRequest;
use App\Models\LinearIntegration;
use App\Services\Integration\TaskManagement\Linear\LinearOAuthService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LinearIntegrationController extends Controller
{
    public function __construct(
        private LinearOAuthService $linearOAuthService
    ) {
    }

    public function authorize(): JsonResponse
    {
        $existingIntegration = LinearIntegration::first();
        if ($existingIntegration) {
            return response()->json([
                'error' => 'You already have a Linear integration. Please disconnect first.',
            ], 409);
        }

        try {
            $authData = $this->linearOAuthService->generateAuthorizationUrl();

            // Store the state temporarily in cache for validation
            Cache::set('linear_oauth_state-' . $authData['state'], $authData['state'], 600); // 10 minutes

            return response()->json([
                'authorization_url' => $authData['authorization_url'],
                'state' => $authData['state'],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to generate authorization URL: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function callback(LinearOAuthCallbackRequest $request): JsonResponse
    {
        $code = $request->input('code');
        $state = $request->input('state');

        // Check for OAuth errors
        if ($request->has('error')) {
            return response()->json([
                'error' => 'OAuth authorization failed: ' . $request->input('error_description', $request->input('error')),
            ], 400);
        }

        // Validate OAuth state to prevent CSRF attacks
        $cacheState = Cache::get('linear_oauth_state-' . $state);

        if (!$cacheState || !$this->linearOAuthService->validateState($state, $cacheState)) {
            return response()->json([
                'error' => 'Invalid OAuth state. Please restart the authorization process.',
            ], 400);
        }

        try {
            // Exchange authorization code for access token
            $tokenData = $this->linearOAuthService->exchangeCodeForToken($code);

            // Get user information
            $userInfo = $this->linearOAuthService->getUserInfo($tokenData['access_token']);

            // Calculate token expiration time
            $expiresAt = now()->addSeconds($tokenData['expires_in'] ?? 86400); // Default 24 hours

            // Create the Linear integration record
            $integration = LinearIntegration::create([
                'user_name' => $userInfo['displayName'] ?? $userInfo['name'] ?? null,
                'user_email' => $userInfo['email'] ?? null,
                'linear_user_id' => $userInfo['id'] ?? null,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => $expiresAt,
                'scope' => isset($tokenData['scope']) ? explode(',', $tokenData['scope']) : ['read', 'write'],
            ]);

            Cache::forget('linear_oauth_state-' . $state);

            return response()->json([
                'message' => 'Linear integration successfully connected',
                'integration' => [
                    'id' => $integration->id,
                    'user_name' => $integration->user_name,
                    'user_email' => $integration->user_email,
                    'expires_at' => $integration->expires_at,
                    'scope' => $integration->scope,
                ],
            ]);
        } catch (\Exception $e) {
            // Clear session data on error
            Cache::forget('linear_oauth_state-' . $state);

            return response()->json([
                'error' => 'Failed to complete OAuth authorization: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function status(Request $request): JsonResponse
    {
        $integration = LinearIntegration::first();

        if (!$integration) {
            return response()->json([
                'connected' => false,
                'message' => 'No Linear integration found',
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
        $integration = LinearIntegration::first();

        if (!$integration) {
            return response()->json([
                'error' => 'No Linear integration found',
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
                'message' => 'Linear integration successfully disconnected',
                'disconnected_integration' => $integrationDetails,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to disconnect Linear integration: ' . $e->getMessage(),
            ], 500);
        }
    }
}