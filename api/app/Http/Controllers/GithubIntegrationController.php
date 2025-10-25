<?php

namespace App\Http\Controllers;

use App\Models\GithubIntegration;
use App\Models\GithubRepository;
use App\Services\Integration\CodeRepository\DataTransferObjects\Repository;
use App\Services\Integration\CodeRepository\GitHub\GitHub;
use App\Services\Integration\CodeRepository\Github\GithubOAuth;
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

        $integration = GithubIntegration::create([
            'installation_id' => $installationId
        ]);

        /** @var Repository $repository */
        foreach ($this->github->repositories() as $repository) {
            GithubRepository::create([
                'github_integration_id' => $integration->id,
                'external_id' => $repository->externalId,
                'name' => $repository->name,
            ]);
        }

        return response()->json([
            'message' => 'GitHub integration successfully connected',
            'integration' => [
                'id' => $integration->id,
                'installation_id' => $integration->installation_id,
            ],
        ]);
    }
}
