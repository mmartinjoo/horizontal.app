<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Services\Url;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function redirectToProvider(Request $request, string $provider)
    {
        $this->validateProvider($provider);

        $tenantId = tenancy()->tenant->id;
        $state = "tenant_id={$tenantId}";

        if ($request->has('invitation_token')) {
            $state .= "|invitation_token={$request->input('invitation_token')}";
        }

        $redirectUrl = Socialite::driver($provider)
            ->stateless()
            ->with([
                'state' => $state,
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
        } catch (Exception $ex) {
            return response()->json([
                'message' => 'Failed to authenticate with '.$provider,
                'error' => $ex->getMessage(),
            ], 401);
        }

        $invitationToken = null;
        if ($request->has('state')) {
            try {
                $invitationToken = Url::extractKeyFromState($request->input('state'), 'invitation_token');
            } catch (Exception $e) {
                // No invitation token in state, that's fine
            }
        }

        $user = User::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialiteUser->getId())
            ->first();

        if (! $user) {
            $existingUser = User::query()
                ->where('email', $socialiteUser->getEmail())
                ->first();

            if ($existingUser) {
                return response()->json([
                    'message' => 'You are already logged in with '.$existingUser->provider,
                ], 401);
            }

            if ($invitationToken) {
                $invitation = Invitation::findByToken($invitationToken);

                if (! $invitation || ! $invitation->isValid()) {
                    return response()->json([
                        'message' => 'Invalid or expired invitation.',
                    ], 403);
                }
            } else {
                return response()->json([
                    'message' => 'An invitation is required to create an account.',
                ], 403);
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

            if (isset($invitation)) {
                $invitation->markAsAccepted();
            }
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
            $url = 'http://'.$domain->domain.':9996/after-login?token='.$token;

            // this is needed because the GitHub app cannot have 'localhost' in the callback URL
            // so we use a local tunnel (see Makefile)
            // at this point the app is at a URL like https://tenant2-horizontal.loca.lt/
            // redirecting to a fronted route needs `away`
            return redirect()->away($url);
        } else {
            // in prod everything happens at `tenant.horizontal.app`
            return redirect('/after-login?token='.$token);
        }
    }

    private function validateProvider(string $provider): void
    {
        $allowedProviders = ['github', 'google'];

        if (! in_array($provider, $allowedProviders)) {
            abort(422, 'Invalid provider. Allowed providers: '.implode(', ', $allowedProviders));
        }
    }
}
