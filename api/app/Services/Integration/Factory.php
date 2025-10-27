<?php

namespace App\Services\Integration;

use App\Services\Indexing\FilePrioritizer;
use App\Services\Integration\CodeRepository\CodeRepository;
use App\Services\Integration\CodeRepository\GitHub\GitHub;
use App\Services\Integration\CodeRepository\Github\GithubOAuth;
use App\Services\Integration\Communication\Communication;
use App\Services\Integration\Communication\GoogleChat\GoogleChat;
use App\Services\Integration\Communication\Slack\Slack;
use App\Services\Integration\Storage\GoogleDrive\GoogleDrive;
use App\Services\Integration\Storage\Storage;
use App\Services\Integration\TaskManagement\Jira\Jira;
use App\Services\Integration\TaskManagement\Jira\JiraTokenManager;
use App\Services\Integration\TaskManagement\Linear\Linear;
use App\Services\Integration\TaskManagement\Linear\LinearTokenManager;
use App\Services\Integration\TaskManagement\TaskManagement;
use Exception;

class Factory
{
    public function createCommunication(string $vendor): Communication
    {
        return match ($vendor) {
            'slack' => new Slack(config('services.slack.base_url'), config('services.slack.bot_user_oauth_token')),
            'google_chat' => new GoogleChat(),
            default => throw new Exception('Unknown communincation integration'),
        };
    }

    public function createStorage(string $vendor): Storage
    {
        return match ($vendor) {
            'google_drive' => new GoogleDrive(new FilePrioritizer()),
            default => throw new Exception('Unknown file storage integration'),
        };
    }

    public function createCodeRepository(string $vendor): CodeRepository
    {
        return match($vendor) {
            'github' => new GitHub(app(GithubOAuth::class), config('services.github.base_url')),
            default => throw new Exception('Unknown code repository integration'),
        };
    }

    public function createTaskManagement(string $vendor): TaskManagement
    {
        return match($vendor) {
            'jira' => new Jira(app(JiraTokenManager::class)),
            'linear' => new Linear(app(LinearTokenManager::class)),
            default => throw new Exception('Unknown task management integration'),
        };
    }
}