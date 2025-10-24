<?php

namespace App\Services\Integration\Google;

use Google\Client;
use Google\Service\Oauth2;
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
        $this->client->addScope('https://www.googleapis.com/auth/chat.spaces.readonly');
        $this->client->addScope('https://www.googleapis.com/auth/chat.memberships.readonly');
        $this->client->addScope('https://www.googleapis.com/auth/chat.messages.readonly');
        $this->client->addScope('https://www.googleapis.com/auth/userinfo.profile');
        $this->client->addScope('https://www.googleapis.com/auth/userinfo.email');
        $this->client->addScope('openid');
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

    public function getUserInfo(string $accessToken): array
    {
        $this->client->setAuthConfig($this->config);
        $this->client->setAccessToken($accessToken);
        $oauth2Service = new Oauth2($this->client);
        $userInfo = $oauth2Service->userinfo->get();

        return [
            'id' => $userInfo->getId(),
            'name' => $userInfo->getName(),
            'email' => $userInfo->getEmail(),
        ];
    }
}