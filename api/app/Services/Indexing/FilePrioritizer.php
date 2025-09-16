<?php

namespace App\Services\Indexing;

use App\Services\Integration\Storage\DataTransferObjects\File;
use Generator;

class FilePrioritizer
{
    public function prioritize(Generator $files): array
    {
        $highQueue = [];
        $mediumQueue = [];
        $lowQueue = [];

        /** @var File $file */
        foreach ($files as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if ($file->isMediaFile() || $file->isZipFile()) {
                continue;
            }

            if ($file->isSmallerThan(20) && $file->wasUsedIn(60)) {
                $highQueue[] = $file;
                continue;
            }

            if ($file->isSmallerThan(10) && $file->wasUsedIn(120)) {
                $mediumQueue[] = $file;
                continue;
            }

            if ($file->isSmallerThan(10) && $file->wasUsedIn(180)) {
                $lowQueue[] = $file;
                continue;
            }
        }

        return [
            'high' => collect($highQueue)->sortByDesc('lastUsedAt'),
            'medium' => collect($mediumQueue)->sortByDesc('lastUsedAt'),
            'low' => collect($lowQueue)->sortByDesc('lastUsedAt'),
        ];
    }
}
