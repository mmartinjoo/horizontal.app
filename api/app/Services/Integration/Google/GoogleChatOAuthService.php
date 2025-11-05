<?php

namespace App\Services\Integration\Google;

use Google\Client;

class GoogleChatOAuthService extends GoogleOAuthService
{
    public function __construct(protected array $config)
    {
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
}