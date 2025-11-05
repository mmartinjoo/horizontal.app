<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class CentralIntegrationController
{
    public function callback(Request $request, string $provider)
    {
        $request->validate([
            'state' => ['required'],
        ]);

        $tenantId = $this->extractTenantIdFromState($request->get('state'));
        $tenant = Tenant::findOrFail($tenantId);
        $domain = $tenant->domains()->first();

        if (App::isLocal()) {
            $url = "http://{$domain->domain}:9996/api/integrations/{$provider}/oauth/callback";
        } else {
            $url = "https://{$domain->domain}/api/integrations/{$provider}/oauth/callback";
        }

        return redirect()->away($url . '?' . $request->getQueryString());
    }

    private function extractTenantIdFromState(string $state): string
    {
        // array be like ['tenant_id=abc', 'random_str=xyz']
        $parts = explode('|', $state);
        foreach ($parts as $part) {
            if (str_starts_with($part, 'tenant_id=')) {
                return substr($part, strlen('tenant_id='));
            }
        }

        throw new Exception('tenant_id not found');
    }
}