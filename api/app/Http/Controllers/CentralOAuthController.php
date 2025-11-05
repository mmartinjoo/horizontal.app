<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        $domain = $tenant->domains()->first();

        if (App::isLocal()) {
            $url = "http://{$domain->domain}:9996/api/auth/{$provider}/callback?code=" . $request->get('code');
        } else {
            $url = "https://{$domain->domain}/api/auth/{$provider}/callback?code=" . $request->get('code');
        }

        return redirect()->away($url);
    }
}