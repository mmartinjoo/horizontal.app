<?php

namespace App\Services\Integration\Google;

use Google\Client;

class GoogleDriveOAuthService extends GoogleOAuthService
{
    public function __construct(protected array $config)
    {
        $this->client = new Client();
        $this->client->setAuthConfig($config);
        $this->client->setAccessType('offline');
        $this->client->addScope('https://www.googleapis.com/auth/drive.readonly');
        $this->client->addScope('https://www.googleapis.com/auth/drive.metadata.readonly');
        $this->client->addScope('https://www.googleapis.com/auth/drive.activity.readonly');
        $this->client->addScope('https://www.googleapis.com/auth/userinfo.profile');
        $this->client->addScope('https://www.googleapis.com/auth/userinfo.email');
        $this->client->addScope('openid');
    }
}