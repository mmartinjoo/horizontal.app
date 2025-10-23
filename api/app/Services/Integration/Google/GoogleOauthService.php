<?php

namespace App\Services\Integration\Google;

use Google\Client;
use Illuminate\Support\Str;

class GoogleOAuthService
{
    private Client $client;

    public function __construct(private array $config) 
    {
        $redirecUrl = "https://tenant2-horizontal.loca.lt/api/integrations/google/oauth/callback";
        $this->client = new Client();
        $this->client->setAuthConfig($config);
        $this->client->setAccessType('offline');
        $this->client->addScope('https://www.googleapis.com/auth/chat.spaces');
        $this->client->addScope('https://www.googleapis.com/auth/chat.spaces.readonly');
        $this->client->addScope('https://www.googleapis.com/auth/chat.memberships');
        $this->client->addScope('https://www.googleapis.com/auth/chat.memberships.readonly');
        $this->client->addScope('https://www.googleapis.com/auth/chat.messages');
        $this->client->addScope('https://www.googleapis.com/auth/chat.messages.readonly');
    }

    public function generateAuthorizationUrl(): array
    {
        $state = Str::random(40);
        $url = $this->client->createAuthUrl(
            queryParams: [
                'state' => $state,
            ],
        );
        return [
            'authorization_url' => $url,
            'state' => $state,
        ];
    }

    public function exchangeCodeForToken(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);
        $this->client->setAccessToken($token);

        return $token;
    }

    public function validateState(string $providedState, string $expectedState): bool
    {
        return hash_equals($expectedState, $providedState);
    }
}