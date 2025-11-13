<?php

namespace App\Services\Integration;

use App\Services\Integration\CodeRepository\CodeRepository;
use App\Services\Integration\CodeRepository\GitHub\GitHub;
use App\Services\Integration\Communication\Communication;
use App\Services\Integration\Communication\GoogleChat\GoogleChat;
use App\Services\Integration\Communication\Slack\Slack;
use App\Services\Integration\Storage\GoogleDrive\GoogleDrive;
use App\Services\Integration\Storage\Storage;
use App\Services\Integration\TaskManagement\Jira\Jira;
use App\Services\Integration\TaskManagement\Linear\Linear;
use App\Services\Integration\TaskManagement\TaskManagement;
use Exception;

class Factory
{
    public function createCommunication(string $vendor): Communication
    {
        return match ($vendor) {
            'slack' => app(Slack::class),
            'google_chat' => app(GoogleChat::class),
            default => throw new Exception('Unknown communincation integration'),
        };
    }

    public function createStorage(string $vendor): Storage
    {
        return match ($vendor) {
            'google_drive' => app(GoogleDrive::class),
            default => throw new Exception('Unknown file storage integration'),
        };
    }

    public function createCodeRepository(string $vendor): CodeRepository
    {
        return match($vendor) {
            'github' => app(GitHub::class),
            default => throw new Exception('Unknown code repository integration'),
        };
    }

    public function createTaskManagement(string $vendor): TaskManagement
    {
        return match($vendor) {
            'jira' => app(Jira::class),
            'linear' => app(Linear::class),
            default => throw new Exception('Unknown task management integration'),
        };
    }
}