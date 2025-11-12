<?php

namespace App\Services;

use App\Models\Tenant;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;

class Url
{
    public static function createOnboardingCallbackFrontendUrl(Tenant $tenant, string $provider, string $step): string
    {
        $domain = $tenant->domains()->first();
        if (App::isLocal()) {
            return "http://{$domain->domain}:9996/onboarding/callback?provider=$provider&step=$step";
        } else {
            return "https://{$domain->domain}/onboarding/callback?provider=$provider&step=$step";
        }
    }

    public static function createOnboardingFrontendUrl(Tenant $tenant, string $step, string $provider, string $errorMessage): string
    {
        $domain = $tenant->domains()->first();
        if (App::isLocal()) {
            return "http://{$domain->domain}:9996/onboarding/{$step}?provider=$provider&error=$errorMessage";
        } else {
            return "https://{$domain->domain}/onboarding/{$step}?provider=$provider&error=$errorMessage";
        }
    }

    public static function createTenantOAuthLoginCallbackUrl(Tenant $tenant, string $provider, Request $request): string
    {
        $domain = $tenant->domains()->first();
        if (App::isLocal()) {
            $url = "http://{$domain->domain}:9996/api/auth/{$provider}/callback";
        } else {
            $url = "https://{$domain->domain}/api/auth/{$provider}/callback";
        }

        $url .= '?' . http_build_query($request->query());
        return $url;
    }

    public static function createTenantIntegrationCallbackUrl(Tenant $tenant, string $provider): string
    {
        $domain = $tenant->domains()->first();
        if (App::isLocal()) {
            return "http://{$domain->domain}:9996/api/integrations/{$provider}/oauth/callback";
        } else {
            return "https://{$domain->domain}/api/integrations/{$provider}/oauth/callback";
        }
    }

    public static function createTenantIntegrationCallbackUrlWithQuery(Tenant $tenant, string $provider, Request $request): string
    {
        $url = self::createTenantIntegrationCallbackUrl($tenant, $provider);
        $url .= '?' . http_build_query($request->query());
        return $url;
    }

    public static function createInvitationAcceptanceUrl(Tenant $tenant, string $token): string
    {
        $domain = $tenant->domains()->first();
        if (App::isLocal()) {
            return "http://{$domain->domain}:9996/accept-invitation?token={$token}";
        } else {
            return "https://{$domain->domain}/accept-invitation?token={$token}";
        }
    }

    /**
     * `state` is an OAUth GET param use in integrations and OAuth login
     * It can contain many fields: `state=tenant_id=abc|random_str=asdf|code=1234`
     */
    public static function extractKeyFromState(string $state, string $key): string
    {
        // array be like ['tenant_id=abc', 'random_str=xyz']
        $parts = explode('|', $state);
        foreach ($parts as $part) {
            if (str_starts_with($part, "{$key}=")) {
                return substr($part, strlen("{$key}="));
            }
        }

        throw new Exception("$key not found");
    }
}
