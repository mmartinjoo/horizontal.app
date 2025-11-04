<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function redirectToProvider(string $provider)
    {
        $this->validateProvider($provider);

        $tenantId = tenancy()->tenant->id;

        $redirectUrl = Socialite::driver($provider)
            ->stateless()            
            ->with([
                'state' => "tenant_id={$tenantId}",
            ])
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'url' => $redirectUrl,
        ]);
    }

    /**
     * Handle OAuth provider callback
     */
    public function handleProviderCallback(Request $request, string $provider)
    {
        $this->validateProvider($provider);

        try {
            $socialiteUser = Socialite::driver($provider)
                ->stateless()
                ->user();
        } catch (Exception $exception) {
            return response()->json([
                'message' => 'Failed to authenticate with '.$provider,
                'error' => $exception->getMessage(),
            ], 401);
        }        

        $user = User::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if (!$user) {
            $existingUser = User::query()
                ->where('email', $socialiteUser->getEmail())
                ->first();

            if ($existingUser) {
                return response()->json([
                    'message' => 'You are already logged in with ' . $existingUser->provider,
                ], 401);
            }            

            $user = User::create([
                'name' => $socialiteUser->getName() ?? $socialiteUser->getNickname(),
                'email' => $socialiteUser->getEmail(),
                'provider' => $provider,
                'provider_id' => $socialiteUser->getId(),
                'provider_token' => $socialiteUser->token,
                'avatar' => $socialiteUser->getAvatar(),
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(32)),
            ]);
        } else {
            $user->update([
                'provider_token' => $socialiteUser->token,
                'avatar' => $socialiteUser->getAvatar(),
            ]);
        }

        $token = $user->createToken('oauth-token')->plainTextToken;

        if (App::isLocal()) {
            $tenant = tenant();
            $domain = $tenant->domains->first();
            $url = 'http://' .  $domain->domain . ':9996/after-login?token=' . $token; 

            // this is needed because the GitHub app cannot have 'localhost' in the callback URL
            // so we use a local tunnel (see Makefile)
            // at this point the app is at a URL like https://tenant2-horizontal.loca.lt/
            // redirecting to a fronted route needs `away`
            return redirect()->away($url);
        } else {
            // in prod everything happens at `tenant.horizontal.app`
            return redirect('/after-login?token=' . $token);
        }
    }

    private function validateProvider(string $provider): void
    {
        $allowedProviders = ['github', 'google'];

        if (!in_array($provider, $allowedProviders)) {
            abort(422, 'Invalid provider. Allowed providers: '.implode(', ', $allowedProviders));
        }
    }
}
