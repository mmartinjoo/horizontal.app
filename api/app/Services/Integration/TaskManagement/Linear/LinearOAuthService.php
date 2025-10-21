<?php

namespace App\Services\Integration\TaskManagement\Linear;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LinearOAuthService
{
    private const LINEAR_OAUTH_BASE_URL = 'https://linear.app/oauth/authorize';
    private const LINEAR_TOKEN_URL = 'https://api.linear.app/oauth/token';

    private array $scopes = [
        'read',
        'write',
        'issues:create',
        'comments:create',
    ];

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri
    ) {}

    public function generateAuthorizationUrl(?string $state = null): array
    {
        $state = $state ?: Str::random(40);

        $queryParams = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(',', $this->scopes),
            'state' => $state,
        ]);

        return [
            'authorization_url' => self::LINEAR_OAUTH_BASE_URL . '?' . $queryParams,
            'state' => $state,
        ];
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()->post(self::LINEAR_TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'code' => $code,
            'redirect_uri' => $this->redirectUri,
        ]);

        if (!$response->successful()) {
            throw new Exception('Failed to exchange authorization code for token: ' . $response->body());
        }

        return $response->json();
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $response = Http::asForm()->post(self::LINEAR_TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        if (!$response->successful()) {
            throw new Exception('Failed to refresh access token: ' . $response->body());
        }

        return $response->json();
    }

    public function validateState(string $providedState, string $expectedState): bool
    {
        return hash_equals($expectedState, $providedState);
    }

    public function getUserInfo(string $accessToken): array
    {
        $query = '
            query {
                viewer {
                    id
                    name
                    email
                    displayName
                }
            }
        ';

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->post('https://api.linear.app/graphql', [
                'query' => $query,
            ]);

        if (!$response->successful()) {
            throw new Exception('Failed to get user info: ' . $response->body());
        }

        $data = $response->json();

        if (isset($data['errors'])) {
            throw new Exception('GraphQL errors: ' . json_encode($data['errors']));
        }

        return $data['data']['viewer'] ?? [];
    }
}