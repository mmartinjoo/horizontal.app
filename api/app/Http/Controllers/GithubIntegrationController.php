<?php

namespace App\Http\Controllers;

use App\Models\GithubIntegration;
use App\Services\Integration\CodeRepository\Github\GithubOAuth;
use Illuminate\Http\Request;

class GithubIntegrationController
{
    public function __construct(private GithubOAuth $githubOAuth)
    {
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

        return response()->json([
            'message' => 'GitHub integration successfully connected',
            'integration' => [
                'id' => $integration->id,
                'installation_id' => $integration->installation_id,
            ],
        ]);
    }
}
