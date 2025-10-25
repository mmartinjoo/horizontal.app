<?php

namespace App\Http\Controllers;

use App\Models\GithubIntegration;
use App\Models\GithubRepository;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use App\Services\Integration\CodeRepository\GitHub\GitHub;
use App\Services\Integration\CodeRepository\Github\GithubOAuth;
use Exception;
use Illuminate\Http\Request;

class GithubIntegrationController
{
    public function __construct(
        private GithubOAuth $githubOAuth,
        private GitHub $github,
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
        $installationId = $request->get('installation_id');
        if (!$installationId) {
            return response('GitHub integration failed', 400);
        }

        $integration = match ($request->get('setup_action')) {
            'install' => $this->installApp($installationId),
            'update' => $this->updateApp($installationId),
            default => dd($request->get('setup_action')),
        };

        return response()->json([
            'message' => 'GitHub integration successfully connected',
            'integration' => [
                'id' => $integration->id,
                'installation_id' => $integration->installation_id,
            ],
        ]);
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
        $integration = GithubIntegration::create([
            'installation_id' => $installationId,
        ]);

        /** @var Repository $repository */
        foreach ($this->github->repositories() as $repository) {
            GithubRepository::create([
                'github_integration_id' => $integration->id,
                'external_id' => $repository->externalId,
                'name' => $repository->name,
            ]);
        }

        return $integration;
    }

    private function updateApp(int $installationId): GithubIntegration
    {
        $integration = GithubIntegration::where('installation_id', $installationId)->firstOrFail();
        GithubRepository::query()
            ->where('github_integration_id', $integration->id)
            ->delete();

        /** @var Repository $repository */
        foreach ($this->github->repositories() as $repository) {
            GithubRepository::create([
                'github_integration_id' => $integration->id,
                'external_id' => $repository->externalId,
                'name' => $repository->name,
            ]);
        }

        return $integration;
    }
}
