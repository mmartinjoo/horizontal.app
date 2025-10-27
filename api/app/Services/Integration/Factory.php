<?php

namespace App\Services\Integration;

use App\Services\Integration\Communication\Communication;
use App\Services\Integration\Communication\GoogleChat\GoogleChat;
use App\Services\Integration\Communication\Slack\Slack;
use Exception;

class Factory
{
    public function createCommunication(string $vendor): Communication
    {
        return match ($vendor) {
            'slack' => new Slack(config('services.slack.base_url'), config('services.slack.bot_user_oauth_token')),
            'google_chat' => new GoogleChat(),
            default => throw new Exception('Unknown communincation tool'),
        };
    }
}