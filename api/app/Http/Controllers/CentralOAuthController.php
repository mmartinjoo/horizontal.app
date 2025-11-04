<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class CentralOAuthController
{
    public function callback(Request $request, string $provider)
    {
        $request->validate([
            'tenant_id' => ['required', 'exists:tenants,id'],
            'code' => ['required', 'string'],
        ]);

        $tenant = Tenant::findOrFail($request->get('tenant_id'));
        $domain = $tenant->domains()->first();

        if (App::isLocal()) {
            $url = "http://{$domain->domain}:9996/api/auth/{$provider}/callback?code=" . $request->get('code');
        } else {
            $url = "https://{$domain->domain}/api/auth/{$provider}/callback?code=" . $request->get('code');
        }

        return redirect()->away($url);
    }
}