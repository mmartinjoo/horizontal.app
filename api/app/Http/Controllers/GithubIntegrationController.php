<?php

namespace App\Http\Controllers;

use App\Models\GithubIntegration;
use Illuminate\Http\Request;

class GithubIntegrationController
{
    public function handleInstallation(Request $request)
    {
        $payload = $request->all();
        $action = $payload['action'] ?? null;
        $installation = $payload['installation'] ?? null;

        if (!$installation) {
            return response('No installation data', 400);
        }

        $installationId = $installation['id'];
        $accountLogin = $installation['account']['login'];

        switch ($action) {
            case 'created':
                $this->handleInstallationCreated($installationId, $accountLogin, $installation);
                break;

            case 'deleted':
                $this->handleInstallationDeleted($installationId);
                break;
        }

        return response('', 200);
    }

    private function handleInstallationCreated(int $installationId, string $accountLogin, array $installation): void
    {
        GithubIntegration::create([
            'installation_id' => $installationId,
            'metadata' => [
                'account_login' => $accountLogin,
                'account_type' => $installation['account']['type'],
                'target_type' => $installation['target_type'],
            ],
        ]);
    }

    private function handleInstallationDeleted(int $installationId): void
      {
          GithubIntegration::where('installation_id', $installationId)
              ->delete();
      }
}
