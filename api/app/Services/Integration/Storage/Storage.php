<?php

namespace App\Services\Integration\Storage;

use App\Services\Integration\Storage\DataTransferObjects\File;
use App\Services\Integration\Storage\DataTransferObjects\Folder;
use Illuminate\Support\LazyCollection;

interface Storage
{
    public function getRevisionAuthors(File $file): array;
    public function getComments(File $file): array;

    /**
     * @return LazyCollection<Folder>
     */
    public function folders(string $root = ''): LazyCollection;
}