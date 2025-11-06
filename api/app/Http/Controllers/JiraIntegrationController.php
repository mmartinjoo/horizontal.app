<?php

namespace App\Http\Controllers;

use App\Http\Requests\JiraOAuthAuthorizeRequest;
use App\Http\Requests\JiraOAuthCallbackRequest;
use App\Models\JiraIntegration;
use App\Models\JiraProject;
use App\Services\Integration\TaskManagement\DataTransferObjects\Project;
use App\Services\Integration\TaskManagement\Jira\Jira;
use App\Services\Integration\TaskManagement\Jira\JiraOAuthService;
use App\Services\Url;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Exception;

class JiraIntegrationController extends Controller
{
    public function __construct(
        private JiraOAuthService $jiraOAuthService
    ) {
    }

    public function authorize(JiraOAuthAuthorizeRequest $request): JsonResponse
    {
        $existingIntegration = JiraIntegration::first();
        if ($existingIntegration) {
            return response()->json([
                'error' => 'You already have a Jira integration. Please disconnect first.',
            ], 409);
        }

        try {
            $authData = $this->jiraOAuthService->generateAuthorizationUrl(
                $request->input('jira_base_url')
            );

            // Store the state and jira_base_url temporarily in session/cache for validation
            Cache::set('jira_oauth_state-' . $authData['random_str'], $authData['random_str']);
            Cache::set('jira_base_url-' . $authData['random_str'], $authData['jira_base_url']);

            return response()->json([
                'authorization_url' => $authData['authorization_url'],
                'random_str' => $authData['random_str'],
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

    public function callback(JiraOAuthCallbackRequest $request)
    {
        $errorMessage = '';
        try {
            $code = $request->input('code');
            $state = $request->input('state');
            $randomStr = Url::extractKeyFromState($state, 'random_str');

            // Validate OAuth state to prevent CSRF attacks
            $cacheState = Cache::get('jira_oauth_state-' . $randomStr);
            $jiraBaseUrl = Cache::get('jira_base_url-' . $randomStr);

            if (!$cacheState || !$this->jiraOAuthService->validateState($randomStr, $cacheState)) {
                $errorMessage = 'Invalid OAuth state. Please restart the authorization process.';
                throw new Exception($errorMessage);
            }

            if (!$jiraBaseUrl) {
                $errorMessage = 'Session expired. Please restart the authorization process.';
                throw new Exception($errorMessage);
            }

            // Exchange authorization code for access token
            $tokenData = $this->jiraOAuthService->exchangeCodeForToken($code);

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
                $errorMessage = 'Could not find cloud ID for Jira instance';
                throw new Exception($errorMessage);
            }

            // Calculate token expiration time
            $expiresAt = now()->addSeconds($tokenData['expires_in'] ?? 3600);

            JiraIntegration::create([
                'jira_base_url' => $jiraBaseUrl,
                'cloud_id' => $cloudId,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => $expiresAt,
                'scope' => explode(' ', $tokenData['scope'] ?? 'read:jira-user read:jira-work'),
            ]);
        } finally {
            Cache::forget('jira_oauth_state-' . $randomStr);
            Cache::forget('jira_base_url-' . $randomStr);
            return redirect()->away(Url::createOnboardingCallbackFrontendUrl(
                tenant: tenancy()->tenant, 
                provider: 'jira',
                step: 'task-management',
            ));
        }
    }

    public function status(Request $request): JsonResponse
    {
        $integration = JiraIntegration::first();

        if (!$integration) {
            return response()->json([
                'connected' => false,
                'message' => 'No Jira integration found',
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
        $integration = JiraIntegration::first();

        if (!$integration) {
            return response()->json([
                'error' => 'No Jira integration found',
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

    public function resources(Jira $jira)
    {
        $resources = $jira->projects()
            ->map(function (Project $project) {
                return [
                    'id' => $project->id,
                    'title' => $project->title,
                    'description' => '',
                ];
            });

        return response()->json([
            'resources' => $resources,
        ]);
    }

    public function configure(Request $request, Jira $jira)
    {
        $request->validate([
            'selected_resources' => ['required', 'array'],
            'selected_resources.*' => ['required', 'string'],
        ]);

        $selectedResourceIds = $request->get('selected_resources');
        $integration = JiraIntegration::firstOrFail();

        JiraProject::query()
            ->where('jira_integration_id', $integration->id)
            ->delete();

        $resources = $jira->projects();
        /** @var Project $resource */
        foreach ($resources as $resource) {
            if (in_array($resource->id, $selectedResourceIds)) {
                JiraProject::create([
                    'title' => $resource->title,
                    'external_id' => $resource->id,
                    'jira_integration_id' => $integration->id,
                ]);
            }
        }
    }
}
