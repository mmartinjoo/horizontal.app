<?php

namespace App\Services\Integration\Storage;

use App\Services\Integration\Storage\DataTransferObjects\File;
use App\Services\Integration\Storage\DataTransferObjects\Folder;
use Illuminate\Support\LazyCollection;

interface Storage
{
    public function revisionAuthors(File $file): array;
    public function comments(File $file): array;

    /**
     * @return LazyCollection<Folder>
     */
    public function folders(string $root = ''): LazyCollection;
    /**
     * @return LazyCollection<File>
     */
    public function files(string $root = ''): LazyCollection;
}