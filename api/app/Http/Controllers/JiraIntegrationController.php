<?php

namespace App\Http\Controllers;

use App\Http\Requests\JiraOAuthAuthorizeRequest;
use App\Http\Requests\JiraOAuthCallbackRequest;
use App\Models\JiraIntegration;
use App\Services\Integration\TaskManagement\Jira\JiraOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class JiraIntegrationController extends Controller
{
    public function __construct(
        private JiraOAuthService $jiraOAuthService
    ) {
    }

    public function authorize(JiraOAuthAuthorizeRequest $request): JsonResponse
    {
        $team = $this->getUserTeam($request);

        // Check if team already has a Jira integration
        $existingIntegration = JiraIntegration::where('team_id', $team->id)->first();
        if ($existingIntegration) {
            return response()->json([
                'error' => 'Team already has a Jira integration. Please disconnect first.',
            ], 409);
        }

        try {
            $authData = $this->jiraOAuthService->generateAuthorizationUrl(
                $request->input('jira_base_url')
            );

            // Store the state and jira_base_url temporarily in session/cache for validation
            Cache::set('jira_oauth_state-' . $authData['state'], $authData['state']);
            Cache::set('jira_base_url-' . $authData['state'], $authData['jira_base_url']);
            Cache::set('team_id-' . $authData['state'], $team->id);

            return response()->json([
                'authorization_url' => $authData['authorization_url'],
                'state' => $authData['state'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate authorization URL',
            ], 500);
        }
    }

    public function callback(JiraOAuthCallbackRequest $request): JsonResponse
    {
        $code = $request->input('code');
        $state = $request->input('state');

        // Validate OAuth state to prevent CSRF attacks
        $cacheState = Cache::get('jira_oauth_state-' . $state);
        $jiraBaseUrl = Cache::get('jira_base_url-' . $state);
        $teamId = Cache::get('team_id-' . $state);

        if (!$cacheState || !$this->jiraOAuthService->validateState($state, $cacheState)) {
            return response()->json([
                'error' => 'Invalid OAuth state. Please restart the authorization process.',
            ], 400);
        }

        if (!$teamId || !$jiraBaseUrl) {
            return response()->json([
                'error' => 'Session expired. Please restart the authorization process.',
            ], 400);
        }

        try {
            // Exchange authorization code for access token
            $tokenData = $this->jiraOAuthService->exchangeCodeForToken($code, $state);

            // Get accessible resources to find cloud ID
            $accessibleResources = $this->jiraOAuthService->getAccessibleResources($tokenData['access_token']);

            // Find the matching Jira instance by URL
            $cloudId = null;
            foreach ($accessibleResources as $resource) {
                if (isset($resource['url']) && str_contains($resource['url'], parse_url($jiraBaseUrl, PHP_URL_HOST))) {
                    $cloudId = $resource['id'];
                    break;
                }
            }

            if (!$cloudId) {
                throw new \Exception('Could not find cloud ID for Jira instance');
            }

            // Calculate token expiration time
            $expiresAt = now()->addSeconds($tokenData['expires_in'] ?? 3600);

            // Create the Jira integration record
            $integration = JiraIntegration::create([
                'team_id' => $teamId,
                'jira_base_url' => $jiraBaseUrl,
                'cloud_id' => $cloudId,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => $expiresAt,
                'scope' => explode(' ', $tokenData['scope'] ?? 'read:jira-user read:jira-work'),
            ]);

            Cache::forget('jira_oauth_state-' . $state);
            Cache::forget('jira_base_url-' . $state);
            Cache::forget('team_id-' . $state);

            return response()->json([
                'message' => 'Jira integration successfully connected',
                'integration' => [
                    'id' => $integration->id,
                    'jira_base_url' => $integration->jira_base_url,
                    'expires_at' => $integration->expires_at,
                    'scope' => $integration->scope,
                ],
            ]);
        } catch (\Exception $e) {
            // Clear session data on error
            Cache::forget('jira_oauth_state-' . $state);
            Cache::forget('jira_base_url-' . $state);
            Cache::forget('team_id-' . $state);

            return response()->json([
                'error' => 'Failed to complete OAuth authorization: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function status(Request $request): JsonResponse
    {
        $team = $this->getUserTeam($request);
        $integration = JiraIntegration::where('team_id', $team->id)->first();

        if (!$integration) {
            return response()->json([
                'connected' => false,
                'message' => 'No Jira integration found for this team',
            ]);
        }

        $isTokenValid = $integration->hasValidToken();
        $isExpired = $integration->isTokenExpired();

        return response()->json([
            'connected' => true,
            'integration' => [
                'id' => $integration->id,
                'jira_base_url' => $integration->jira_base_url,
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
        $team = $this->getUserTeam($request);
        $integration = JiraIntegration::where('team_id', $team->id)->first();

        if (!$integration) {
            return response()->json([
                'error' => 'No Jira integration found for this team',
            ], 404);
        }

        try {
            // Store integration details for response before deletion
            $integrationDetails = [
                'id' => $integration->id,
                'jira_base_url' => $integration->jira_base_url,
                'created_at' => $integration->created_at,
            ];

            // Delete the integration (tokens will be automatically cleaned up)
            $integration->delete();

            return response()->json([
                'message' => 'Jira integration successfully disconnected',
                'disconnected_integration' => $integrationDetails,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to disconnect Jira integration: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function getUserTeam(Request $request)
    {
        return $request->user()->team;
    }
}
