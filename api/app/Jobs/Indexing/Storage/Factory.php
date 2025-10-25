<?php

namespace App\Jobs\Indexing\Storage;

use App\Services\Integration\Storage\GoogleDrive\GoogleDrive;
use App\Services\Integration\Storage\Storage;
use Exception;

class Factory
{
    public function create(string $vendor): Storage
    {
        return match ($vendor) {
            'google_drive' => app(GoogleDrive::class),
            default => throw new Exception('not implemented yet'),
        };
    }
}