<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\Url;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class CentralIntegrationController
{
    public function callback(Request $request, string $provider)
    {
        $request->validate([
            'state' => ['required'],
        ]);

        $tenantId = Url::extractKeyFromState($request->get('state'), 'tenant_id');
        $tenant = Tenant::findOrFail($tenantId);
        

        $url = Url::createTenantIntegrationCallbackUrl($tenant, $provider);

        return redirect()->away($url . '?' . $request->getQueryString());
    }
}