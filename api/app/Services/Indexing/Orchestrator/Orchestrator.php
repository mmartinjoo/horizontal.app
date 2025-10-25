<?php

namespace App\Services\Indexing\Orchestrator;

use App\Jobs\Indexing\CodeRepository\GitHub\IndexGitHub;
use App\Jobs\Indexing\Storage\GoogleDrive\IndexGoogleDrive;
use Exception;

class Orchestrator
{
    public function schedule()
    {
        // This will be merged into one `integrations` table
        $integrations = ['github'];
        foreach ($integrations as $integration) {
            $job = $this->createJob($integration);
            dispatch($job);
        }
    }

    public function createJob(string $integration)
    {
        return match ($integration) {
            'google_drive' => new IndexGoogleDrive,
            'github' => new IndexGitHub,
            default => throw new Exception('unknown integration'),
        };
    }
}
