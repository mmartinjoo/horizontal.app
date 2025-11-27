<?php

namespace App\Http\Controllers;

use App\Models\GithubIntegration;
use App\Models\GithubRepository;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use App\Services\Integration\CodeRepository\Github\Github;
use App\Services\Integration\CodeRepository\Github\GithubOAuth;
use App\Services\Url;
use Exception;
use Illuminate\Http\Request;

class GithubIntegrationController
{
    public function __construct(
        private GithubOAuth $githubOAuth,
    ) {
    }

    public function authorize()
    {
        $urlData = $this->githubOAuth->generateAuthorizationUrl();
        return response()->json([
            'authorization_url' => $urlData['authorization_url'],
        ]);
    }

    public function callback(Request $request)
    {
        $errorMessage = '';
        try {
            $installationId = $request->get('installation_id');
            if (!$installationId) {
                $errorMessage = 'GitHub integration failed';
                throw new Exception($errorMessage);
            }

            match ($request->get('setup_action')) {
                'install' => $this->installApp($installationId),
                'update' => $this->updateApp($installationId),
                default => throw new Exception('Unknown setup action'),
            };
        } finally {
            return redirect()->away(Url::createOnboardingCallbackFrontendUrl(
                tenant: tenancy()->tenant, 
                provider: 'github',
                step: 'code-repository',
            ));
        }
    }

    public function status()
    {
        $integration = GithubIntegration::first();
        if (!$integration) {
            return response()->json([
                'connected' => false,
                'message' => 'No GitHub integration found',
            ]);
        }

        return response()->json([
            'connected' => true,
            'status' => 'active',
            'integration' => [
                'id' => $integration->id,
                'installation_id' => $integration->installation_id,
                'number_of_repositories' => $integration->repositories()->count(),
                'created_at' => $integration->created_at,
                'updated_at' => $integration->updated_at,
            ],
        ]);
    }

    public function disconnect()
    {
        $integration = GithubIntegration::first();
        if (!$integration) {
            return response()->json([
                'error' => 'No GitHub integration found',
            ], 404);
        }

        try {
            $integrationDetails = [
                'id' => $integration->id,
                'installation_id' => $integration->installation_id,
            ];

            $integration->delete();

            return response()->json([
                'message' => 'GitHub integration successfully disconnected',
                'disconnected_integration' => $integrationDetails,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to disconnect GitHub integration: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function installApp(int $installationId): GithubIntegration
    {
        return GithubIntegration::create([
            'installation_id' => $installationId,
        ]);
    }

    private function updateApp(int $installationId): GithubIntegration
    {
        return GithubIntegration::where('installation_id', $installationId)->firstOrFail();
    }

    public function resources(Github $github)
    {
        $resources = $github->repositories()
            ->map(function (Repository $repository) {
                return [
                    'id' => $repository->externalId,
                    'title' => $repository->name,
                    'description' => '',
                ];
            });

        return response()->json([
            'resources' => $resources,
        ]);
    }

    public function configure(Request $request, Github $github)
    {
        $request->validate([
            'selected_resources' => ['required', 'array'],
            'selected_resources.*' => ['required', 'string'],
        ]);

        $selectedResourceIds = $request->get('selected_resources');
        $integration = GithubIntegration::firstOrFail();

        GithubRepository::query()
            ->where('github_integration_id', $integration->id)
            ->delete();

        $resources = $github->repositories();
        /** @var Repository $resource */
        foreach ($resources as $resource) {
            if (in_array($resource->externalId, $selectedResourceIds)) {
                GithubRepository::create([
                    'name' => $resource->name,
                    'external_id' => $resource->externalId,
                    'github_integration_id' => $integration->id,
                ]);
            }
        }
    }
}
