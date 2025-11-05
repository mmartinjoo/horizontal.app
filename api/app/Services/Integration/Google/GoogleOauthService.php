<?php

namespace App\Services\Integration\Google;

use Google\Client;
use Google\Service\Oauth2;
use Illuminate\Support\Str;

abstract class GoogleOAuthService
{
    protected Client $client;

    public function __construct(protected array $config) 
    {
    }

    public function generateAuthorizationUrl(): array
    {
        $randomStr = Str::random(40);
        $tenantId = tenancy()->tenant->id;
        $state = "tenant_id={$tenantId}|random_str={$randomStr}";

        $url = $this->client->createAuthUrl(
            queryParams: [
                'state' => $state,
            ],
        );
        return [
            'authorization_url' => $url,
            'random_str' => $state,
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