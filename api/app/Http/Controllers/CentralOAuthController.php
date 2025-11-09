<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Url;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class CentralOAuthController
{
    public function callback(Request $request, string $provider)
    {
        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required']
        ]);

        $tenantId = Str::after($request->get('state'), 'tenant_id=');
        if (!$tenantId) {
            abort(429, "tenant_id is required");
        }
        $tenant = Tenant::findOrFail($tenantId);

        $url = Url::createTenantIntegrationCallbackUrlWithCode($tenant, $provider, $request->get('code'));

        return redirect()->away($url);
    }
}