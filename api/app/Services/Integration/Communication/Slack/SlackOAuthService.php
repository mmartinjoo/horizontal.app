<?php

namespace App\Services\Integration\Communication\Slack;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SlackOAuthService
{
    private array $scopes = [
        'channels:read',
        'channels:history',
        'groups:read',
        'groups:history',
        'im:read',
        'im:history',
        'mpim:read',
        'mpim:history',
        'users:read',
        'users:read.email',
    ];

    public function __construct(
        private array $config,
    ) {}

    public function generateAuthorizationUrl(): array
    {
        $str = Str::random(40);
        $tenantId = tenancy()->tenant->id;
        $state = "tenant_id={$tenantId}|random_str={$str}";

        $queryParams = http_build_query([
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(',', $this->scopes),
            'state' => $state,
        ]);

        return [
            'authorization_url' => 'https://slack.com/oauth/v2/authorize?'.$queryParams,
            'random_str' => $str,
        ];
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = Http::asForm()->post('https://slack.com/api/oauth.v2.access', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'code' => $code,
            'redirect_uri' => $this->config['redirect_uri'],
        ]);

        if (! $response->successful()) {
            throw new Exception('Failed to exchange authorization code for token: '.$response->body());
        }

        return $response->json();
    }

    public function validateState(string $providedState, string $expectedState): bool
    {
        return hash_equals($expectedState, $providedState);
    }

    public function getUserInfo(string $accessToken, string $userId): array
    {
        $response = Http::withToken($accessToken)
            ->get('https://slack.com/api/users.info', [
                'user' => $userId,
            ]);

        if (! $response->successful() || ! $response->json('ok')) {
            throw new Exception('Failed to get user info: '.$response->body());
        }

        return $response->json('user');
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $response = Http::asForm()->post('https://slack.com/api/oauth.v2.access', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $refreshToken,
        ]);

        if (! $response->successful() || ! $response->json('ok')) {
            throw new Exception('Failed to refresh access token: '.$response->body());
        }

        return $response->json();
    }

    public function revokeToken(string $accessToken): void
    {
        $response = Http::asForm()->post('https://slack.com/api/auth.revoke', [
            'token' => $accessToken,
        ]);

        if (! $response->successful() || ! $response->json('ok')) {
            throw new Exception('Failed to revoke token: '.$response->body());
        }
    }
}
