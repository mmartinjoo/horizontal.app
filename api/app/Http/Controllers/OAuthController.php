<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class OAuthController extends Controller
{
    public function redirectToProvider(string $provider): JsonResponse
    {
        $this->validateProvider($provider);

        $redirectUrl = Socialite::driver($provider)
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'url' => $redirectUrl,
        ]);
    }

    /**
     * Handle OAuth provider callback
     */
    public function handleProviderCallback(string $provider): JsonResponse
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
            $user = User::query()
                ->where('email', $socialiteUser->getEmail())
                ->first();

            if ($user) {
                $user->update([
                    'provider' => $provider,
                    'provider_id' => $socialiteUser->getId(),
                    'provider_token' => $socialiteUser->token,
                    'avatar' => $socialiteUser->getAvatar(),
                ]);
            } else {
                $user = User::query()->create([
                    'name' => $socialiteUser->getName() ?? $socialiteUser->getNickname(),
                    'email' => $socialiteUser->getEmail(),
                    'provider' => $provider,
                    'provider_id' => $socialiteUser->getId(),
                    'provider_token' => $socialiteUser->token,
                    'avatar' => $socialiteUser->getAvatar(),
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(32)),
                ]);
            }
        } else {
            $user->update([
                'provider_token' => $socialiteUser->token,
                'avatar' => $socialiteUser->getAvatar(),
            ]);
        }

        $token = $user->createToken('oauth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    private function validateProvider(string $provider): void
    {
        $allowedProviders = ['github', 'google'];

        if (!in_array($provider, $allowedProviders)) {
            abort(422, 'Invalid provider. Allowed providers: '.implode(', ', $allowedProviders));
        }
    }
}
