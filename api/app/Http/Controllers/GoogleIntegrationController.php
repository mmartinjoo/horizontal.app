<?php

namespace App\Http\Controllers;

use App\Http\Requests\GoogleOAuthCallbackRequest;
use App\Services\Integration\Google\GoogleOAuthService;
use App\Services\Url;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

abstract class GoogleIntegrationController extends Controller
{
    abstract protected function hasExistingIntegration(): bool;
    abstract protected function getIntegration(): Model;
    abstract protected function getStateCacheKey(): string;
    abstract protected function createIntegration(array $userInfo, array $tokenData, Carbon $expiresAt): Model;
    abstract protected function createRedirectUrlToOnboarding(string $errorMessage): string;

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
            Cache::set($this->getStateCacheKey() . $authData['random_str'], $authData['random_str'], 600); // 10 minutes

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

    public function callback(GoogleOAuthCallbackRequest $request)
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
            $cacheState = Cache::get($this->getStateCacheKey() . $randomStr);
            if (!$cacheState || !$this->googleOAuthService->validateState($randomStr, $cacheState)) {
                $errorMessage = 'Invalid OAuth state. Please restart the authorization process.'; 
                throw new Exception($errorMessage);
            }

            $tokenData = $this->googleOAuthService->exchangeCodeForToken($code);
            
            $userInfo = $this->googleOAuthService->getUserInfo($tokenData['access_token']);

            $expiresAt = now()->addSeconds($tokenData['expires_in'] ?? 86400); // Default 24 hours

            $this->createIntegration($userInfo, $tokenData, $expiresAt);            
        } finally {
            Cache::forget($this->getStateCacheKey() . $randomStr);
            return redirect()->away($this->createRedirectUrlToOnboarding($errorMessage));
        }
    }

    public function status(): JsonResponse
    {
        $integration = $this->getIntegration();

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

    public function disconnect(): JsonResponse
    {
        $integration = $this->getIntegration();

        if (!$integration) {
            return response()->json([
                'error' => 'No Google Drive integration found',
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
                'message' => 'Google Drive integration successfully disconnected',
                'disconnected_integration' => $integrationDetails,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to disconnect Google integration: ' . $e->getMessage(),
            ], 500);
        }
    }
}